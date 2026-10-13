<?php
namespace App\\Http\\Controllers;
use App\\Models\\Product;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;
class ProductController {
    public function index(Request $request) {
        return Product::where('tenant_id',$request->user()->tenant_id)->where('active',true)->orderBy('name')->paginate(50);
    }
    public function store(Request $request) {
        $data = $request->validate([
            'name'=>['required','string','max:180'],
            'sku'=>['required','string','max:80',Rule::unique('products')->where('tenant_id',$request->user()->tenant_id)],
            'barcode'=>['nullable','string','max:120'],
            'price'=>['required','numeric','min:0','max:999999999'],
            'cost'=>['required','numeric','min:0','max:999999999'],
            'track_stock'=>['sometimes','boolean'],
        ]);
        $product = Product::create($data + ['tenant_id'=>$request->user()->tenant_id,'track_stock'=>$data['track_stock']??true,'active'=>true]);
        return response()->json($product,201);
    }
}
