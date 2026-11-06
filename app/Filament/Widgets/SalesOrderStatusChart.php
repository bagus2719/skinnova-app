<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\SalesOrder;

class SalesOrderStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Status Sales Order';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $statuses = ['Draft', 'Confirmed', 'Shipped', 'Cancelled'];
        $counts = [];

        foreach ($statuses as $status) {
            $counts[] = SalesOrder::query()->where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Pesanan',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#94a3b8', // Draft (Gray)
                        '#3b82f6', // Confirmed (Blue)
                        '#10b981', // Shipped (Green)
                        '#ef4444', // Cancelled (Red)
                    ],
                ],
            ],
            'labels' => ['Draft', 'Dikonfirmasi', 'Terkirim', 'Dibatalkan'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

