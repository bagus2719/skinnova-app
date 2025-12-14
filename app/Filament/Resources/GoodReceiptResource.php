<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoodReceiptResource\Pages;
use App\Filament\Resources\GoodReceiptResource\RelationManagers;
use App\Models\GoodReceipt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Closure;
use App\Models\PurchaseOrder;
use Filament\Forms\Get;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;

class GoodReceiptResource extends Resource
{
    protected static ?string $model = GoodReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Purchasing';
    protected static ?string $modelLabel = 'Penerimaan Barang';
    protected static ?string $pluralModelLabel = 'Penerimaan Barang';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar Penerimaan')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi GR')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastGR = GoodReceipt::query()->orderByDesc('id')->first();
                                    $nextId = $lastGR ? $lastGR->id + 1 : 1;
                                    return 'GR-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),

                        Forms\Components\Select::make('purchase_order_id')
                            ->label('Berdasarkan Purchase Order')
                            ->relationship('purchaseOrder', 'reference_no', fn(Builder $query) => $query->whereIn('status', ['Sent', 'Received']))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $po = PurchaseOrder::find($state);
                                    if ($po) {
                                        $itemsData = [];
                                        foreach ($po->items as $item) {
                                            $material = $item->material;
                                            // Hitung Kuantitas Tersisa yang Belum Diterima
                                            $pendingQty = $item->quantity - $item->received_quantity;

                                            if ($pendingQty > 0) {
                                                $itemsData[] = [
                                                    'po_item_id' => $item->id,
                                                    'material_name' => $material->name . ' (' . $material->unit . ')',
                                                    'order_qty' => $item->quantity,
                                                    'received_before' => $item->received_quantity,
                                                    'pending_qty' => $pendingQty,
                                                    // Defaultkan kuantitas yang diterima ke sisa yang belum diterima
                                                    'received_qty' => $pendingQty,
                                                ];
                                            }
                                        }
                                        $set('received_items', $itemsData);
                                    }
                                }
                            }),

                        Forms\Components\DatePicker::make('receipt_date')
                            ->label('Tanggal Penerimaan')
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
                            ->label('Catatan Penerimaan')
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Detail Material yang Diterima')
                    ->description('Masukkan kuantitas material yang benar-benar diterima untuk setiap item PO.')
                    ->schema([
                        Forms\Components\Repeater::make('received_items')
                            ->label('Item Diterima')
                            ->schema([
                                Forms\Components\Hidden::make('po_item_id'),
                                Forms\Components\Hidden::make('order_qty'),
                                Forms\Components\Hidden::make('received_before'),
                                Forms\Components\Hidden::make('pending_qty'),

                                Forms\Components\TextInput::make('material_name')
                                    ->label('Material')
                                    ->readOnly()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('pending_qty')
                                    ->label('Sisa Pesanan')
                                    ->readOnly()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('received_qty')
                                    ->label('Kuantitas Diterima')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    // Aturan Validasi: Kuantitas diterima tidak boleh melebihi sisa pesanan
                                    ->rules([
                                        fn(Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $pending = (float) $get('pending_qty');
                                            if ($value > $pending) {
                                                $fail("Kuantitas diterima ({$value}) tidak boleh melebihi sisa pesanan ({$pending}).");
                                            }
                                        },
                                    ])
                                    ->columnSpan(3),

                            ])
                            ->columns(12)
                            ->disabledOn('edit') // Mencegah perubahan item setelah GR disimpan
                            ->deletable(false)
                            ->addable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn(string $operation, Forms\Get $get) => $operation === 'create' && !$get('purchase_order_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. GR')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchaseOrder.reference_no')
                    ->label('Ref. PO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchaseOrder.vendor.name')
                    ->label('Pemasok')
                    ->searchable(),

                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Tgl. Diterima')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Completed' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purchase_order_id')
                    ->label('Purchase Order')
                    ->relationship('purchaseOrder', 'reference_no')
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('complete')
                    ->label('Selesaikan GR')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penyelesaian Penerimaan')
                    ->modalDescription('Aksi ini akan menambah stok material dan memperbarui kuantitas diterima pada PO. Lanjutkan?')
                    ->action(function (GoodReceipt $record) {
                        if ($record->status !== 'Draft') {
                            Notification::make()->title('Gagal')->body('Hanya GR berstatus Draft yang dapat diselesaikan.')->danger()->send();
                            return;
                        }

                        DB::transaction(function () use ($record) {
                            $po = $record->purchaseOrder;
                            $poItems = $po->items()->with('material')->get()->keyBy('id');
                            $isPOFullyReceived = true;

                            $receivedItemsData = $record->received_items;

                            if (is_array($receivedItemsData)) {
                                foreach ($receivedItemsData as $receivedItem) {
                                    if (!isset($receivedItem['po_item_id']) || !isset($receivedItem['received_qty'])) {
                                        continue;
                                    }

                                    $poItemId = $receivedItem['po_item_id'];
                                    $receivedQty = (float) $receivedItem['received_qty'];

                                    $poItem = $poItems->get($poItemId);

                                    if ($poItem && $receivedQty > 0) {
                                        $material = $poItem->material;
                                        $material->current_stock += $receivedQty;
                                        $material->save();

                                        $poItem->received_quantity += $receivedQty;
                                        $poItem->save();

                                        $epsilon = 0.000001;
                                        $diff = abs($poItem->received_quantity - $poItem->quantity);

                                        if ($diff > $epsilon) {
                                            $isPOFullyReceived = false;
                                        }
                                    }
                                }
                            }
                            $record->status = 'Completed';
                            $record->save();

                            if ($isPOFullyReceived) {
                                $po->status = 'Received';
                            } else {
                                $po->status = 'Sent';
                            }
                            $po->save();
                        });

                        Notification::make()
                            ->title('Penerimaan Selesai')
                            ->body("Barang untuk PO {$record->purchaseOrder->reference_no} telah diterima dan stok material diperbarui.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn(GoodReceipt $record): bool => $record->status === 'Draft'),
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
            'index' => Pages\ListGoodReceipts::route('/'),
            'create' => Pages\CreateGoodReceipt::route('/create'),
            'edit' => Pages\EditGoodReceipt::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Draft')->count();
    }
}
