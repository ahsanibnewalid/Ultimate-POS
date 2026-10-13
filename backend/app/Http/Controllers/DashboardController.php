<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DashboardController {
 public function __invoke(Request $request) {
  $tenantId=(int)$request->user()->tenant_id; $today=now()->toDateString();
  $todaySales=DB::table('sales')->where('tenant_id',$tenantId)->whereDate('created_at',$today);
  $daily=DB::table('sales')->selectRaw('DATE(created_at) as day, SUM(total) as total, COUNT(*) as count')->where('tenant_id',$tenantId)->where('created_at','>=',now()->subDays(6)->startOfDay())->groupBy('day')->orderBy('day')->get();
  $stock=DB::table('products as p')->where('p.tenant_id',$tenantId)->where('p.active',true)->where('p.track_stock',true)->select('p.id','p.name','p.sku')->selectSub(function($q)use($tenantId){$q->from('inventory_ledger')->selectRaw('COALESCE(SUM(quantity_delta),0)')->whereColumn('product_id','p.id')->where('tenant_id',$tenantId);},'on_hand')->get();
  return response()->json([
   'today'=>['sales_total'=>(float)(clone $todaySales)->sum('total'),'sales_count'=>(int)(clone $todaySales)->count()],
   'products_count'=>DB::table('products')->where('tenant_id',$tenantId)->where('active',true)->count(),
   'customers_count'=>DB::table('customers')->where('tenant_id',$tenantId)->count(),
   'low_stock_count'=>$stock->filter(fn($p)=>(int)$p->on_hand<=5)->count(),
   'low_stock'=>$stock->filter(fn($p)=>(int)$p->on_hand<=5)->take(10)->values(),
   'daily_sales'=>$daily,'recent_sales'=>DB::table('sales')->where('tenant_id',$tenantId)->orderByDesc('id')->limit(8)->get(),'tenant'=>$request->user()->tenant
  ]);
 }
}