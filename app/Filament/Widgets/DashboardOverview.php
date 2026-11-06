<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\SalesInvoice;
use App\Models\PurchaseBill;
use App\Models\Product;
use App\Models\Material;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Schema;

class DashboardOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // 1. Total Penjualan Tahun Berjalan (YTD) yang sudah Paid
        $totalSalesYear = SalesInvoice::query()
            ->where('invoice_date', '>=', Carbon::now()->startOfYear())
            ->where('status', 'Paid')
            ->sum('grand_total');

        // Tren 6 bulan terakhir untuk sparkline chart
        $salesSparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $salesSparkline[] = (float) SalesInvoice::query()
                ->whereYear('invoice_date', $month->year)
                ->whereMonth('invoice_date', $month->month)
                ->where('status', 'Paid')
                ->sum('grand_total');
        }

        // 2. Total Piutang Usaha (Invoice belum lunas: Draft & Sent)
        $unpaidSalesInvoices = SalesInvoice::query()
            ->whereIn('status', ['Draft', 'Sent'])
            ->withSum('salesPayments', 'amount')
            ->get();

        $unpaidSales = $unpaidSalesInvoices->sum(function ($inv) {
            $paid = (float) ($inv->sales_payments_sum_amount ?? 0);
            return max(0, (float) $inv->grand_total - $paid);
        });

        // 3. Total Hutang Usaha (Tagihan Pembelian belum lunas: Draft & Open)
        $unpaidPurchases = PurchaseBill::query()
            ->whereIn('status', ['Draft', 'Open'])
            ->get()
            ->sum(function ($bill) {
                return max(0, (float) $bill->total_amount - (float) ($bill->paid_amount ?? 0));
            });

        // 4. Item dengan Stok Rendah (di bawah atau sama dengan minimum_stock)
        $hasProductMinStock = Schema::hasColumn('products', 'minimum_stock');
        $lowStockProducts = $hasProductMinStock
            ? Product::query()->whereColumn('current_stock', '<=', 'minimum_stock')->count()
            : Product::query()->where('current_stock', '<=', 0)->count();

        $hasMaterialMinStock = Schema::hasColumn('materials', 'minimum_stock');
        $lowStockMaterials = $hasMaterialMinStock
            ? Material::query()->whereColumn('current_stock', '<=', 'minimum_stock')->count()
            : Material::query()->where('current_stock', '<=', 0)->count();

        $lowStockTotal = $lowStockProducts + $lowStockMaterials;

        return [
            Stat::make('Penjualan Tahun Ini (YTD)', 'Rp ' . Number::format($totalSalesYear, 0, 2))
                ->description('Total pendapatan invoice lunas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($salesSparkline)
                ->color('success'),

            Stat::make('Total Piutang Pelanggan', 'Rp ' . Number::format($unpaidSales, 0, 2))
                ->description('Tagihan invoice belum lunas')
                ->descriptionIcon('heroicon-m-clock')
                ->color($unpaidSales > 10000000 ? 'danger' : ($unpaidSales > 0 ? 'warning' : 'success')),

            Stat::make('Total Hutang Vendor', 'Rp ' . Number::format($unpaidPurchases, 0, 2))
                ->description('Tagihan pembelian belum lunas')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color($unpaidPurchases > 10000000 ? 'danger' : ($unpaidPurchases > 0 ? 'warning' : 'success')),

            Stat::make('Peringatan Stok Rendah', $lowStockTotal . ' item')
                ->description("{$lowStockProducts} produk & {$lowStockMaterials} material kritis")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockTotal > 0 ? 'danger' : 'success'),
        ];
    }
}
