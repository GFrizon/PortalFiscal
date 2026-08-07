# Portal de Notas Fiscais - BAKOF

## Arquitetura inicial

- Backend: Laravel 12, PHP 8.2+, MySQL.
- Frontend: Laravel Blade e Bootstrap 5 via CDN.
- Autenticacao: sessao web padrao do Laravel, Password Broker e rate limit no login.
- Perfis: `admin`, `fiscal`, `user` em `App\Enums\UserRole`.
- Situacao de usuario/unidade: `active`, `inactive` em `App\Enums\UserStatus`.
- Status das notas: `awaiting_review`, `in_review`, `pending`, `launched`, `cancelled` em `App\Enums\InvoiceStatus`.

## Estrutura do banco

- `users`: usuarios, perfil, situacao e flag de troca obrigatoria de senha.
- `business_units`: unidades de negocio com CNPJ unico.
- `invoices`: dados principais da nota fiscal e caminho privado do PDF.
- `invoice_histories`: trilha de auditoria das acoes.
- `invoice_alerts`: divergencias e pendencias da nota.
- `purchase_order_checks`: resultado da consulta simulada ou futura API de ordem de compra.

## Instalar em cPanel

1. Envie os arquivos do projeto para uma pasta fora de `public_html`, por exemplo `portal-notas`.
2. Nao envie pastas temporarias de instalacao, cache local ou dependencias de desenvolvimento:
   - `_laravel_install`
   - `_laravel12_install`
   - `node_modules`
   - `storage/framework/cache/*`
   - `storage/framework/views/*`
   - `storage/logs/*.log`
3. Configure o dominio/subdominio para apontar para `portal-notas/public`.
   - O arquivo `public/.htaccess` ja redireciona as rotas para `public/index.php`.
   - Nunca aponte o dominio para a raiz do projeto.
4. No cPanel, crie um banco MySQL e um usuario com permissoes nesse banco.
5. Copie `.env.production.example` para `.env` e preencha:
   - `APP_URL`
   - `APP_KEY`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - configuracoes de e-mail
6. Execute:

```bash
php composer.phar install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

7. Ajuste permissoes de escrita para:

```bash
storage
bootstrap/cache
```

8. Configure o PHP da hospedagem com:
   - PHP 8.2 ou superior
   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `fileinfo`
   - `ctype`
   - `json`
   - `tokenizer`
   - `xml`

9. Para uploads de PDF, ajuste no PHP:
   - `upload_max_filesize`
   - `post_max_size`
   - `max_execution_time`
   - `memory_limit`

10. Configure o limite do app no `.env`:

```env
INVOICE_PDF_MAX_UPLOAD_KB=10240
```

11. Para compactacao opcional de PDFs, habilite somente se o servidor tiver Ghostscript:

```env
INVOICE_PDF_OPTIMIZATION_ENABLED=true
INVOICE_PDF_OPTIMIZATION_BINARY=/usr/bin/gs
INVOICE_PDF_OPTIMIZATION_QUALITY=/ebook
INVOICE_PDF_OPTIMIZATION_TIMEOUT=60
INVOICE_PDF_OPTIMIZATION_MIN_SAVINGS_PERCENT=8
```

Se o Ghostscript nao estiver disponivel, deixe `INVOICE_PDF_OPTIMIZATION_ENABLED=false`.

12. Para OCR local de PDFs escaneados, habilite somente se o servidor tiver `pdftoppm` e `tesseract`:

```env
PDF_OCR_ENABLED=true
PDF_OCR_PDFTOPPM_BINARY=/usr/bin/pdftoppm
PDF_OCR_TESSERACT_BINARY=/usr/bin/tesseract
PDF_OCR_LANGUAGE=por
PDF_OCR_TIMEOUT=60
PDF_OCR_MAX_PAGES=2
```

13. Para fallback opcional via OpenAI quando parser/OCR nao extrairem os campos criticos do PDF:

```env
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=45
OPENAI_PDF_DETAIL=low
```

Use `OPENAI_PDF_DETAIL=low` para menor custo. O app tenta leitura local primeiro e so chama a OpenAI quando faltarem campos criticos ou o nome extraido parecer inconsistente.

14. Teste os comandos de armazenamento:

```bash
php artisan invoices:storage-report
php artisan invoices:optimize-pdfs --force --dry-run --limit=25 --min-size-kb=300
php artisan invoices:cleanup-storage --dry-run --days=1
```

15. Se o dry-run estiver correto e o servidor tiver Ghostscript, compacte em lotes pequenos:

```bash
php artisan invoices:optimize-pdfs --force --limit=25 --min-size-kb=300
```

Use lotes pequenos no cPanel para evitar estourar CPU/memoria da hospedagem. O app so substitui o PDF quando a nova versao fica menor que o percentual minimo configurado.

16. Para limpar arquivos temporarios:

```bash
php artisan invoices:cleanup-storage --days=1
```

Para procurar PDFs orfaos, primeiro rode sempre em modo seguro:

```bash
php artisan invoices:cleanup-storage --orphans --dry-run
```

So remova orfaos sem `--dry-run` depois de conferir o resultado.

17. Configure Cron para tarefas agendadas, fila e manutencao com banco de dados:

```bash
* * * * * cd /home/usuario/portal-notas && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/usuario/portal-notas && php artisan queue:work --stop-when-empty --tries=3 --timeout=90 >> /dev/null 2>&1
*/30 * * * * cd /home/usuario/portal-notas && php artisan invoices:optimize-pdfs --force --limit=10 --min-size-kb=300 >> /dev/null 2>&1
0 2 * * * cd /home/usuario/portal-notas && php artisan invoices:cleanup-storage --days=1 >> /dev/null 2>&1
```

18. Configure backup recorrente de:
   - banco MySQL
   - pasta `storage/app/private`
   - arquivo `.env`

19. Para volume alto de PDFs, acompanhe mensalmente:
   - espaco usado em `storage/app/private/notas`
   - tamanho medio por PDF
   - crescimento da tabela `invoice_histories`
   - tempo medio de upload

## Acesso inicial

- E-mail: `admin@bakof.local`
- Senha temporaria: `Alterar123!`

Em producao, o sistema obriga a troca dessa senha no primeiro acesso.
