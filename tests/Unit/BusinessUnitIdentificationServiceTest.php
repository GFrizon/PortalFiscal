<?php

namespace Tests\Unit;

use App\Models\BusinessUnit;
use App\Services\BusinessUnitIdentificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessUnitIdentificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_identifies_unit_by_recipient_cnpj(): void
    {
        $unit = BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'cnpj' => '91967067000155',
        ]);

        $identified = app(BusinessUnitIdentificationService::class)->identify([
            'recipient_cnpj' => '91.967.067/0001-55',
        ]);

        $this->assertTrue($unit->is($identified));
    }

    public function test_it_identifies_only_business_unit_cnpj_that_is_not_the_issuer(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF SC',
            'cnpj' => '12345678000195',
        ]);
        $rs = BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'cnpj' => '91967067000155',
        ]);

        $identified = app(BusinessUnitIdentificationService::class)->identify([
            'issuer_cnpj' => '12345678000195',
            'recipient_cnpj' => null,
            'cnpjs' => ['12345678000195', '91967067000155'],
        ]);

        $this->assertTrue($rs->is($identified));
    }

    public function test_it_identifies_unit_by_unique_recipient_name_when_cnpj_is_missing(): void
    {
        $unit = BusinessUnit::factory()->create([
            'name' => 'FIBRACAMPO',
            'legal_name' => 'FIBRACAMPO COMERCIO DE FIBRAS LTDA',
            'cnpj' => '11222333000181',
        ]);

        $identified = app(BusinessUnitIdentificationService::class)->identify([
            'recipient_cnpj' => null,
            'recipient_legal_name' => 'Fibracampo Comercio de Fibras Ltda',
            'cnpjs' => [],
        ]);

        $this->assertTrue($unit->is($identified));
    }

    public function test_it_does_not_identify_issuer_unit_as_recipient_when_it_is_the_only_unit_cnpj(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF SC',
            'cnpj' => '12345678000195',
        ]);

        $identified = app(BusinessUnitIdentificationService::class)->identify([
            'issuer_cnpj' => '12345678000195',
            'recipient_cnpj' => null,
            'cnpjs' => ['12345678000195'],
        ]);

        $this->assertNull($identified);
    }
}
