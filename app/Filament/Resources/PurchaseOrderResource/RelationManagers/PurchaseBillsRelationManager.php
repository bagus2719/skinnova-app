<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrderItem;

class PurchaseBillsRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseBills';
    protected static ?string $title = 'Tagihan Vendor';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Tagihan')
                            ->schema([
                                Forms\Components\TextInput::make('reference_no')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->default(fn() => 'PB-' . date('Ymd') . '-' . Str::random(5))
                                    ->disabledOn('edit')
                                    ->maxLength(255),

                                Forms\Components\Hidden::make('vendor_id')
                                    ->default(fn() => $this->ownerRecord->vendor_id),

                                Forms\Components\DatePicker::make('bill_date')
                                    ->label('Tanggal Tagihan')
                                    ->required()
                                    ->default(now()),

                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Jatuh Tempo')
                                    ->required(),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'Draft' => 'Draft',
                                        'Open' => 'Open',
                                        'Paid' => 'Paid',
                                        'Cancelled' => 'Cancelled',
                                    ])
                                    ->default('Draft')
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->columnSpanFull()
                                    ->maxLength(65535),
                            ])->columns(2),

                        Forms\Components\Section::make('Item Tagihan')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->live()

                                    ->default(function (Forms\Set $set, $livewire) {
                                        $ownerRecord = $livewire->ownerRecord;

                                        if (!$livewire->getOwnerRecord()->purchaseBills()->count() && $ownerRecord->items->isNotEmpty()) {

                                            $initialItems = $ownerRecord->items->map(function ($item) {
                                                $qty = (float) $item->quantity;
                                                $price = (float) $item->unit_price;
                                                $subtotal = $qty * $price;
                                                return [
                                                    'material_id' => $item->material_id,
                                                    'quantity' => $qty,
                                                    'unit_price' => $price,
                                                    'sub_total' => $subtotal,
                                                ];
                                            })->toArray();

                                            $totalAmount = collect($initialItems)->sum(fn($item) => (float) $item['sub_total']);

                                            // Perbaikan: Set total amount di form data
                                            $set('total_amount', $totalAmount);

                                            return $initialItems;
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        $totalAmount = collect($state)->sum(function ($item) {
                                            $quantity = (float) ($item['quantity'] ?? 0);
                                            $price = (float) ($item['unit_price'] ?? 0);
                                            return $quantity * $price;
                                        });
                                        $set('total_amount', $totalAmount);
                                    })
                                    ->schema([
                                        Forms\Components\Select::make('material_id')
                                            ->relationship('material', 'name')
                                            ->label('Material')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live(),

                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(1)
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('unit_price')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('sub_total')
                                            ->numeric()
                                            ->readOnly()
                                            ->dehydrated()
                                            ->default(0)
                                            ->afterStateHydrated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $subtotal = (float) $get('quantity') * (float) $get('unit_price');
                                                $set('sub_total', $subtotal);
                                            })
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $subtotal = (float) $get('quantity') * (float) $get('unit_price');
                                                $set('sub_total', $subtotal);
                                            }),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull()
                                    ->deleteAction(
                                        fn(Forms\Components\Actions\Action $action) => $action->icon('heroicon-o-trash'),
                                    ),
                            ]),

                        Forms\Components\Section::make('Total')
                            ->schema([
                                Forms\Components\TextInput::make('total_amount')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    // Perbaikan: Tambahkan format mata uang di TextInput untuk tampilan yang benar
                                    ->mask(\Filament\Support\RawJs::make('$money($state)'))
                                    ->default(0.00),

                                Forms\Components\TextInput::make('paid_amount')
                                    ->label('Jumlah Sudah Dibayar')
                                    ->numeric()
                                    ->readOnly()
                                    ->mask(\Filament\Support\RawJs::make('$money($state)'))
                                    ->default(0.00),
                            ])->columns(2),

                    ])->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $totalAmount = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $totalAmount += $quantity * $price;
            }
        }

        // Jaminan Backend: Simpan total amount yang diformat dengan benar
        $data['total_amount'] = number_format($totalAmount, 2, '.', '');
        $data['paid_amount'] = 0.00;

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')->label('No. Tagihan')->searchable(),
                Tables\Columns\TextColumn::make('bill_date')->label('Tgl. Tagihan')->date(),
                Tables\Columns\TextColumn::make('due_date')->label('Jatuh Tempo')->date(),
                // PERBAIKAN: Gunakan money format untuk tampilan yang benar
                Tables\Columns\TextColumn::make('total_amount')->label('Total (Rp)')->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')->label('Terbayar (Rp)')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Open' => 'warning',
                        'Paid' => 'success',
                        'Cancelled' => 'danger',
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('Record Payment')
                    ->label('Catat Pembayaran')
                    ->visible(fn(?PurchaseBill $record) => $record && $record->status !== 'Paid' && $record->status !== 'Cancelled')
                    ->color('success')
                    ->icon('heroicon-o-credit-card')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah Dibayar')
                            ->numeric()
                            ->required()
                            ->default(fn(?PurchaseBill $record) => $record ? ((float) $record->total_amount - (float) $record->paid_amount) : 0)
                            ->maxValue(fn(?PurchaseBill $record) => $record ? ((float) $record->total_amount - (float) $record->paid_amount) : 0),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, PurchaseBill $record) {
                        $newPaidAmount = $record->paid_amount + $data['amount'];

                        $status = 'Open';
                        if ($newPaidAmount >= $record->total_amount) {
                            $status = 'Paid';
                        }

                        $record->update([
                            'paid_amount' => $newPaidAmount,
                            'status' => $status,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Pembayaran berhasil dicatat.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('printBill')
                    ->label('Cetak Tagihan')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    // Memanggil rute yang akan kita daftarkan di web.php
                    ->url(fn(PurchaseBill $record): string => route('filament.admin.purchase-bills.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}