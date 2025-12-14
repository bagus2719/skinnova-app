<?php

namespace App\Filament\Resources\SalesInvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class SalesPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'salesPayments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pembayaran')
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'SP-' . date('Ymd') . '-' . Str::random(5))
                            ->disabledOn('edit')
                            ->maxLength(255),
                        
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah Pembayaran (Rp)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('IDR')
                            ->default(function (RelationManager $livewire): float {
                                $invoice = $livewire->getOwnerRecord();
                                
                                // Hitung total yang sudah dibayar
                                $totalPaid = $invoice->salesPayments()->sum('amount');
                                
                                // Hitung sisa tagihan
                                $remaining = $invoice->grand_total - $totalPaid;
                                
                                return max(0.00, $remaining);
                            }),

                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'Cash' => 'Cash',
                                'Bank Transfer' => 'Bank Transfer',
                                'Credit Card' => 'Credit Card',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->default('Bank Transfer'),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->maxLength(65535),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('No. Pembayaran')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah (Rp)')
                    ->money('IDR', 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
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