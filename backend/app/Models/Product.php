<?php
namespace App\\Models;
use Illuminate\\Database\\Eloquent\\Model;
class Product extends Model {
    protected $fillable = ['tenant_id','name','sku','barcode','price','cost','track_stock','active'];
    protected function casts(): array { return ['price'=>'decimal:2','cost'=>'decimal:2','track_stock'=>'boolean','active'=>'boolean']; }
}
