<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Handler extends WebhookHandler
{
    public function hello()
    {
        
        Telegraph::message('hello world')
            ->keyboard(Keyboard::make()->buttons([
                Button::make("🗑️ Delete")->action("delete")->param('id', 1),  
                Button::make("📖 Mark as Read")->action("read")->param('id', 1),  
                Button::make("👀 Open")->url('https://test.it'),  
            ])->chunk(2))->send();
            }
}
