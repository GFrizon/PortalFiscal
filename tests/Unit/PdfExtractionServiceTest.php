<?php

namespace Tests\Unit;

use App\Models\BusinessUnit;
use App\Services\AiDocumentExtractionService;
use App\Services\PdfExtractionService;
use App\Services\PdfOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Document;
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

    public function test_it_prefers_destination_business_unit_when_issuer_is_also_a_business_unit(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF SC',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '54163230000109',
        ]);

        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "DANFE Documento Auxiliar da Nota Fiscal Eletronica\n".
            "EMITENTE\n".
            "BAKOF PLASTICOS LTDA\n".
            "CNPJ 54.163.230/0001-09\n".
            "DESTINATARIO / REMETENTE\n".
            "BAKOF PLASTICOS LTDA\n".
            "CNPJ 91.967.067/0001-55\n".
            "NF-e N 000.014.317 SERIE 1"
        );

        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('54163230000109', $result['issuer_cnpj']);
    }

    public function test_it_extracts_nfe_number_from_access_key(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'CHAVE DE ACESSO 3526 0754 1632 3000 0109 5500 4000 0314 2614 4399 1849'
        );

        $this->assertSame('31426', $result['invoice_number']);
        $this->assertSame('35260754163230000109550040000314261443991849', $result['invoice_access_key']);
    }

    public function test_it_ignores_protocol_digits_before_nfe_access_key(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "PROTOCOLO DE AUTORIZACAO DE USO\n".
            "13526299658726/07/2026 17:10:56\n".
            "CHAVE DE ACESSO\n".
            "3526 0705 4625 4300 0225 5500 2000 7754 5915 9450 6574\n".
            "Nota fiscal de retorno simbolico n 775458, emitida em 26/07/2026, serie 2."
        );

        $this->assertSame('775459', $result['invoice_number']);
        $this->assertSame('35260705462543000225550020007754591594506574', $result['invoice_access_key']);
    }

    public function test_it_prefers_printed_issuer_cnpj_when_access_key_cnpj_conflicts_with_pdf_cnpj(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "DESTINATARIO / REMETENTE\n".
            "BAKOF PLASTICOS LTDA EM RECUPERACAO JUDICIAL\n".
            "CNPJ/CPF 91.967.067/0001-55\n".
            "CHAVE ACESSO\n".
            "43260887958674000181558900684543221163137974\n".
            "NATUREZA DA OPERACAO PROTOCOLO DE AUTORIZACAO DE USO\n".
            "CPF/CNPJ Venda de Mercadoria 490064744 243260354293551 - 11/08/2026 16:02:26 46.349.446/0001-27\n".
            "KW MOTORES E USINAGENS LTDA"
        );

        $this->assertSame('46349446000127', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('43260887958674000181558900684543221163137974', $result['invoice_access_key']);
    }

    public function test_it_reads_nfe_when_issuer_cnpj_is_glued_to_following_digits(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "RECEBEMOS DE MERCADO LIVRE BRASIL LTDA OS PRODUTOS CONSTANTES DA NOTA FISCAL INDICADA AO LADO\n".
            "MERCADO LIVRE BRASIL LTDA\n".
            "DANFE Documento Auxiliar da Nota Fiscal Eletronica\n".
            "1: Saida\n".
            "www.nfe.fazenda.gov.br/portal ou no site da Sefaz Autorizadora\n".
            "CHAVE DE ACESSO\n".
            "3126 0803 0073 3100 1032 5500 1147 1730 0712 3457 3597\n".
            "NATUREZA DA OPERACAO\n".
            "INSCRICAO ESTADUAL INSC. ESTADUAL DO SUBST. TRIBUTARIO CNPJ\n".
            "0038450760305 03.007.331/0010-329000095870\n".
            "DATA DA EMISSAO\n".
            "C.N.P.J / C.P.F.\n".
            "NOME/RAZAO SOCIAL\n".
            "BAKOF PLASTICOS LTDA EM RECUPERACAO JUDICIAL 91.967.067/0001-55\n".
            "DESTINATARIO / REMETENTE\n".
            "DADOS ADICIONAIS\n".
            "Enviado diretamente do deposito temporario - operador logistico: EBAZAR.COM.BR LTDA, Cnpj: 03007331012077"
        );

        $this->assertContains('03007331001032', $result['cnpjs']);
        $this->assertSame('03007331001032', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('147173007', $result['invoice_number']);
        $this->assertSame('MERCADO LIVRE BRASIL LTDA', $result['issuer_legal_name']);
    }

    public function test_it_extracts_cte_number_from_valid_access_key(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'CHAVE DE ACESSO CT-e 3526.0705.4625.4300.0225.5700.2000.1234.5912.3456.7893'
        );

        $this->assertSame('123459', $result['invoice_number']);
        $this->assertSame('35260705462543000225570020001234591234567893', $result['invoice_access_key']);
    }

    public function test_it_extracts_cte_issuer_legal_name_near_cnpj(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "DACTE Documento Auxiliar do Conhecimento de Transporte Eletronico\n".
            "REMETENTE\n".
            "AUZEC COMERCIO DE EQUIPAMENTOS LTDA\n".
            "CNPJ/CPF 05.462.543/0002-25\n".
            "DESTINATARIO\n".
            "BAKOF PLASTICOS LTDA\n".
            "CNPJ 91.967.067/0001-55\n".
            "CHAVE DE ACESSO CT-e 3526.0705.4625.4300.0225.5700.2000.1234.5912.3456.7893"
        );

        $this->assertSame('05462543000225', $result['issuer_cnpj']);
        $this->assertSame('AUZEC COMERCIO DE EQUIPAMENTOS LTDA', $result['issuer_legal_name']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('BAKOF PLASTICOS LTDA', $result['recipient_legal_name']);
    }

    public function test_it_extracts_nfcom_issuer_from_access_key(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "Telefonica Brasil S.A.\n".
            "Av. Carlos Gomes, 258 - Andares 14, 15 e 16\n".
            "CEP 90480-000 - Porto Alegre - RS\n".
            "I.E.: 0962949477\n".
            "CNPJ Matriz :02.558.157/0001-62\n".
            "CNPJ Filial   :02.558.157/0017-20\n".
            "Documento Auxiliar da Nota Fiscal de Fatura de Servico de Comunicacao Eletronica\n".
            "NOME: BAKOF PLASTICOS LTDA\n".
            "Nº NFCOM 1130383 - SERIE 004/DATA DE EMISSAO: 28/07/2026\n".
            "Chave de acesso: 43260702558157001720620040011303831005586160\n".
            "CPF/CNPJ: 91.967.067/0001-55"
        );

        $this->assertSame('1130383', $result['invoice_number']);
        $this->assertSame('43260702558157001720620040011303831005586160', $result['invoice_access_key']);
        $this->assertSame('02558157001720', $result['issuer_cnpj']);
        $this->assertSame('TELEFONICA BRASIL S.A.', $result['issuer_legal_name']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('BAKOF PLASTICOS LTDA', $result['recipient_legal_name']);
    }

    public function test_it_rejects_invalid_fiscal_access_key_check_digit(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'CHAVE DE ACESSO 3526 0705 4625 4300 0225 5500 2000 7754 5915 9450 6570'
        );

        $this->assertNull($result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_falls_back_to_danfe_number_when_access_key_is_not_readable(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "DANFE Documento Auxiliar da Nota Fiscal Eletronica\n".
            "NF-e Nº 000.775.459 SERIE 002 Folha 1 de 1\n".
            "CHAVE DE ACESSO ilegivel no PDF"
        );

        $this->assertSame('775459', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_does_not_use_symbolic_return_invoice_as_main_number(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'Dados adicionais: Nota fiscal de retorno simbolico n 775458, emitida em 26/07/2026, serie 2.'
        );

        $this->assertNull($result['invoice_number']);
    }

    public function test_it_does_not_use_invoice_installment_or_rps_as_invoice_number(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "FATURA/DUPLICATA 001 vencimento 30/07/2026 valor 333,33\n".
            "Numero do RPS 999 Data de emissao 29/07/2026"
        );

        $this->assertNull($result['invoice_number']);
    }

    public function test_it_extracts_nfse_number_from_label(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'NOTA FISCAL DE SERVICO ELETRONICA - NFS-e Numero da NFS-e 361 Data e Hora da Emissao'
        );

        $this->assertSame('361', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_prefers_nfse_number_over_verification_code(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            'NFS-e Codigo de Verificacao 938201 Numero da NFS-e 44449 Data e Hora da Emissao'
        );

        $this->assertSame('44449', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_extracts_nfse_number_from_municipal_note_label(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "NOTA FISCAL DE SERVICO ELETRONICA\n".
            "Codigo de Verificacao 938201\n".
            "Numero da Nota 775459\n".
            "Numero do RPS 123"
        );

        $this->assertSame('775459', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_extracts_nfse_number_when_label_is_split_by_pdf_spacing(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "NFS-e\n".
            "N.\n".
            "000031144\n".
            "Serie 0 Codigo de Verificacao 1610855857"
        );

        $this->assertSame('31144', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_extracts_nfse_number_from_service_invoice_block(): void
    {
        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "Prefeitura Municipal\n".
            "Nota Fiscal de Servico Eletronica NFS-e\n".
            "Data de emissao 03/08/2026 Numero 47933 Competencia 08/2026\n".
            "Codigo de verificacao 456789"
        );

        $this->assertSame('47933', $result['invoice_number']);
        $this->assertNull($result['invoice_access_key']);
    }

    public function test_it_extracts_nfse_number_from_danfse_column_header_layout(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $service = new PdfExtractionService(new Parser());

        $result = $service->extractFromText(
            "DANFSe v1.0\n".
            "Documento auxiliar da NFS-e\n".
            "Chave de acesso da NFS-e\n".
            "35290051202351877000900000000059270326064760853336\n".
            "Numero da NFS-e Competencia da NFS-e Data e Hora da emissao da NFS-e\n".
            "592703 21/06/2026 00:06 21/06/2026 03:06\n".
            "EMITENTE DA NFS-e\n".
            "Prestador de servico\n".
            "Nome / Nome empresarial\n".
            "LWSA S A\n".
            "CNPJ / CPF / NIF\n".
            "02.351.877/0009-00\n".
            "TOMADOR DO SERVICO\n".
            "Nome / Nome Empresarial\n".
            "BAKOF PLASTICOS LTDA\n".
            "CNPJ / CPF / NIF\n".
            "91.967.067/0001-55"
        );

        $this->assertSame('592703', $result['invoice_number']);
        $this->assertSame('02351877000900', $result['issuer_cnpj']);
        $this->assertSame('LWSA S A', $result['issuer_legal_name']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
        $this->assertSame('BAKOF PLASTICOS LTDA', $result['recipient_legal_name']);
    }

    public function test_it_discards_suspicious_legal_name_candidates(): void
    {
        $service = new PdfExtractionService(new Parser());

        $this->assertTrue($service->isSuspiciousLegalName('0 - ENTRADA 432'));
        $this->assertTrue($service->isSuspiciousLegalName('0 - SAIDA 432'));
    }

    public function test_it_discards_formatted_cnpj_as_legal_name_candidate(): void
    {
        $service = new PdfExtractionService(new Parser());

        $this->assertTrue($service->isSuspiciousLegalName('91.967.067/0001-55'));
        $this->assertTrue($service->isSuspiciousLegalName('12345678000195'));
    }

    public function test_it_uses_ai_fallback_when_local_extraction_is_missing_critical_fields(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $document = \Mockery::mock(Document::class);
        $document->shouldReceive('getText')->once()->andReturn("Fornecedor ilegivel\nTomador BAKOF PLASTICOS LTDA\nCNPJ 91.967.067/0001-55");

        $parser = \Mockery::mock(Parser::class);
        $parser->shouldReceive('parseFile')->once()->with('broken.pdf')->andReturn($document);

        $ai = new class extends AiDocumentExtractionService
        {
            public function extract(string $absolutePath): array
            {
                return [
                    'success' => true,
                    'invoice_number' => '1261180',
                    'invoice_access_key' => null,
                    'issuer_cnpj' => '12345678000195',
                    'issuer_legal_name' => 'PAULO SERVICOS LTDA',
                    'recipient_cnpj' => '91967067000155',
                    'recipient_legal_name' => 'BAKOF PLASTICOS LTDA',
                    'error' => null,
                    'source' => 'ai',
                ];
            }
        };

        $service = new PdfExtractionService($parser, null, $ai);

        $result = $service->extract('broken.pdf');

        $this->assertTrue($result['success']);
        $this->assertSame('text+ai', $result['source']);
        $this->assertSame('1261180', $result['invoice_number']);
        $this->assertSame('12345678000195', $result['issuer_cnpj']);
        $this->assertSame('PAULO SERVICOS LTDA', $result['issuer_legal_name']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
    }

    public function test_it_uses_ai_fallback_when_pdf_parser_returns_only_numeric_identifier(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $document = \Mockery::mock(Document::class);
        $document->shouldReceive('getText')->once()->andReturn('2026-178258701-01-001-04-4');

        $parser = \Mockery::mock(Parser::class);
        $parser->shouldReceive('parseFile')->once()->with('corsan.pdf')->andReturn($document);

        $ai = new class extends AiDocumentExtractionService
        {
            public function extract(string $absolutePath): array
            {
                return [
                    'success' => true,
                    'invoice_number' => '985315',
                    'invoice_access_key' => null,
                    'issuer_cnpj' => '12345678000195',
                    'issuer_legal_name' => 'COMPANHIA RIOGRANDENSE DE SANEAMENTO',
                    'recipient_cnpj' => '91967067000155',
                    'recipient_legal_name' => 'BAKOF PLASTICOS LTDA',
                    'error' => null,
                    'source' => 'ai',
                ];
            }
        };

        $service = new PdfExtractionService($parser, null, $ai);

        $result = $service->extract('corsan.pdf');

        $this->assertTrue($result['success']);
        $this->assertSame('blank+ai', $result['source']);
        $this->assertSame('985315', $result['invoice_number']);
        $this->assertSame('12345678000195', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
    }

    public function test_it_uses_ocr_when_pdf_has_no_searchable_text(): void
    {
        BusinessUnit::factory()->create([
            'name' => 'BAKOF RS',
            'legal_name' => 'BAKOF PLASTICOS LTDA',
            'cnpj' => '91967067000155',
        ]);

        $document = \Mockery::mock(Document::class);
        $document->shouldReceive('getText')->once()->andReturn('');

        $parser = \Mockery::mock(Parser::class);
        $parser->shouldReceive('parseFile')->once()->with('scanned.pdf')->andReturn($document);

        $ocr = new class extends PdfOcrService
        {
            public function extract(string $absolutePath): ?string
            {
                return "NOTA FISCAL DE SERVICO ELETRONICA\n".
                    "Numero da Nota 1261180\n".
                    "PAULO SERVICOS LTDA\n".
                    "CNPJ 12.345.678/0001-95\n".
                    "Tomador BAKOF PLASTICOS LTDA CNPJ 91.967.067/0001-55";
            }
        };

        $service = new PdfExtractionService($parser, $ocr);

        $result = $service->extract('scanned.pdf');

        $this->assertTrue($result['success']);
        $this->assertSame('ocr', $result['source']);
        $this->assertSame('1261180', $result['invoice_number']);
        $this->assertSame('12345678000195', $result['issuer_cnpj']);
        $this->assertSame('91967067000155', $result['recipient_cnpj']);
    }
}
