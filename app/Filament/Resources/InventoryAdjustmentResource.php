<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryAdjustmentResource\Pages;
use App\Filament\Resources\InventoryAdjustmentResource\RelationManagers;
use App\Models\InventoryAdjustment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Material;
use Illuminate\Support\Number;

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $label = 'Penyesuaian Stok Material';
    
    protected static ?string $navigationGroup = 'Manufacturing';    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Penyesuaian')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('reference_no')
                            ->label('Nomor Referensi')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastAdjustment = InventoryAdjustment::query()->orderByDesc('id')->first();
                                    $nextId = $lastAdjustment ? $lastAdjustment->id + 1 : 1;
                                    return 'ADJ-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->disabled()
                            ->dehydrated(true)
                            ->required(),

                        Forms\Components\Select::make('material_id')
                            ->label('Bahan Baku')
                            ->options(Material::pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Tipe Transaksi')
                            ->options([
                                'IN' => 'Masuk (Penambahan Stok)',
                                'OUT' => 'Keluar (Pengurangan Stok)',
                            ])
                            ->required()
                            ->native(false),
                    ]),

                Forms\Components\Section::make('Kuantitas & Alasan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->step(0.0001)
                            ->minValue(0.0001)
                            ->required()
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                if ($state !== null) {
                                    $component->state(Number::format($state, 2));
                                }
                            }),

                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Penyesuaian')
                            ->columnSpan('full')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Ref. No')
                    ->searchable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Bahan Baku')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'IN' => 'Masuk',
                        'OUT' => 'Keluar',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(function (InventoryAdjustment $record) { 
                        $material = $record->material;
                        return ' ' . ($material?->unit ?? 'Unit');
                    }),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }
}
