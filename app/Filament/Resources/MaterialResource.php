<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Number;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Bahan Baku';

    protected static ?string $pluralModelLabel = 'Bahan Baku';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar Material')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Material')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Material')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastMaterial = Material::query()->orderByDesc('id')->first();
                                    $nextId = $lastMaterial ? $lastMaterial->id + 1 : 1;
                                    return 'MAT-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\Select::make('unit')
                            ->label('Satuan Unit Dasar')
                            ->options([
                                'g' => 'Gram (g)',
                                'ml' => 'Mililiter (ml)',
                                'pcs' => 'Pcs',
                                'kg' => 'Kilogram (kg)',
                                'liter' => 'Liter (l)',
                            ])
                            ->required()
                            ->default('g'),
                    ]),

                Forms\Components\Section::make('Stok & Biaya')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('current_stock')
                            ->label('Stok Saat Ini')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->disabled(),

                        Forms\Components\TextInput::make('std_cost')
                            ->label('Biaya Standar per Unit (Rp)')
                            ->numeric()
                            ->step(0.01)
                            ->placeholder('Contoh: 15000.50')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Internal')
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit Dasar')
                    ->badge(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->badge()
                    ->color(fn($state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state > 0 => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('std_cost')
                    ->label('Biaya Standar')
                    ->formatStateUsing(fn(string $state): string => 'Rp ' . Number::format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
