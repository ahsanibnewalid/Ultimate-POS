<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class SaleController {
 public function index(Request $request) {
  $query=DB::table('sales')->where('tenant_id',$request->user()->tenant_id)->orderByDesc('id');
  if($from=$request->query('from'))$query->whereDate('created_at','>=',$from);
  if($to=$request->query('to'))$query->whereDate('created_at','<=',$to);
  return response()->json(['data'=>$query->limit(100)->get()]);
 }
 public function store(Request $request) {
  $data=$request->validate(['client_sale_id'=>['required','uuid'],'location_name'=>['required','string','max:120'],'items'=>['required','array','min:1','max:100'],'items.*.product_id'=>['required','integer','distinct'],'items.*.quantity'=>['required','integer','min:1','max:100000'],'payments'=>['required','array','min:1','max:10'],'payments.*.method'=>['required','in:cash,card,mobile'],'payments.*.amount'=>['required','numeric','gt:0']]);
  $tenantId=(int)$request->user()->tenant_id;
  $result=DB::transaction(function()use($data,$tenantId,$request){
   $existing=DB::table('sales')->where('tenant_id',$tenantId)->where('client_sale_id',$data['client_sale_id'])->first();
   if($existing)return ['id'=>$existing->id,'receipt_number'=>$existing->receipt_number,'total'=>$existing->total,'replayed'=>true];
   $products=Product::where('tenant_id',$tenantId)->whereIn('id',collect($data['items'])->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
   if($products->count()!==count($data['items']))throw ValidationException::withMessages(['items'=>['One or more products do not belong to this tenant.']]);
   $lines=[];$totalCents=0;
   foreach($data['items'] as $item){
    $product=$products->get($item['product_id']);if(!$product->active)throw ValidationException::withMessages(['items'=>['Inactive products cannot be sold.']]);
    $qty=(int)$item['quantity'];$priceCents=(int)round(((float)$product->price)*100);$lineCents=$priceCents*$qty;
    if($product->track_stock){$onHand=(int)DB::table('inventory_ledger')->where('tenant_id',$tenantId)->where('product_id',$product->id)->where('location_name',$data['location_name'])->sum('quantity_delta');if($onHand<$qty)throw ValidationException::withMessages(['items'=>["Insufficient stock for {$product->name}. Available: {$onHand}."]]);}
    $totalCents+=$lineCents;if($totalCents>99999999999999)throw ValidationException::withMessages(['items'=>['Sale total exceeds the supported transaction limit.']]);
    $lines[]=['product'=>$product,'quantity'=>$qty,'unit_price'=>number_format($priceCents/100,2,'.',''),'line_total'=>number_format($lineCents/100,2,'.','')];
   }
   $paidCents=0;foreach($data['payments'] as $payment)$paidCents+=(int)round(((float)$payment['amount'])*100);
   if($paidCents!==$totalCents)throw ValidationException::withMessages(['payments'=>['Payment total must exactly match sale total.']]);
   $total=number_format($totalCents/100,2,'.','');$receipt='POS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
   $saleId=DB::table('sales')->insertGetId(['tenant_id'=>$tenantId,'client_sale_id'=>$data['client_sale_id'],'receipt_number'=>$receipt,'location_name'=>$data['location_name'],'cashier_id'=>$request->user()->id,'total'=>$total,'created_at'=>now(),'updated_at'=>now()]);
   foreach($lines as $line){
    DB::table('sale_items')->insert(['tenant_id'=>$tenantId,'sale_id'=>$saleId,'product_id'=>$line['product']->id,'quantity'=>$line['quantity'],'unit_price'=>$line['unit_price'],'line_total'=>$line['line_total'],'created_at'=>now(),'updated_at'=>now()]);
    if($line['product']->track_stock)DB::table('inventory_ledger')->insert(['tenant_id'=>$tenantId,'product_id'=>$line['product']->id,'location_name'=>$data['location_name'],'movement_type'=>'sale','quantity_delta'=>-$line['quantity'],'reference_type'=>'sale','reference_id'=>$saleId,'reason'=>'POS sale '.$receipt,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
   }
   foreach($data['payments'] as $payment)DB::table('payments')->insert(['tenant_id'=>$tenantId,'sale_id'=>$saleId,'method'=>$payment['method'],'amount'=>number_format(((int)round(((float)$payment['amount'])*100))/100,2,'.',''),'created_at'=>now(),'updated_at'=>now()]);
   return ['id'=>$saleId,'receipt_number'=>$receipt,'total'=>$total,'replayed'=>false];
  },3);
  return response()->json($result,$result['replayed']?200:201);
 }
}