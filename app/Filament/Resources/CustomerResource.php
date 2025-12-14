<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Customer')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Customer')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Customer')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(function (string $operation) {
                                if ($operation === 'create') {
                                    $lastCustomer = Customer::query()->orderByDesc('id')->first();
                                    $nextId = $lastCustomer ? $lastCustomer->id + 1 : 1;
                                    return 'CUS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                }
                                return null;
                            })
                            ->readOnly()
                            ->dehydrated(true)
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Kontak Person')
                            ->maxLength(255)
                            ->nullable(),
                            
                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->columnSpanFull()
                            ->nullable(),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false)
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Customer')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Kontak Person')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->nullable()
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif')
                    ->default(true),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
