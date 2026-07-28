<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_file_size')->nullable()->after('original_pdf_name');
            $table->char('pdf_sha256', 64)->nullable()->after('file_size')->index();
            $table->boolean('pdf_optimized')->default(false)->after('pdf_sha256');
            $table->timestamp('pdf_processed_at')->nullable()->after('pdf_optimized');
        });

        DB::table('invoices')
            ->whereNull('original_file_size')
            ->update([
                'original_file_size' => DB::raw('file_size'),
                'pdf_processed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['pdf_sha256']);
            $table->dropColumn([
                'original_file_size',
                'pdf_sha256',
                'pdf_optimized',
                'pdf_processed_at',
            ]);
        });
    }
};
