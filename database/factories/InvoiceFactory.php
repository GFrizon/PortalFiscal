<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'protocol' => 'NF-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'submitted_by' => User::factory(),
            'business_unit_id' => BusinessUnit::factory(),
            'purchase_order_number' => fake()->numerify('######'),
            'invoice_number' => fake()->numerify('########'),
            'issuer_cnpj' => fake()->numerify('##############'),
            'issuer_legal_name' => fake()->company(),
            'recipient_cnpj' => fake()->numerify('##############'),
            'recipient_legal_name' => fake()->company(),
            'due_date' => now()->addDays(15),
            'arrival_date' => now(),
            'sent_at' => now(),
            'user_notes' => null,
            'fiscal_notes' => null,
            'pdf_path' => 'notas/'.now()->format('Y/m').'/exemplo.pdf',
            'original_pdf_name' => 'exemplo.pdf',
            'original_file_size' => 1024,
            'file_size' => 1024,
            'pdf_sha256' => hash('sha256', fake()->uuid()),
            'pdf_optimized' => false,
            'pdf_processed_at' => now(),
            'status' => InvoiceStatus::AwaitingReview,
        ];
    }
}
