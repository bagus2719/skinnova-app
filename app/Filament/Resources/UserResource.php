<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Kelola Pengguna & Hak Akses';
    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Pengguna & Hak Akses';
    protected static ?int $navigationSort = 99;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Akun Pengguna')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email (Login)')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor WhatsApp / Telepon')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('role')
                            ->label('Peran / Hak Akses (Role)')
                            ->options([
                                'admin' => 'Administrator (Akses Penuh)',
                                'sales' => 'Sales (Penjualan, Pelanggan & DO)',
                                'purchasing' => 'Purchasing (Pengadaan, Vendor & PO)',
                                'inventory' => 'Gudang (Stok, Penerimaan, DO & Mutasi)',
                                'production' => 'Produksi (BOM, MO & Kartu Stok)',
                                'finance' => 'Finance (Invoice, Pembayaran & Laporan)',
                                'hr' => 'Human Resources (Karyawan & Jabatan)',
                            ])
                            ->default('admin')
                            ->required(),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Kosongkan jika tidak ingin mengubah password.' : null),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Akun Aktif')
                            ->default(true)
                            ->helperText('Pengguna non-aktif tidak dapat login ke sistem.')
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran (Role)')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin', 'super_admin' => 'danger',
                        'sales' => 'info',
                        'purchasing' => 'warning',
                        'inventory' => 'primary',
                        'production' => 'purple',
                        'finance' => 'success',
                        'hr' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin', 'super_admin' => 'Administrator',
                        'sales' => 'Sales',
                        'purchasing' => 'Purchasing',
                        'inventory' => 'Gudang',
                        'production' => 'Produksi',
                        'finance' => 'Finance',
                        'hr' => 'HR',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Filter Peran')
                    ->options([
                        'admin' => 'Administrator',
                        'sales' => 'Sales',
                        'purchasing' => 'Purchasing',
                        'inventory' => 'Gudang',
                        'production' => 'Produksi',
                        'finance' => 'Finance',
                        'hr' => 'HR',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Akun'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
