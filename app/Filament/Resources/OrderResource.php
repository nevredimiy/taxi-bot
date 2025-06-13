<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use App\Models\Driver;
use App\Models\Client;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Exports\OrderExporter;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ExportBulkAction;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')
                    ->required()
                    ->options(Client::all()->pluck('full_name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('driver_id')
                    ->options(Driver::all()->pluck('full_name', 'id'))
                    ->searchable(),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'new' => 'new',
                        'accepted' => 'accepted',
                        'rejected' => 'rejected',
                        'completed' => 'completed'
                    ])
                    ->default('new'),
                Forms\Components\TextInput::make('route')
                    ->maxLength(255),
                Forms\Components\TextInput::make('budget')
                    ->minValue(0)
                    ->prefix('$')
                    ->numeric(),
                Forms\Components\Textarea::make('details')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('route')
                    ->searchable(),
                Tables\Columns\TextColumn::make('budget')
                    ->numeric()
                    ->sortable(),
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(OrderExporter::class)
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exporter(OrderExporter::class)
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
