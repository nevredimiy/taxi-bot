<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

     // Метод позволяет перейти в список, после редактировании
     protected function getRedirectUrl(): string
     {
         // Перенаправление на список событий
         return $this->getResource()::getUrl('index');
     }
}
