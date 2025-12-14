<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Models\ManufacturingOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Product;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;

class ManufacturingOrderResource extends Resource
{
    protected static ?string $model = ManufacturingOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Manufacturing';
    protected static ?string $modelLabel = 'Manufacturing Order';
    protected static ?string $pluralModelLabel = 'Manufacturing Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar Produksi')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastOrder = ManufacturingOrder::query()->orderByDesc('id')->first();
                                    $nextId = $lastOrder ? $lastOrder->id + 1 : 1;
                                    return 'MO-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true),

                        Forms\Components\Select::make('product_id')
                            ->label('Produk yang Diproduksi')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                self::calculateTotalCost($get, $set);
                            }),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Kuantitas Produksi')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                self::calculateTotalCost($get, $set);
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Planned' => 'Planned',
                                'Done' => 'Selesai (Done)',
                                'Cancelled' => 'Dibatalkan (Cancelled)',
                            ])
                            ->default('Draft')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Rincian Biaya & Tanggal')
                    ->schema([
                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Biaya Material')
                            ->prefix('Rp')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->readOnly()
                            ->helperText('Biaya ini dihitung otomatis dari standar harga material di BOM.'),

                        Forms\Components\DatePicker::make('planned_start_date')
                            ->label('Rencana Mulai')
                            ->nullable(),

                        Forms\Components\DatePicker::make('finished_at')
                            ->label('Tanggal Selesai')
                            ->nullable()
                            ->readOnly(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    protected static function calculateTotalCost(Forms\Get $get, Forms\Set $set): void
    {
        $productId = $get('product_id');
        $quantity = (float) $get('quantity');

        if ($productId && $quantity > 0) {
            $product = Product::with('boms.material')->find($productId);

            if ($product && $product->boms->count() > 0) {
                $costPerUnit = 0;

                foreach ($product->boms as $bom) {
                    if ($bom->material && $bom->material->std_cost !== null) {
                        $costPerUnit += $bom->quantity * $bom->material->std_cost;
                    }
                }

                $totalCost = $costPerUnit * $quantity;

                $set('total_cost', round($totalCost, 2));
            } else {
                $set('total_cost', 0);
            }
        } else {
            $set('total_cost', 0);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. No.')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produk Jadi')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn($record) => $record->product?->unit ? ' ' . $record->product->unit : '')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Planned' => 'warning',
                        'Done' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Biaya Material')
                    ->prefix('Rp ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Selesai')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Belum Selesai')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Planned' => 'Planned',
                        'Done' => 'Selesai (Done)',
                        'Cancelled' => 'Dibatalkan (Cancelled)',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markAsDone')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-check-circle')

                    ->modalDescription(function (ManufacturingOrder $record): HtmlString {
                        $product = $record->product;
                        $quantityToProduce = $record->quantity;
                        $shortages = [];

                        foreach ($product->boms as $bom) {
                            $material = $bom->material;
                            if (!$material)
                                continue;

                            $required = $bom->quantity * $quantityToProduce;
                            $available = $material->current_stock;

                            if ($available < $required) {
                                $shortage = $required - $available;
                                $shortages[] = [
                                    'material' => $material->name,
                                    'available' => \Illuminate\Support\Number::format($available, 2) . ' ' . $material->unit,
                                    'shortage' => \Illuminate\Support\Number::format($shortage, 2) . ' ' . $material->unit,
                                ];
                            }
                        }

                        if (!empty($shortages)) {
                            $listItems = '';
                            foreach ($shortages as $s) {
                                $listItems .= "<li>{$s['material']}: Kurang {$s['shortage']} (Tersedia: {$s['available']})</li>";
                            }

                            $html = "
                                <div style='color: red; font-weight: bold; margin-bottom: 10px;'>
                                    ⚠️ PERINGATAN! STOK TIDAK CUKUP
                                </div>
                                Produksi {$record->reference_no} tidak dapat diselesaikan karena kekurangan material:<br>
                                <ul style='margin-top: 10px; margin-left: 20px;'>{$listItems}</ul>
                            ";

                            return new HtmlString($html);

                        } else {
                            return new HtmlString('Aksi ini akan mengurangi stok material dan menambah stok produk jadi secara permanen. Lanjutkan?');
                        }
                    })

                    ->action(function (ManufacturingOrder $record) {
                        $product = $record->product;
                        $quantityToProduce = $record->quantity;
                        $isShortage = false;

                        // Pengecekan stok final (Validasi Data Integrity)
                        foreach ($product->boms as $bom) {
                            $material = $bom->material;
                            $required = $bom->quantity * $quantityToProduce;

                            if ($material && $material->current_stock < $required) {
                                $isShortage = true;
                                break;
                            }
                        }

                        if ($isShortage) {
                            // Jika ada kekurangan, hentikan proses dan kirim Notifikasi rapi
                            Notification::make()
                                ->title('Gagal Diselesaikan')
                                ->body('Terdapat kekurangan stok material. Proses produksi dibatalkan.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Jika stok cukup, lanjutkan proses DONE
                        $record->status = 'Done';
                        $record->save();
                        Notification::make()
                            ->title('Produksi Selesai')
                            ->body("Perintah produksi {$record->reference_no} berhasil diselesaikan.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn(ManufacturingOrder $record): bool => $record->status !== 'Done' && $record->status !== 'Cancelled'),

                Tables\Actions\Action::make('markAsCancelled')
                    ->label('Batalkan Produksi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-x-circle')
                    ->modalHeading('Konfirmasi Pembatalan Produksi')
                    ->modalDescription('Jika MO ini sudah Selesai (Done), aksi ini akan membalikkan semua perubahan stok (material dikembalikan, produk jadi dikurangi).')
                    ->visible(fn(ManufacturingOrder $record): bool => $record->status !== 'Cancelled')
                    ->action(function (ManufacturingOrder $record) {
                        $record->status = 'Cancelled';
                        $record->save();
                        Notification::make()
                            ->title('Produksi Dibatalkan')
                            ->body("Perintah produksi {$record->reference_no} telah dibatalkan. Jika sebelumnya status 'Done', stok telah dikembalikan.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
