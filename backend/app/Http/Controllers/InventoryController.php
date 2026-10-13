<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class InventoryController {
 public function index(Request $request) {
  $tenantId=(int)$request->user()->tenant_id;
  $products=Product::where('tenant_id',$tenantId)->where('active',true)->orderBy('name')->get();
  $balances=DB::table('inventory_ledger')->where('tenant_id',$tenantId)->select('product_id')->selectRaw('SUM(quantity_delta) as on_hand')->groupBy('product_id')->get()->keyBy('product_id');
  return response()->json(['data'=>$products->map(fn($p)=>['product'=>$p,'on_hand'=>(int)($balances->get($p->id)->on_hand??0)])]);
 }
 public function adjust(Request $request) {
  abort_unless(in_array($request->user()->role,['admin','manager'],true),403,'Only managers can adjust stock.');
  $data=$request->validate(['product_id'=>['required','integer'],'location_name'=>['required','string','max:120'],'quantity_delta'=>['required','integer','not_in:0','min:-1000000','max:1000000'],'reason'=>['required','string','min:3','max:240']]);
  $tenantId=(int)$request->user()->tenant_id;
  $result=DB::transaction(function()use($data,$tenantId,$request){
   $product=Product::where('tenant_id',$tenantId)->where('active',true)->lockForUpdate()->findOrFail($data['product_id']);
   abort_unless($product->track_stock,422,'Stock tracking is disabled for this product.');
   $onHand=(int)DB::table('inventory_ledger')->where('tenant_id',$tenantId)->where('product_id',$product->id)->where('location_name',$data['location_name'])->sum('quantity_delta');
   $newBalance=$onHand+(int)$data['quantity_delta']; abort_if($newBalance<0,422,'Stock adjustment cannot make inventory negative.');
   DB::table('inventory_ledger')->insert(['tenant_id'=>$tenantId,'product_id'=>$product->id,'location_name'=>$data['location_name'],'movement_type'=>'adjustment','quantity_delta'=>$data['quantity_delta'],'reference_type'=>'manual_adjustment','reference_id'=>0,'reason'=>$data['reason'],'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
   return ['product_id'=>$product->id,'on_hand'=>$newBalance,'reason'=>$data['reason']];
  },3);
  return response()->json($result,201);
 }
}