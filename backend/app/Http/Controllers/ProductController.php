<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProductController {
 public function index(Request $request) {
  $query=Product::where('tenant_id',$request->user()->tenant_id);
  if(!$request->boolean('include_inactive'))$query->where('active',true);
  if($search=trim((string)$request->query('search',''))){$query->where(function($q)use($search){$q->where('name','like',"%{$search}%")->orWhere('sku','like',"%{$search}%")->orWhere('barcode','like',"%{$search}%");});}
  return $query->orderBy('name')->paginate(min(100,max(1,(int)$request->query('per_page',50))));
 }
 public function store(Request $request) {
  abort_unless(in_array($request->user()->role,['admin','manager'],true),403,'Only managers can create products.');
  $tenantId=(int)$request->user()->tenant_id;
  $data=$request->validate(['name'=>['required','string','max:180'],'sku'=>['required','string','max:80',Rule::unique('products')->where('tenant_id',$tenantId)],'barcode'=>['nullable','string','max:120',Rule::unique('products')->where('tenant_id',$tenantId)],'price'=>['required','numeric','decimal:0,2','min:0','max:999999999'],'cost'=>['required','numeric','decimal:0,2','min:0','max:999999999'],'track_stock'=>['sometimes','boolean']]);
  $product=Product::create($data+['tenant_id'=>$tenantId,'track_stock'=>$data['track_stock']??true,'active'=>true]);
  return response()->json($product,201);
 }
 public function update(Request $request,int $product) {
  abort_unless(in_array($request->user()->role,['admin','manager'],true),403,'Only managers can edit products.');
  $tenantId=(int)$request->user()->tenant_id;$record=Product::where('tenant_id',$tenantId)->findOrFail($product);
  $data=$request->validate(['name'=>['sometimes','required','string','max:180'],'sku'=>['sometimes','required','string','max:80',Rule::unique('products')->where('tenant_id',$tenantId)->ignore($record->id)],'barcode'=>['sometimes','nullable','string','max:120',Rule::unique('products')->where('tenant_id',$tenantId)->ignore($record->id)],'price'=>['sometimes','required','numeric','decimal:0,2','min:0','max:999999999'],'cost'=>['sometimes','required','numeric','decimal:0,2','min:0','max:999999999'],'track_stock'=>['sometimes','boolean'],'active'=>['sometimes','boolean']]);
  $record->update($data);return response()->json($record->fresh());
 }
 public function destroy(Request $request,int $product) {
  abort_unless(in_array($request->user()->role,['admin','manager'],true),403,'Only managers can archive products.');
  $record=Product::where('tenant_id',$request->user()->tenant_id)->findOrFail($product);$record->update(['active'=>false]);return response()->json(['message'=>'Product archived.']);
 }
}