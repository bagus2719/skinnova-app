<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Number;
use App\Filament\Resources\ProductResource\RelationManagers\BomsRelationManager;
use App\Services\CsvExporter;
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Manufacturing';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['production', 'inventory', 'sales']) ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Produk')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->columnSpan(2)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('sku')
                            ->label('SKU / Kode Produk')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastProduct = Product::query()->orderByDesc('id')->first();
                                    $nextId = $lastProduct ? $lastProduct->id + 1 : 1;
                                    return 'SKNV-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\Select::make('unit')
                            ->label('Satuan Unit Jual')
                            ->options([
                                'pcs' => 'Pcs',
                                'botol' => 'Botol',
                                'set' => 'Set',
                                'tube' => 'Tube',
                            ])
                            ->required()
                            ->default('pcs'),

                        Forms\Components\TextInput::make('current_stock')
                            ->label('Stok Produk Saat Ini')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('minimum_stock')
                            ->label('Stok Minimum (Alert)')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->helperText('Batas minimum untuk peringatan stok menipis')
                            ->required(),

                        Forms\Components\TextInput::make('selling_price')
                            ->label('Harga Jual (Rp)')
                            ->numeric()
                            ->step(0.01)
                            ->nullable(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Produk')
                            ->columnSpanFull()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Regulasi & Masa Simpan (BPOM)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bpom_number')
                            ->label('Nomor Registrasi BPOM')
                            ->placeholder('Contoh: NA18240101234')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('shelf_life_months')
                            ->label('Masa Simpan / Shelf Life')
                            ->numeric()
                            ->default(24)
                            ->suffix('Bulan')
                            ->helperText('Otomatis menghitung tanggal expired produk saat produksi selesai.')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bpom_number')
                    ->label('No. BPOM')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit Jual')
                    ->badge(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record, $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= ($record->minimum_stock ?? 0) => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Stok Min')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Harga Jual')
                    ->formatStateUsing(fn(string $state): string => 'Rp ' . Number::format($state, 2))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV / Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        $products = Product::orderBy('name')->get();

                        $headers = [
                            'Nama Produk',
                            'SKU',
                            'Unit',
                            'Stok Saat Ini',
                            'Stok Minimum',
                            'Harga Jual (Rp)',
                            'Status Aktif',
                            'Deskripsi',
                        ];

                        $rows = $products->map(function ($product) {
                            return [
                                $product->name,
                                $product->sku,
                                $product->unit,
                                $product->current_stock,
                                $product->minimum_stock ?? 0,
                                $product->selling_price,
                                $product->is_active ? 'Aktif' : 'Non-Aktif',
                                $product->description ?? '-',
                            ];
                        });

                        return CsvExporter::download(
                            'products-inventory-' . date('Y-m-d') . '.csv',
                            $headers,
                            $rows
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exportSelected')
                        ->label('Export Pilihan ke CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $headers = [
                                'Nama Produk',
                                'SKU',
                                'Unit',
                                'Stok Saat Ini',
                                'Stok Minimum',
                                'Harga Jual (Rp)',
                                'Status Aktif',
                                'Deskripsi',
                            ];

                            $rows = $records->map(function ($product) {
                                return [
                                    $product->name,
                                    $product->sku,
                                    $product->unit,
                                    $product->current_stock,
                                    $product->minimum_stock ?? 0,
                                    $product->selling_price,
                                    $product->is_active ? 'Aktif' : 'Non-Aktif',
                                    $product->description ?? '-',
                                ];
                            });

                            return CsvExporter::download(
                                'products-selected-' . date('Y-m-d') . '.csv',
                                $headers,
                                $rows
                            );
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BomsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

}
