<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms;
use App\Models\SalesInvoice;
use App\Models\Customer;
use App\Services\CsvExporter;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Penjualan';
    protected static ?string $title = 'Laporan Penjualan & Tagihan';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.sales-report';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'customer_id' => null,
            'status' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Periode & Kriteria Laporan')
                    ->columns(4)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Dari Tanggal')
                            ->required(),

                        Forms\Components\DatePicker::make('end_date')
                            ->label('Sampai Tanggal')
                            ->required(),

                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->placeholder('Semua Pelanggan')
                            ->options(Customer::orderBy('name')->pluck('name', 'id'))
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->label('Status Faktur')
                            ->placeholder('Semua Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Sent' => 'Terkirim / Belum Lunas',
                                'Paid' => 'Lunas',
                                'Cancelled' => 'Dibatalkan',
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function filter(): void
    {
        // Triggers re-render with updated form data
    }

    public function resetFilter(): void
    {
        $this->form->fill([
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'customer_id' => null,
            'status' => null,
        ]);
    }

    public function getFilteredInvoicesProperty()
    {
        $formData = $this->data;

        $query = SalesInvoice::with(['salesOrder.customer', 'salesPayments'])
            ->orderByDesc('invoice_date');

        if (!empty($formData['start_date'])) {
            $query->whereDate('invoice_date', '>=', $formData['start_date']);
        }

        if (!empty($formData['end_date'])) {
            $query->whereDate('invoice_date', '<=', $formData['end_date']);
        }

        if (!empty($formData['customer_id'])) {
            $query->whereHas('salesOrder', function ($q) use ($formData) {
                $q->where('customer_id', $formData['customer_id']);
            });
        }

        if (!empty($formData['status'])) {
            $query->where('status', $formData['status']);
        }

        return $query->get();
    }

    public function getSummaryProperty(): array
    {
        $invoices = $this->filteredInvoices;

        $totalGrand = 0;
        $totalPaid = 0;
        $totalRemaining = 0;
        $count = $invoices->count();

        foreach ($invoices as $inv) {
            if ($inv->status === 'Cancelled') {
                continue;
            }
            $paid = (float) $inv->salesPayments->sum('amount');
            $grand = (float) $inv->grand_total;
            $rem = max(0, $grand - $paid);

            $totalGrand += $grand;
            $totalPaid += $paid;
            $totalRemaining += $rem;
        }

        return [
            'total_invoices' => $count,
            'total_grand' => $totalGrand,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $invoices = $this->filteredInvoices;

        $headers = [
            'No. Invoice',
            'No. SO',
            'Pelanggan',
            'Tgl Faktur',
            'Jatuh Tempo',
            'Subtotal (Rp)',
            'PPN (Rp)',
            'Total Tagihan (Rp)',
            'Total Dibayar (Rp)',
            'Sisa Tagihan (Rp)',
            'Status',
        ];

        $rows = $invoices->map(function ($inv) {
            $totalPaid = (float) $inv->salesPayments->sum('amount');
            $remaining = max(0, (float) $inv->grand_total - $totalPaid);

            return [
                $inv->reference_no,
                $inv->salesOrder?->reference_no ?? '-',
                $inv->salesOrder?->customer?->name ?? '-',
                $inv->invoice_date?->format('d/m/Y') ?? '-',
                $inv->due_date?->format('d/m/Y') ?? '-',
                $inv->total_amount,
                $inv->tax_amount,
                $inv->grand_total,
                $totalPaid,
                $remaining,
                $inv->status,
            ];
        });

        $startDate = $this->data['start_date'] ?? date('Y-m-d');
        $endDate = $this->data['end_date'] ?? date('Y-m-d');

        return CsvExporter::download(
            "laporan-penjualan-{$startDate}-sd-{$endDate}.csv",
            $headers,
            $rows
        );
    }
}

