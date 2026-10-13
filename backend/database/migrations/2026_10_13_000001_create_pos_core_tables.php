<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('tenants',function(Blueprint $t){$t->id();$t->string('name',160);$t->string('slug',180)->unique();$t->char('currency',3)->default('BDT');$t->string('timezone',80)->default('Asia/Dhaka');$t->string('plan')->default('free');$t->timestamps();});
  Schema::create('users',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->string('name',120);$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('role')->default('cashier');$t->rememberToken();$t->timestamps();$t->index(['tenant_id','role']);});
  Schema::create('personal_access_tokens',function(Blueprint $t){$t->id();$t->morphs('tokenable');$t->string('name');$t->string('token',64)->unique();$t->text('abilities')->nullable();$t->timestamp('last_used_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();});
  Schema::create('products',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->string('name',180);$t->string('sku',80);$t->string('barcode',120)->nullable();$t->decimal('price',14,2);$t->decimal('cost',14,2)->default(0);$t->boolean('track_stock')->default(true);$t->boolean('active')->default(true);$t->timestamps();$t->unique(['tenant_id','sku']);$t->unique(['tenant_id','barcode']);});
  Schema::create('sales',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->uuid('client_sale_id');$t->string('receipt_number',40);$t->string('location_name',120);$t->foreignId('cashier_id')->constrained('users');$t->decimal('total',14,2);$t->timestamps();$t->unique(['tenant_id','client_sale_id']);$t->unique(['tenant_id','receipt_number']);});
  Schema::create('sale_items',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();$t->foreignId('product_id')->constrained('products');$t->unsignedInteger('quantity');$t->decimal('unit_price',14,2);$t->decimal('line_total',14,2);$t->timestamps();});
  Schema::create('payments',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();$t->string('method',20);$t->decimal('amount',14,2);$t->timestamps();});
  Schema::create('inventory_ledger',function(Blueprint $t){$t->id();$t->foreignId('tenant_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->constrained('products');$t->string('location_name',120);$t->string('movement_type',30);$t->integer('quantity_delta');$t->string('reference_type',30);$t->unsignedBigInteger('reference_id');$t->foreignId('created_by')->constrained('users');$t->timestamps();$t->index(['tenant_id','product_id','location_name']);$t->index(['reference_type','reference_id']);});
 }
 public function down(): void {Schema::dropIfExists('inventory_ledger');Schema::dropIfExists('payments');Schema::dropIfExists('sale_items');Schema::dropIfExists('sales');Schema::dropIfExists('products');Schema::dropIfExists('personal_access_tokens');Schema::dropIfExists('users');Schema::dropIfExists('tenants');}
};
