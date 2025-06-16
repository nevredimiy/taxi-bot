<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Filament\Resources\DriverResource\RelationManagers;
use App\Models\Driver;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\Section;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->required()
                    ->options(User::all()->pluck('name', 'id')),
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(255),
                 Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'pending',
                        'active' => 'active',
                        'blocked' => 'blocked',
                    ])
                    ->default('pending'),

                Section::make('Информация автомобиля')
                        ->schema([
                            Forms\Components\TextInput::make('license_number')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('car_model')
                                ->maxLength(255),
                            Forms\Components\FileUpload::make('license_photo')
                                ->image()
                                ->disk('public')
                                ->maxSize(2600)
                                ->directory('license_photo')
                                ->deleteUploadedFileUsing(fn ($record) => 
                                    $record->license_photo ? unlink(storage_path('app/public/' . $record->license_photo)) : null
                                ),
                            Forms\Components\FileUpload::make('car_photo')
                                ->image()
                                ->disk('public')
                                ->maxSize(2600)
                                ->directory('car_photo')
                                ->deleteUploadedFileUsing(fn ($record) => 
                                    $record->car_photo ? unlink(storage_path('app/public/' . $record->car_photo)) : null
                                ),
                           
                        ])
                        ->columns(2),
               
               
                Forms\Components\TextInput::make('country')
                    ->maxLength(255),
                Forms\Components\TextInput::make('city')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable(),
                 Tables\Columns\TextColumn::make('last_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('license_number')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('license_photo')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('car_model')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('car_photo')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                ImageColumn::make('license_photo')
                    ->disk('public')
                    ->height(50)
                    ->width(50)
                    ->label('Лиценция'), 
                ImageColumn::make('car_photo')
                    ->disk('public')
                    ->height(50)
                    ->width(50)
                    ->label('Фото автомобиля'), 
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
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
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
