<x-filament-panels::page>
    <form wire:submit.prevent="filter" class="space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
            <div class="flex items-center gap-2">
                <x-filament::button type="submit" icon="heroicon-m-funnel" color="primary">
                    Terapkan Filter
                </x-filament::button>

                <x-filament::button type="button" wire:click="resetFilter" color="gray" icon="heroicon-m-arrow-path">
                    Reset Filter
                </x-filament::button>
            </div>

            <x-filament::button type="button" wire:click="exportCsv" color="success" icon="heroicon-m-arrow-down-tray">
                Export Laporan ke Excel / CSV
            </x-filament::button>
        </div>
    </form>

    {{-- Summary KPI Cards --}}
    @php
        $summary = $this->summary;
        $invoices = $this->filteredInvoices;
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Faktur</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($summary['total_invoices']) }} Faktur</div>
            <div class="text-xs text-gray-400 mt-1">Sesuai kriteria filter</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Nilai Penjualan</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">Rp {{ number_format($summary['total_grand'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-400 mt-1">Termasuk PPN</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pembayaran Masuk</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">Rp {{ number_format($summary['total_paid'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-400 mt-1">Dana lunas / diterima</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Sisa Piutang Berjalan</div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">Rp {{ number_format($summary['total_remaining'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-400 mt-1">Tagihan belum lunas</div>
        </div>
    </div>

    {{-- Detail Data Table --}}
    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="font-semibold text-gray-800 dark:text-gray-200 text-lg">Rincian Faktur Penjualan</h3>
            <span class="text-xs text-gray-500">{{ $invoices->count() }} Data Ditemukan</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs uppercase font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-3">No. Faktur</th>
                        <th class="px-4 py-3">No. SO</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Tgl Faktur</th>
                        <th class="px-4 py-3">Jatuh Tempo</th>
                        <th class="px-4 py-3 text-right">Total Tagihan</th>
                        <th class="px-4 py-3 text-right">Terbayar</th>
                        <th class="px-4 py-3 text-right">Sisa Piutang</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($invoices as $inv)
                        @php
                            $paid = (float) $inv->salesPayments->sum('amount');
                            $remaining = max(0, (float) $inv->grand_total - $paid);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                {{ $inv->reference_no }}
                            </td>
                            <td class="px-4 py-3">{{ $inv->salesOrder?->reference_no ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $inv->salesOrder?->customer?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $inv->invoice_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $inv->due_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                Rp {{ number_format($inv->grand_total, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">
                                Rp {{ number_format($paid, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $remaining > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                Rp {{ number_format($remaining, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $badgeClasses = match($inv->status) {
                                        'Paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                        'Sent' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                        'Cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $badgeClasses }}">
                                    {{ $inv->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-400">
                                Tidak ada data faktur penjualan yang sesuai dengan filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>

