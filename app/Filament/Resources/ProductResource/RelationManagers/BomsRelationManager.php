<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Material;
use Illuminate\Support\Number;

class BomsRelationManager extends RelationManager
{
    protected static string $relationship = 'boms';
    protected static ?string $title = 'Komponen Bahan Baku (BOM)';
    protected static ?string $label = 'Komponen';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('material_id')
                    ->label('Bahan Baku')
                    ->required()
                    ->searchable()
                    ->options(Material::pluck('name', 'id'))
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $material = Material::find($state);
                            $set('unit', $material?->unit ?? '');
                        }
                    })
                    ->columnSpan(2)
                    ->reactive(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Kuantitas per Produk')
                    ->numeric()
                    ->step(0.01)
                    ->required()
                    ->default(0)
                    ->minValue(0.01)
                    ->reactive(),

                Forms\Components\TextInput::make('unit')
                    ->label('Satuan Resep')
                    ->maxLength(10)
                    ->disabled()
                    ->dehydrated(true),

                Forms\Components\TextInput::make('cost_per_item')
                    ->label('Biaya Material (Rp)')
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->afterStateHydrated(function ($set, callable $get) {
                        $this->calculateCost($set, $get);
                    })
                    ->afterStateUpdated(function ($set, callable $get) {
                        $this->calculateCost($set, $get);
                    }),

            ])
            ->columns(4);
    }

    private function calculateCost($set, $get)
    {
        $materialId = $get('material_id');
        $quantity = (float) $get('quantity');

        if (!$materialId || $quantity <= 0) {
            $set('cost_per_item', '0.00');
            return;
        }

        $material = Material::find($materialId);

        if (!$material || $material->std_cost === null) {
            $set('cost_per_item', '0.00');
            return;
        }

        $cost = $quantity * $material->std_cost;

        $set('cost_per_item', Number::format($cost, 2));
    }


    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                Tables\Columns\TextColumn::make('material.name')
                    ->label('Bahan Baku'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn($record) => $record->material?->unit ? ' ' . $record->material->unit : ''),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit Resep')
                    ->badge(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya Material')
                    ->getStateUsing(function ($record) {
                        if ($record->material) {
                            $cost = $record->quantity * $record->material->std_cost;
                            return 'Rp ' . Number::format($cost, 2);
                        }
                        return 'Rp 0.00';
                    }),
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
