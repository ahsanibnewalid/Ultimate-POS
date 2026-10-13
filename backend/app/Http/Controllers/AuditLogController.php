<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AuditLogController {
 public function index(Request $request) {
  abort_unless($request->user()->role==='admin',403,'Only workspace administrators can view the activity log.');
  $logs=DB::table('audit_logs as a')->leftJoin('users as u','u.id','=','a.actor_id')->where('a.tenant_id',$request->user()->tenant_id)
   ->orderByDesc('a.id')->limit(100)->get(['a.id','a.action','a.entity_type','a.entity_id','a.details','a.ip_address','a.created_at','u.name as actor_name']);
  return response()->json(['data'=>$logs]);
 }
}