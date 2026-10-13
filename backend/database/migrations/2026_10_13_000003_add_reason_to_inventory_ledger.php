<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('inventory_ledger',function(Blueprint $table){$table->string('reason',240)->nullable();}); }
 public function down(): void { Schema::table('inventory_ledger',function(Blueprint $table){$table->dropColumn('reason');}); }
};