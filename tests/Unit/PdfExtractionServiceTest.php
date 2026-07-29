<?php

namespace Tests\Unit;

use App\Models\BusinessUnit;
use App\Services\PdfExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class PdfExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_identifies_business_unit_cnpj_with_irregular_pdf_spacing(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 004)',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "EMITENTE CNPJ 54.163.230/0001-09\nDESTINATARIO BAKOF CNPJ 91 . 967 . 067 / 0001 - 55\nNF 123456"
        );

        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
        $this->assertContains('91967067000155', $result['cnpjs']);
    }

    public function test_it_ignores_glued_pdf_numbers_that_are_not_valid_cnpjs(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 001)',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "INSCRICAO ESTADUAL CNPJ\n799828002110 54.163.230/0001-09\n".
            "PROTOCOLO 13526293365022/07/2026\n".
            "DESTINATARIO / REMETENTE BAKOF PLASTICOS LTDA 91.967.067/0001-55"
        );

        $this->assertSame(['54163230000109', '91967067000155'], $result['cnpjs']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
    }

    public function test_it_accepts_cnpj_with_irregular_separator_instead_of_slash(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 004)',
            'cnpj' => '91967067000317',
            'internal_code' => '004',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "Unidade: BAKOF PLASTICOS LTDA CNPJ.: 91.967.067.0003-17\nUnidade Negocio: 004"
        );

        $this->assertContains('91967067000317', $result['cnpjs']);
        $this->assertSame('91967067000317', $result['recipient_cnpj']);
    }

    public function test_it_identifies_business_unit_by_internal_code_when_cnpj_is_not_readable(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF PLASTICOS LTDA (UNIDADE 004)',
            'cnpj' => '91967067000317',
            'internal_code' => '004',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "Relatorio de conferencia\nUnidade Negocio: 004\nCNPJ ilegivel no arquivo"
        );

        $this->assertSame('91967067000317', $result['recipient_cnpj']);
    }

    public function test_it_extracts_nfe_number_from_access_key(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'CHAVE DE ACESSO 3526 0754 1632 3000 0109 5500 4000 0314 2614 4399 1849'
        );

        $this->assertSame('31426', $result['invoice_number']);
    }

    public function test_it_extracts_nfse_number_from_label(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'NOTA FISCAL DE SERVICO ELETRONICA - NFS-e Numero da NFS-e 361 Data e Hora da Emissao'
        );

        $this->assertSame('361', $result['invoice_number']);
    }
}
