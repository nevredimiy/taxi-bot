<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Handlers\WebhookHandler;

class Handler extends WebhookHandler
{
    public function hello()
    {
        $this->reply('Привет. Я учусь');
    }
}
