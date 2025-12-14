<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Filament\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\SalesInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use App\Filament\Resources\SalesInvoiceResource\RelationManagers\SalesPaymentsRelationManager;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $modelLabel = 'Sales Invoice (Faktur)';
    protected static ?string $pluralModelLabel = 'Sales Invoice';
    const TAX_RATE = 0.11;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Faktur')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Faktur')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastInv = SalesInvoice::query()->orderByDesc('id')->first();
                                    $nextId = $lastInv ? $lastInv->id + 1 : 1;
                                    return 'INV-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),

                        Forms\Components\Select::make('sales_order_id')
                            ->label('Sales Order (SO)')
                            ->relationship(
                                'salesOrder',
                                'reference_no',
                                fn(Builder $query) =>
                                $query->whereIn('status', ['Shipped', 'Confirmed'])
                            )
                            ->getOptionLabelFromRecordUsing(fn(SalesOrder $record) => "{$record->reference_no} ({$record->customer->name})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state) {
                                if (!$state) {
                                    $set('items', []);
                                    self::calculateTotalAmount($set, null, null);
                                    return;
                                }

                                $so = SalesOrder::with('items')->find($state);
                                if (!$so)
                                    return;

                                $itemsData = $so->items->map(function (SalesOrderItem $item) {
                                    $billableQty = $item->shipped_quantity;
                    
                                    if ($billableQty > 0) {
                                        return [
                                            'product_id' => $item->product_id,
                                            'quantity' => $billableQty,
                                            'unit_price' => $item->unit_price,
                                            'line_total' => round($billableQty * $item->unit_price, 2),
                                        ];
                                    }
                                    return null;
                                })->filter()->toArray();

                                $set('items', $itemsData);
                                self::calculateTotalAmount($set, $so->items, $so->id);
                            }),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Tanggal Faktur')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('Jatuh Tempo')
                            ->default(now()->addDays(30))
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status Pembayaran')
                            ->options([
                                'Draft' => 'Draft',
                                'Sent' => 'Terkirim',
                                'Paid' => 'Lunas',
                                'Cancelled' => 'Dibatalkan',
                            ])
                            ->default('Draft')
                            ->required(),

                    ])->columns(3),

                Forms\Components\Section::make('Detail Penagihan')
                    ->description('Kuantitas ditagih harus berdasarkan barang yang sudah terkirim (Delivery Order).')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::pluck('name', 'id'))
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty Ditagih')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Harga/Unit')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('line_total')
                                    ->label('Subtotal')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly()
                                    ->columnSpan(3),
                            ])
                            ->columns(12)
                            ->deletable(false)
                            ->addable(false)
                            ->reorderable(false),
                    ])->hidden(fn(Get $get) => !$get('sales_order_id')),

                Forms\Components\Section::make('Ringkasan Keuangan')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total (Sebelum Pajak)')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0.00),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Pajak (PPN ' . (self::TAX_RATE * 100) . '%)')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0.00),

                        Forms\Components\TextInput::make('grand_total')
                            ->label('TOTAL AKHIR')
                            ->prefix('Rp')
                            ->numeric()
                            ->readOnly()
                            ->default(0.00),
                    ])->columns(3),
            ]);
    }

    public static function calculateTotalAmount(Set $set, $items = null, $soId = null): void
    {
        $total = 0;

        if ($items && is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['line_total']) && is_numeric($item['line_total'])) {
                    $total += (float) $item['line_total'];
                }
            }
        }
        elseif ($soId) {
            $so = SalesOrder::find($soId);
            if ($so) {
                foreach ($so->items as $item) {
                    $total += ($item->shipped_quantity * $item->unit_price);
                }
            }
        }

        $tax = round($total * self::TAX_RATE, 2);
        $grandTotal = round($total + $tax, 2);

        $set('total_amount', round($total, 2));
        $set('tax_amount', $tax);
        $set('grand_total', $grandTotal);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. INV')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.reference_no')
                    ->label('Dari SO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label('Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Tgl. Faktur')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total Tagihan')
                    ->prefix('Rp ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Sent' => 'info',
                        'Paid' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Sent' => 'Terkirim',
                        'Paid' => 'Lunas',
                        'Cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SalesInvoice $record) {
                        $record->status = 'Paid';
                        $record->save();
                        Notification::make()->title('Faktur Lunas')->body("Faktur {$record->reference_no} ditandai sebagai Lunas.")->success()->send();
                    })
                    ->visible(fn(SalesInvoice $record) => $record->status !== 'Paid' && $record->status !== 'Cancelled'),
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
            SalesPaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
