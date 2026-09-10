<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class HistoricalInvoicePdfExportService
{
    public function export(string $fromDate, string $toDate, ?string $batch = null): array
    {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();
        $batchName = Str::slug($batch ?: 'invoices-' . $from->format('Ymd') . '-to-' . $to->format('Ymd'));
        $directory = "invoice-exports/{$batchName}";

        Storage::disk('public')->deleteDirectory($directory);
        Storage::disk('public')->makeDirectory($directory);

        $summary = [
            'batch' => $batchName,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'processed' => 0,
            'exported' => 0,
            'failed' => 0,
            'zip_path' => null,
        ];

        Order::query()
            ->with(['user', 'orderProducts.product'])
            ->whereNotNull('order_serial_no')
            ->whereBetween('order_datetime', [$from, $to])
            ->whereNotIn('status', [OrderStatus::CANCELED, OrderStatus::REJECTED])
            ->orderBy('order_datetime')
            ->chunkById(100, function ($orders) use (&$summary, $directory) {
                foreach ($orders as $order) {
                    $summary['processed']++;

                    try {
                        $fileName = sprintf(
                            '%s-%d-%s.pdf',
                            $order->order_serial_no ?: 'invoice',
                            $order->id,
                            Str::slug($order->user?->name ?: 'customer')
                        );

                        Storage::disk('public')->put(
                            "{$directory}/{$fileName}",
                            app(InvoicePdfRenderer::class)->render($order)
                        );
                        $summary['exported']++;
                    } catch (\Throwable) {
                        $summary['failed']++;
                    }
                }
            });

        $summary['zip_path'] = $this->createZip($batchName);

        return $summary;
    }

    private function createZip(string $batchName): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }

        $directory = storage_path("app/public/invoice-exports/{$batchName}");
        $zipPath = storage_path("app/public/invoice-exports/{$batchName}.zip");
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach (scandir($directory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..' && is_file("{$directory}/{$file}")) {
                $zip->addFile("{$directory}/{$file}", $file);
            }
        }

        $zip->close();

        return "storage/invoice-exports/{$batchName}.zip";
    }
}
