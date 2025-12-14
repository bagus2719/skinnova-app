<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Filament\Resources\VendorResource\RelationManagers;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $modelLabel = 'Vendor';
    protected static ?string $pluralModelLabel = 'Daftar Vendor ';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar Vendor')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Vendor')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastVendor = Vendor::query()->orderByDesc('id')->first();
                                    $nextId = $lastVendor ? $lastVendor->id + 1 : 1;
                                    return 'VDR-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Pemasok')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Kontak dan Administrasi')
                    ->schema([
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Kontak Person')
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon')
                            ->tel()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tax_id')
                            ->label('NPWP / ID Pajak')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pemasok')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Kontak Person')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Non-Aktif')
                    ->default(true),
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
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
