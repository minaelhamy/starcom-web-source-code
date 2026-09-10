<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoicePdfRenderer
{
    public function render(Order $order): string
    {
        $temporaryDirectory = storage_path('app/mpdf');
        File::ensureDirectoryExists($temporaryDirectory);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'tempDir' => $temporaryDirectory,
        ]);

        $pdf->SetTitle('فاتورة ' . $order->order_serial_no);
        $pdf->SetDirectionality('rtl');
        $pdf->autoArabic = true;
        $pdf->autoScriptToLang = true;
        $pdf->autoLangToFont = true;
        $pdf->WriteHTML(view('pdf.bulk-pos-invoice', [
            'order' => $order,
            'customer' => $order->user,
            'items' => $order->orderProducts,
        ])->render());

        return $pdf->Output('', Destination::STRING_RETURN);
    }
}
