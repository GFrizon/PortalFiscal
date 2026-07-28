<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('purchase_order_number')->index();
            $table->boolean('exists')->default(false);
            $table->string('status')->nullable();
            $table->string('supplier_cnpj', 14)->nullable();
            $table->string('supplier_name')->nullable();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_checks');
    }
};
