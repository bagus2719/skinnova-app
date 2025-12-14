<?php

use Illuminate\Support\Facades\Route;
use App\Models\SalesInvoice;
use App\Models\DeliveryOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Resources\SalesInvoiceResource;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::redirect('/', '/admin');

Route::group(['prefix' => 'admin', 'as' => 'filament.admin.', 'middleware' => ['web', 'auth']], function () {
    // Rute Cetak Sales Invoice PDF
    Route::get('/sales-invoices/{record}/pdf', function (SalesInvoice $record) {
        // Pastikan relasi items dimuat
        $record->load(['salesOrder.customer', 'items.product']); 
        
        // Meneruskan nilai TAX_RATE ke View
        $pdf = Pdf::loadView('pdf.sales-invoice', [
            'invoice' => $record,
            'taxRate' => SalesInvoiceResource::TAX_RATE,
        ]);

        return $pdf->stream("Invoice-{$record->reference_no}.pdf");
    })->name('sales-invoices.pdf');

    // Rute Cetak Delivery Order / Surat Jalan PDF
    Route::get('/delivery-orders/{record}/pdf', function (DeliveryOrder $record) {
        // Pastikan relasi items dimuat
        $record->load(['salesOrder.customer', 'items.product']); 
        
        // Asumsi nama view: resources/views/pdf/delivery-order.blade.php
        $pdf = Pdf::loadView('pdf.delivery-order', [
            'deliveryOrder' => $record,
        ]);

        return $pdf->stream("SuratJalan-{$record->reference_no}.pdf");
    })->name('delivery-orders.pdf');
});