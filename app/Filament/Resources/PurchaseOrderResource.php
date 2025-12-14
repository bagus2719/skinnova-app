<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Models\Material;
use Filament\Forms\Components\Actions\DeleteAction;
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $modelLabel = 'Purchase Orders (PO)';
    protected static ?string $pluralModelLabel = 'Purchase Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar PO')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi PO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastPO = PurchaseOrder::query()->orderByDesc('id')->first();
                                    $nextId = $lastPO ? $lastPO->id + 1 : 1;
                                    return 'PO-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),

                        Forms\Components\Select::make('vendor_id')
                            ->label('Pemasok (Vendor)')
                            ->relationship('vendor', 'name', fn(Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('order_date')
                            ->label('Tanggal Pemesanan')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('expected_receipt_date')
                            ->label('Target Tanggal Kedatangan')
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Sent' => 'Terkirim',
                                'Received' => 'Diterima Penuh',
                                'Cancelled' => 'Dibatalkan',
                            ])
                            ->default('Draft')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan PO')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detail Item Pembelian')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('Update Cost')
                            ->label('Hitung Ulang Total')
                            ->icon('heroicon-o-currency-dollar')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->action(fn(Forms\Get $get, Forms\Set $set) => self::calculateTotalAmount($get, $set))
                    ])
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label('Material')
                                    ->options(Material::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(4)
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        // Cari std_cost dari material yang dipilih
                                        if ($state) {
                                            $material = Material::find($state);
                                            if ($material) {
                                                // Defaultkan unit_price ke std_cost material saat ini
                                                $set('unit_price', $material->std_cost);
                                            }
                                        }
                                        // Trigger kalkulasi total baris dan total PO
                                        self::calculateLineTotal($set, $get);
                                    })
                                    ->live(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Kuantitas Pesanan')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->columnSpan(2)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateLineTotal($set, $get))
                                    ->live(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Harga Beli/Unit')
                                    ->prefix('Rp')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.00)
                                    ->default(0.00)
                                    ->columnSpan(3)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateLineTotal($set, $get))
                                    ->live(),

                                Forms\Components\TextInput::make('line_total')
                                    ->label('Subtotal Baris')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->columnSpan(3),
                            ])
                            ->defaultItems(1)
                            ->columns(12)
                            ->addActionLabel('Tambah Material')
                            ->reorderable(false)
                            ->collapsible()
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::calculateTotalAmount($get, $set))
                            ->mutateDehydratedStateUsing(function ($state, Forms\Get $get, Forms\Set $set) {
                                self::calculateTotalAmount($get, $set);
                                return $state;
                            }),
                    ]),

                Forms\Components\Section::make('Total Pembelian')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Jumlah PO')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0.00)
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
    }

    public static function calculateLineTotal(Set $set, Get $get): void
    {
        $qty = (float) $get('quantity');
        $price = (float) $get('unit_price');
        $lineTotal = round($qty * $price, 2);

        $set('line_total', $lineTotal);
    }

    public static function calculateTotalAmount(Get $get, Set $set): void
    {
        $items = $get('items');
        $total = 0;

        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['line_total']) && is_numeric($item['line_total'])) {
                    $total += (float) $item['line_total'];
                }
            }
        }

        $set('total_amount', round($total, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. PO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Pemasok')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Tgl. Order')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total PO')
                    ->prefix('Rp ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Sent' => 'warning',
                        'Received' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_receipt_date')
                    ->label('Target Kedatangan')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Pemasok')
                    ->relationship('vendor', 'name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Terkirim',
                        'Received' => 'Diterima Penuh',
                        'Cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }
}
