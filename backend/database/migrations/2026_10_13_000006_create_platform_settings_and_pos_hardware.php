<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id(); $table->string('key', 120)->unique(); $table->text('value');
            $table->unsignedBigInteger('updated_by')->nullable(); $table->timestamps();
        });
        Schema::create('platform_setting_audits', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('setting_key', 120); $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent(); $table->index(['setting_key', 'created_at']);
        });
        Schema::create('tenant_pos_settings', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('printer_mode', 24)->default('browser'); $table->unsignedTinyInteger('paper_width_mm')->default(80);
            $table->boolean('barcode_scanner_enabled')->default(true); $table->boolean('cash_drawer_enabled')->default(false);
            $table->string('terminal_mode', 24)->default('standalone');
            $table->string('receipt_footer', 300)->default('Thank you for your purchase!'); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tenant_pos_settings');
        Schema::dropIfExists('platform_setting_audits');
        Schema::dropIfExists('platform_settings');
    }
};
