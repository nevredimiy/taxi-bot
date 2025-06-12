<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Метод позволяет перейти в список, после редактировании
     protected function getRedirectUrl(): string
     {
         // Перенаправление на список событий
         return $this->getResource()::getUrl('index');
     }
}
