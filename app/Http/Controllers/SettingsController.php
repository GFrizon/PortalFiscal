<?php

namespace App\Http\Controllers;

use App\Services\InvoiceStorageReportService;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(InvoiceStorageReportService $storageReportService): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $storage = $storageReportService->summary();

        return view('admin.settings.index', [
            'storage' => $storage,
            'storageDisplay' => [
                'database_stored' => $storageReportService->humanBytes($storage['database_stored_bytes']),
                'database_original' => $storageReportService->humanBytes($storage['database_original_bytes']),
                'database_saved' => $storageReportService->humanBytes($storage['database_saved_bytes']),
                'disk' => $storageReportService->humanBytes($storage['disk_bytes']),
                'tmp' => $storageReportService->humanBytes($storage['tmp_bytes']),
                'average' => $storageReportService->humanBytes($storage['average_pdf_bytes']),
            ],
        ]);
    }
}
