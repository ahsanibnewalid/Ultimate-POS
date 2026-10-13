<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('audit_logs',function(Blueprint $table){
   $table->id();$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();$table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('action',80);$table->string('entity_type',80);$table->unsignedBigInteger('entity_id')->nullable();$table->text('details')->nullable();
   $table->string('ip_address',45)->nullable();$table->string('user_agent',500)->nullable();$table->timestamps();$table->index(['tenant_id','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('audit_logs'); }
};