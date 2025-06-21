<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('setсommandstelegram', function () {
    
    /** @var \DefStudio\Telegraph\Models\TelegraphBot $telegraphBot */

    $telegraphBot = \DefStudio\Telegraph\Models\TelegraphBot::find(1);
    $telegraphBot->registerCommands([
        'start' => 'Getting Started with the Bot',
        'order' => 'Create an order a taxi',
        'cancel' => 'Cancel all actions and clear cache'
    ])->send();


});
