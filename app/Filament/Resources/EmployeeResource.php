<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
use Filament\Forms\Get;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Employee';
    protected static ?string $modelLabel = 'Employee';
    protected static ?string $pluralModelLabel = 'Employee';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Employee Data')
                    ->tabs([
                        // TAB 1: Informasi Dasar & Pribadi
                        Forms\Components\Tabs\Tab::make('Informasi Dasar')
                            ->schema([
                                Forms\Components\TextInput::make('employee_id')
                                    ->label('ID Karyawan')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    // Penomoran otomatis EMP-0001
                                    ->default(function (string $operation) {
                                        if ($operation === 'create') {
                                            $lastEmployee = Employee::query()->orderByDesc('id')->first();
                                            $nextId = $lastEmployee ? $lastEmployee->id + 1 : 1;
                                            return 'EMP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                        }
                                        return null;
                                    })
                                    ->readOnly()
                                    ->dehydrated(true)
                                    ->columnSpan(1),
                                
                                Forms\Components\TextInput::make('first_name')
                                    ->label('Nama Depan')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                    
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Nama Belakang')
                                    ->maxLength(255)
                                    ->nullable()
                                    ->columnSpan(1),
                                    
                                Forms\Components\Select::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->options([
                                        'Laki-laki' => 'Laki-laki',
                                        'Perempuan' => 'Perempuan',
                                    ])
                                    ->nullable(),
                                    
                                Forms\Components\DatePicker::make('date_of_birth')
                                    ->label('Tanggal Lahir')
                                    ->nullable(),

                                // Field kontak di tab ini
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel()
                                    ->maxLength(255)
                                    ->nullable(),
                                    
                                Forms\Components\TextInput::make('email')
                                    ->label('Email Kantor')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ])->columns(3),

                        // TAB 2: Informasi Pekerjaan (Menggunakan Relasi)
                        Forms\Components\Tabs\Tab::make('Informasi Pekerjaan')
                            ->schema([
                                Forms\Components\Select::make('job_position_id')
                                    ->label('Jabatan / Posisi')
                                    ->relationship('jobPosition', 'name', fn (Builder $query) => $query->orderBy('name'))
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->live() // Agar bisa memuat data Department
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $jobPosition = \App\Models\JobPosition::find($state);
                                            // Tampilkan nama Department
                                            $set('department_name', $jobPosition?->department->name);
                                        } else {
                                            $set('department_name', null);
                                        }
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('department_name')
                                    ->label('Departemen')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(1),

                                Forms\Components\DatePicker::make('hire_date')
                                    ->label('Tanggal Mulai Kerja')
                                    ->nullable(),
                                
                                Forms\Components\Select::make('status')
                                    ->label('Status Karyawan')
                                    ->options([
                                        'Active' => 'Aktif',
                                        'On Leave' => 'Cuti',
                                        'Terminated' => 'Berhenti',
                                    ])
                                    ->default('Active')
                                    ->required(),
                            ])->columns(3),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('full_name') 
                    ->label('Nama Karyawan')
                    ->getStateUsing(fn (Employee $record) => $record->full_name) 
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                
                // Menggunakan relasi untuk menampilkan Jabatan dan Departemen
                Tables\Columns\TextColumn::make('jobPosition.name')
                    ->label('Jabatan')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('jobPosition.department.name')
                    ->label('Departemen')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('hire_date')
                    ->label('Tgl. Masuk')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'On Leave' => 'warning',
                        'Terminated' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('job_position_id')
                    ->relationship('jobPosition', 'name')
                    ->label('Filter Berdasarkan Jabatan'),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Active' => 'Aktif',
                        'On Leave' => 'Cuti',
                        'Terminated' => 'Berhenti',
                    ]),
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
