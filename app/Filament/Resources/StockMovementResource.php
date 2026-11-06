<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Services\CsvExporter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Manufacturing';
    protected static ?string $navigationLabel = 'Kartu Stok / Mutasi';
    protected static ?string $modelLabel = 'Mutasi Stok';
    protected static ?string $pluralModelLabel = 'Kartu Stok / Mutasi Stok';
    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['inventory', 'production', 'finance']) ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('movement_date')
                    ->label('Waktu Transaksi')
                    ->readOnly(),

                Forms\Components\TextInput::make('type')
                    ->label('Tipe Mutasi')
                    ->readOnly(),

                Forms\Components\TextInput::make('item_type')
                    ->label('Kategori')
                    ->readOnly(),

                Forms\Components\TextInput::make('batch_number')
                    ->label('No. Batch')
                    ->readOnly(),

                Forms\Components\DatePicker::make('expired_date')
                    ->label('Tgl Kadaluarsa')
                    ->readOnly(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Jumlah Mutasi')
                    ->numeric()
                    ->readOnly(),

                Forms\Components\TextInput::make('stock_before')
                    ->label('Stok Sebelum')
                    ->numeric()
                    ->readOnly(),

                Forms\Components\TextInput::make('stock_after')
                    ->label('Saldo Akhir')
                    ->numeric()
                    ->readOnly(),

                Forms\Components\TextInput::make('reference_type')
                    ->label('Dokumen Referensi')
                    ->readOnly(),

                Forms\Components\TextInput::make('reference_no')
                    ->label('No. Referensi')
                    ->readOnly(),

                Forms\Components\Textarea::make('notes')
                    ->label('Keterangan')
                    ->columnSpanFull()
                    ->readOnly(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('movement_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'IN' ? 'Masuk (IN)' : 'Keluar (OUT)')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item_type')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Product' => 'info',
                        'Material' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'Product' ? 'Produk Jadi' : 'Bahan Baku')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Barang')
                    ->state(function (StockMovement $record): string {
                        if ($record->item_type === 'Product') {
                            return $record->product?->name ?? '-';
                        }
                        return $record->material?->name ?? '-';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                     ->orWhereHas('material', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch_number')
                    ->label('No. Batch')
                    ->searchable()
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expired_date')
                    ->label('Expired Date')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (StockMovement $record) => $record->type === 'IN' ? 'success' : 'danger')
                    ->formatStateUsing(fn (StockMovement $record, $state) => ($record->type === 'IN' ? '+' : '-') . number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_before')
                    ->label('Stok Awal')
                    ->numeric(decimalPlaces: 2)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stock_after')
                    ->label('Saldo Akhir')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reference_no')
                    ->label('No. Referensi')
                    ->description(fn (StockMovement $record): string => $record->reference_type)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_type')
                    ->label('Kategori Barang')
                    ->options([
                        'Product' => 'Produk Jadi',
                        'Material' => 'Bahan Baku',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Mutasi')
                    ->options([
                        'IN' => 'Barang Masuk (IN)',
                        'OUT' => 'Barang Keluar (OUT)',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export Kartu Stok ke Excel/CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        $movements = StockMovement::with(['product', 'material'])
                            ->orderByDesc('movement_date')
                            ->get();

                        $headers = [
                            'Waktu Mutasi',
                            'Tipe (IN/OUT)',
                            'Kategori',
                            'Nama Barang',
                            'No. Batch / Lot',
                            'Tgl Expired',
                            'Kuantitas Mutasi',
                            'Stok Sebelum',
                            'Saldo Akhir',
                            'Jenis Dokumen',
                            'No. Referensi',
                            'Keterangan',
                        ];

                        $rows = $movements->map(function ($m) {
                            $name = $m->item_type === 'Product'
                                ? ($m->product?->name ?? '-')
                                : ($m->material?->name ?? '-');

                            return [
                                $m->movement_date?->format('d/m/Y H:i:s') ?? '-',
                                $m->type,
                                $m->item_type,
                                $name,
                                $m->batch_number ?? '-',
                                $m->expired_date?->format('d/m/Y') ?? '-',
                                ($m->type === 'IN' ? '+' : '-') . $m->quantity,
                                $m->stock_before,
                                $m->stock_after,
                                $m->reference_type,
                                $m->reference_no,
                                $m->notes ?? '-',
                            ];
                        });

                        return CsvExporter::download(
                            'kartu-stok-mutasi-' . date('Y-m-d') . '.csv',
                            $headers,
                            $rows
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}

