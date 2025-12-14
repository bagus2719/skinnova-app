<?php

use Illuminate\Support\Facades\Route;
use App\Models\SalesInvoice;
use App\Models\DeliveryOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Resources\SalesInvoiceResource;
use App\Models\PurchaseBill;
use App\Filament\Resources\PurchaseBillResource;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::redirect('/', '/admin');

Route::group(['prefix' => 'admin', 'as' => 'filament.admin.', 'middleware' => ['web', 'auth']], function () {
    // Rute Cetak Sales Invoice PDF
    Route::get('/sales-invoices/{record}/pdf', function (SalesInvoice $record) {
        $record->load(['salesOrder.customer', 'items.product']);

        $pdf = Pdf::loadView('pdf.sales-invoice', [
            'invoice' => $record,
            'taxRate' => SalesInvoiceResource::TAX_RATE,
        ]);

        return $pdf->stream("Invoice-{$record->reference_no}.pdf");
    })->name('sales-invoices.pdf');

    // Rute Cetak Delivery Order / Surat Jalan PDF
    Route::get('/delivery-orders/{record}/pdf', function (DeliveryOrder $record) {
        $record->load(['salesOrder.customer', 'items.product']);

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'deliveryOrder' => $record,
        ]);

        return $pdf->stream("SuratJalan-{$record->reference_no}.pdf");
    })->name('delivery-orders.pdf');

    Route::get('/purchase-bills/{record}/pdf', function (PurchaseBill $record) {
        $record->load([
            'goodReceipt.purchaseOrder.vendor',
            'items.purchaseOrderItem.product',
            'items.purchaseOrderItem.material',
        ]);

        $pdf = Pdf::loadView('pdf.purchase-bill', [
            'bill' => $record,
        ]);

        return $pdf->stream("Bill-{$record->reference_no}.pdf");
    })->name('purchase-bills.pdf');
});