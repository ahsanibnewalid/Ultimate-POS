<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;
Route::get('/health',fn()=>response()->json(['status'=>'ok','service'=>'ultimate-pos-api']));
Route::post('/register',[AuthController::class,'register'])->middleware('throttle:5,1');
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::middleware(['auth:sanctum','tenant.context'])->group(function(){
 Route::post('/logout',[AuthController::class,'logout']);
 Route::get('/me',[AuthController::class,'me']);
 Route::get('/dashboard',DashboardController::class);
 Route::get('/audit-logs',[AuditLogController::class,'index']);
 Route::get('/products',[ProductController::class,'index']);
 Route::post('/products',[ProductController::class,'store']);
 Route::patch('/products/{product}',[ProductController::class,'update'])->whereNumber('product');
 Route::delete('/products/{product}',[ProductController::class,'destroy'])->whereNumber('product');
 Route::get('/inventory',[InventoryController::class,'index']);
 Route::post('/inventory/adjustments',[InventoryController::class,'adjust']);
 Route::get('/customers',[CustomerController::class,'index']);
 Route::post('/customers',[CustomerController::class,'store']);
 Route::patch('/customers/{customer}',[CustomerController::class,'update'])->whereNumber('customer');
 Route::get('/sales',[SaleController::class,'index']);
 Route::post('/sales',[SaleController::class,'store'])->middleware('throttle:60,1');
});