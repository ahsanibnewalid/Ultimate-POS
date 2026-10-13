<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_status', 24)->default('inactive');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('stripe_subscription_id', 120)->nullable()->unique();
        });

        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('transaction_id')->unique();
            $table->string('provider', 24);
            $table->string('plan', 40);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending');
            $table->string('provider_reference', 160)->nullable()->unique();
            $table->string('provider_subscription_id', 160)->nullable()->index();
            $table->text('provider_metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn(['billing_status', 'subscription_ends_at', 'stripe_subscription_id']);
        });
    }
};
