<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class CustomerController {
 public function index(Request $request) {
  $query=DB::table('customers')->where('tenant_id',$request->user()->tenant_id);
  if($search=trim((string)$request->query('search',''))){$query->where(function($q)use($search){$q->where('name','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhere('email','like',"%{$search}%");});}
  return response()->json(['data'=>$query->orderBy('name')->limit(200)->get()]);
 }
 public function store(Request $request) {
  $tenantId=(int)$request->user()->tenant_id;
  $data=$request->validate(['name'=>['required','string','max:160'],'phone'=>['nullable','string','max:40',Rule::unique('customers')->where('tenant_id',$tenantId)],'email'=>['nullable','email','max:255'],'notes'=>['nullable','string','max:2000']]);
  $id=DB::table('customers')->insertGetId($data+['tenant_id'=>$tenantId,'created_at'=>now(),'updated_at'=>now()]);
  return response()->json(DB::table('customers')->where('tenant_id',$tenantId)->where('id',$id)->first(),201);
 }
 public function update(Request $request,int $customer) {
  $tenantId=(int)$request->user()->tenant_id;
  abort_unless(DB::table('customers')->where('tenant_id',$tenantId)->where('id',$customer)->exists(),404);
  $data=$request->validate(['name'=>['sometimes','required','string','max:160'],'phone'=>['sometimes','nullable','string','max:40',Rule::unique('customers')->where('tenant_id',$tenantId)->ignore($customer)],'email'=>['sometimes','nullable','email','max:255'],'notes'=>['sometimes','nullable','string','max:2000']]);
  DB::table('customers')->where('tenant_id',$tenantId)->where('id',$customer)->update($data+['updated_at'=>now()]);
  return response()->json(DB::table('customers')->where('tenant_id',$tenantId)->where('id',$customer)->first());
 }
}