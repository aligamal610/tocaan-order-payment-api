<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method', 40);
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('gateway_reference')->nullable()->unique();
            $table->json('gateway_metadata')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
