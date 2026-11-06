<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\SalesInvoice;
use App\Models\PurchaseBill;
use Illuminate\Support\Carbon;

class MonthlySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Penjualan & Pembelian Bulanan';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 2;
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $now = Carbon::now();
        $labels = [];
        $salesData = [];
        $purchasesData = [];

        // 6 Bulan Terakhir
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $labels[] = $month->translatedFormat('F Y');

            $sales = (float) SalesInvoice::query()
                ->whereYear('invoice_date', $month->year)
                ->whereMonth('invoice_date', $month->month)
                ->where('status', 'Paid')
                ->sum('grand_total');

            $purchases = (float) PurchaseBill::query()
                ->whereYear('bill_date', $month->year)
                ->whereMonth('bill_date', $month->month)
                ->whereIn('status', ['Open', 'Paid'])
                ->sum('total_amount');

            $salesData[] = $sales;
            $purchasesData[] = $purchases;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan Lunas (Rp)',
                    'data' => $salesData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Pembelian Material (Rp)',
                    'data' => $purchasesData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

