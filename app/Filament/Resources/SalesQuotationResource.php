<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesQuotationResource\Pages;
use App\Filament\Resources\SalesQuotationResource\RelationManagers;
use App\Models\SalesQuotation;
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
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;

class SalesQuotationResource extends Resource
{
    protected static ?string $model = SalesQuotation::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $modelLabel = 'Sales Quotation (SQ)';
    protected static ?string $pluralModelLabel = 'Sales Quotations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Penawaran')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi SQ')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastSQ = SalesQuotation::query()->orderByDesc('id')->first();
                                    $nextId = $lastSQ ? $lastSQ->id + 1 : 1;
                                    return 'SQ-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
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
                            ->required(),

                        Forms\Components\DatePicker::make('quotation_date')
                            ->label('Tanggal Penawaran')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('valid_until_date')
                            ->label('Berlaku Hingga')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Sent' => 'Terkirim',
                                'Won' => 'Menang',
                                'Lost' => 'Kalah',
                                'Expired' => 'Kadaluarsa',
                            ])
                            ->default('Draft')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan SQ')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detail Item Penawaran (Product)')
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
                                    ->label('Kuantitas')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->columnSpan(2)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateLineTotal($set, $get))
                                    ->live(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Harga/Unit')
                                    ->prefix('Rp')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.00)
                                    ->default(0.00)
                                    ->columnSpan(3)
                                    ->afterStateUpdated(fn(Set $set, Get $get) => self::calculateLineTotal($set, $get))
                                    ->live(),

                                Forms\Components\TextInput::make('line_total')
                                    ->label('Subtotal')
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
                            ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::calculateTotalAmount($get, $set))
                            ->mutateDehydratedStateUsing(function ($state, Forms\Get $get, Forms\Set $set) {
                                self::calculateTotalAmount($get, $set);
                                return $state;
                            }),
                    ]),

                Forms\Components\Section::make('Total Penawaran')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Jumlah SQ')
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
                    ->label('Ref. SQ')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quotation_date')
                    ->label('Tgl. SQ')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until_date')
                    ->label('Berlaku Hingga')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total SQ')
                    ->prefix('Rp ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Sent' => 'info',
                        'Won' => 'success',
                        'Lost' => 'danger',
                        'Expired' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Pelanggan')
                    ->relationship('customer', 'name')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('convertToSO')
                    ->label('Buat Sales Order')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konversi ke Sales Order')
                    ->modalDescription('Apakah Anda yakin ingin mengkonversi Penawaran ini menjadi Sales Order? Aksi ini akan membuat dokumen SO baru.')
                    ->action(function (SalesQuotation $record) {
                        if ($record->status !== 'Won' && $record->status !== 'Sent') {
                            Notification::make()->title('Gagal Konversi')->body('Hanya Penawaran dengan status "Won" atau "Sent" yang dapat dikonversi.')->danger()->send();
                            return;
                        }
                        
                        DB::transaction(function () use ($record) {
                            $lastSO = SalesOrder::query()->orderByDesc('id')->first();
                            $nextId = $lastSO ? $lastSO->id + 1 : 1;
                            $nextRefNo = 'SO-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                            $newSO = SalesOrder::create([
                                'customer_id' => $record->customer_id,
                                'reference_no' => $nextRefNo,
                                'order_date' => now(),
                                'total_amount' => $record->total_amount,
                                'status' => 'Confirmed',
                                'notes' => 'Dikonversi dari SQ Ref: ' . $record->reference_no,
                            ]);

                            foreach ($record->items as $sqItem) {
                                SalesOrderItem::create([
                                    'sales_order_id' => $newSO->id,
                                    'product_id' => $sqItem->product_id,
                                    'quantity' => $sqItem->quantity,
                                    'unit_price' => $sqItem->unit_price,
                                    'line_total' => $sqItem->line_total,
                                ]);
                            }
                            
                            $record->status = 'Won';
                            $record->save();
                            
                            Notification::make()
                                ->title('Konversi Berhasil')
                                ->body("Sales Order {$newSO->reference_no} telah dibuat dari Penawaran ini.")
                                ->success()
                                ->send();
                        });
                    })
                    ->visible(fn (SalesQuotation $record): bool => $record->status === 'Won' || $record->status === 'Sent'),
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
            'index' => Pages\ListSalesQuotations::route('/'),
            'create' => Pages\CreateSalesQuotation::route('/create'),
            'edit' => Pages\EditSalesQuotation::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }
}
