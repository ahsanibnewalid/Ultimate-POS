<?php
namespace App\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AuditLogger {
 public static function record(Request $request,string $action,string $entityType,?int $entityId,array $details=[]): void {
  $user=$request->user();
  if(!$user||!$user->tenant_id)return;
  DB::table('audit_logs')->insert([
   'tenant_id'=>(int)$user->tenant_id,'actor_id'=>$user->id,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,
   'details'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'ip_address'=>$request->ip(),
   'user_agent'=>substr((string)$request->userAgent(),0,500),'created_at'=>now(),'updated_at'=>now()
  ]);
 }
}