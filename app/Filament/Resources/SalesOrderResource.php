<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Filament\Resources\SalesOrderResource\RelationManagers;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Customer;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $modelLabel = 'Sales Order (SO)';
    protected static ?string $pluralModelLabel = 'Sales Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pelanggan & Pesanan')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi SO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastSO = SalesOrder::query()->orderByDesc('id')->first();
                                    $nextId = $lastSO ? $lastSO->id + 1 : 1;
                                    return 'SO-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),

                        Forms\Components\Select::make('customer_id')
                            ->label('Pelanggan')
                            ->relationship('customer', 'name', fn(Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        $set('shipping_address', $customer->address);
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('order_date')
                            ->label('Tanggal Pesanan')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('expected_shipment_date')
                            ->label('Target Pengiriman')
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Confirmed' => 'Dikonfirmasi',
                                'Shipped' => 'Terkirim Penuh',
                                'Cancelled' => 'Dibatalkan',
                            ])
                            ->default('Draft')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('shipping_address')
                            ->label('Alamat Pengiriman')
                            ->columnSpanFull()
                            ->nullable(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan SO')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detail Item Penjualan (Product)')
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
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(4)
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Defaultkan unit_price ke selling_price product
                                                $set('unit_price', $product->selling_price);
                                            }
                                        }
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
                                    ->label('Harga Jual/Unit')
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
                            ->addActionLabel('Tambah Product')
                            ->reorderable(false)
                            ->collapsible()
                            // Hook untuk kalkulasi saat item diubah/dihapus/ditambah
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::calculateTotalAmount($get, $set))
                            ->mutateDehydratedStateUsing(function ($state, Forms\Get $get, Forms\Set $set) {
                                self::calculateTotalAmount($get, $set);
                                return $state;
                            }),
                    ]),

                Forms\Components\Section::make('Total Penjualan')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Jumlah SO')
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
                    ->label('Ref. SO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name') // Mengambil nama dari relasi customer
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Tgl. Order')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total SO')
                    ->prefix('Rp ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Confirmed' => 'warning',
                        'Shipped' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_shipment_date')
                    ->label('Target Kirim')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Confirmed' => 'Dikonfirmasi',
                        'Shipped' => 'Terkirim Penuh',
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
            ])
            ->filters([
                //
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
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }
}
