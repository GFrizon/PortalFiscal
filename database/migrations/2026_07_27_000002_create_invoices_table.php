<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('protocol')->unique();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('business_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_order_number')->nullable()->index();
            $table->string('invoice_number')->nullable()->index();
            $table->string('issuer_cnpj', 14)->nullable()->index();
            $table->string('issuer_legal_name')->nullable();
            $table->string('recipient_cnpj', 14)->nullable()->index();
            $table->string('recipient_legal_name')->nullable();
            $table->date('due_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('user_notes')->nullable();
            $table->text('fiscal_notes')->nullable();
            $table->string('pdf_path');
            $table->string('original_pdf_name');
            $table->unsignedBigInteger('file_size');
            $table->string('status', 40)->default('awaiting_review')->index();
            $table->foreignId('fiscal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('launched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
