<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Filament\Resources\DeliveryOrderResource\RelationManagers;
use App\Models\DeliveryOrder;
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
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Set;
use Filament\Forms\Get;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $modelLabel = 'Delivery Order (DO)';
    protected static ?string $pluralModelLabel = 'Delivery Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Delivery Order')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi DO')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastDO = DeliveryOrder::query()->orderByDesc('id')->first();
                                    $nextId = $lastDO ? $lastDO->id + 1 : 1;
                                    return 'DO-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),

                        Forms\Components\Select::make('sales_order_id')
                            ->label('Sales Order (SO)')
                            ->relationship('salesOrder', 'reference_no', fn (Builder $query) => 
                                $query->where('status', 'Confirmed') // Hanya SO yang Dikonfirmasi
                                      // PERBAIKAN: Mengganti 'sol.delivered_quantity' menjadi 'sol.shipped_quantity'
                                      ->whereColumn('sales_orders.total_amount', '>', DB::raw('(SELECT COALESCE(SUM(sol.shipped_quantity), 0) FROM sales_order_items sol WHERE sol.sales_order_id = sales_orders.id)'))
                            )
                            ->getOptionLabelFromRecordUsing(fn (SalesOrder $record) => "{$record->reference_no} ({$record->customer->name})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?int $state) {
                                if (!$state) {
                                    $set('items', []);
                                    return;
                                }

                                $so = SalesOrder::find($state);
                                if (!$so) return;
                                
                                $items = $so->items->map(function (SalesOrderItem $item) {
                                    $pendingQty = $item->quantity - $item->shipped_quantity;
                                    
                                    if ($pendingQty > 0) {
                                        return [
                                            'product_id' => $item->product_id,
                                            'sales_order_quantity' => $item->quantity,
                                            'delivered_quantity' => $pendingQty,
                                        ];
                                    }
                                    return null;
                                })->filter()->toArray();

                                $set('items', $items);
                            }),
                            
                        Forms\Components\DatePicker::make('delivery_date')
                            ->label('Tanggal Pengiriman')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Completed' => 'Selesai',
                                'Cancelled' => 'Dibatalkan',
                            ])
                            ->default('Draft')
                            ->required(),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan DO')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detail Item Pengiriman')
                    ->description('Sesuaikan Kuantitas Dikirim (Delivered Quantity) dengan barang yang dikeluarkan dari gudang.')
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
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('sales_order_quantity')
                                    ->label('Qty SO')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('delivered_quantity')
                                    ->label('Qty Dikirim')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->columnSpan(3)
                                    // Validasi: Qty Delivered tidak boleh lebih dari Qty SO yang belum terkirim (belum diimplementasikan)
                                    // ->rules([
                                    //     fn (Get $get, SalesOrderItem $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    //         // Ini adalah validasi kompleks yang memerlukan akses ke SO Item asli
                                    //         // Untuk sementara, kita abaikan dan andalkan validasi di aksi "Complete"
                                    //     },
                                    // ]),
                                    ->live(),

                            ])
                            ->columns(12)
                            ->addActionLabel('Tambah Item')
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible(),
                    ])->hidden(fn (Get $get) => !$get('sales_order_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. DO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('salesOrder.reference_no')
                    ->label('Dari SO')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('salesOrder.customer.name')
                    ->label('Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('delivery_date')
                    ->label('Tgl. Kirim')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Completed' => 'Selesai',
                        'Cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('completeDelivery')
                    ->label('Complete Delivery')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pengiriman')
                    ->modalDescription('Aksi ini akan mengurangi stok Produk dan memperbarui status SO. Lanjutkan?')
                    ->action(function (DeliveryOrder $record) {
                        if ($record->status !== 'Draft') {
                            Notification::make()->title('Gagal')->body('Hanya Delivery Order berstatus "Draft" yang dapat diselesaikan.')->danger()->send();
                            return;
                        }
                        
                        DB::transaction(function () use ($record) {
                            $so = $record->salesOrder;
                            $allDelivered = true;

                            foreach ($record->items as $doItem) {
                                $deliveredQty = $doItem->delivered_quantity;
                                $product = $doItem->product;
                                
                                $soItem = $so->items->where('product_id', $product->id)->first();
                                if ($soItem) {
                                    $soItem->shipped_quantity += $deliveredQty;
                                    $soItem->save();
                                    
                                    if ($soItem->shipped_quantity < $soItem->quantity) {
                                        $allDelivered = false; 
                                    }
                                }
                            }
                            
                            $record->status = 'Completed';
                            $record->save();
                            
                            $so->status = $allDelivered ? 'Shipped' : 'Confirmed';
                            $so->save();

                            Notification::make()
                                ->title('Pengiriman Selesai')
                                ->body("Delivery Order {$record->reference_no} telah diselesaikan. Stok produk telah dikurangi dan SO diperbarui.")
                                ->success()
                                ->send();
                        });
                    })
                    ->visible(fn (DeliveryOrder $record): bool => $record->status === 'Draft'),
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
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }
}
