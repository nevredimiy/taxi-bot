<?php

namespace App\Telegraph;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Handler extends WebhookHandler
{
    protected $driver = [];

    public function hello()
    {
        
        Telegraph::message('hello world')
            ->keyboard(Keyboard::make()->buttons([
                Button::make("🗑️ Delete")->action("delete")->param('id', 1),  
                Button::make("📖 Mark as Read")->action("read")->param('id', 1),  
                Button::make("👀 Open")->url('https://test.it'),  
            ])->chunk(2))->send();
    }

    public function start(): void
    {
        $this->chat->message('Добро пожаловать!')->keyboard(
            Keyboard::make()->row([
                Keyboard::make()->buttons([
                    Button::make('🚗 Регистрация водителя')->action('register_driver')
                ])
            ])
        )->send();
    }

    public function register_driver(): void
    {

        $this->chat->storage()->set('registration_step', 'full_name');

        $this->chat->message('Введите ваше полное имя:')->send();
    }

    public function getChatMessage(string $text): void
    {
        $step = $this->chat->storage()->get('registration_step');

        match ($step) {
            'full_name' => $this->handleFullName($text),
            'license_number' => $this->handleLicenseNumber($text),
            'car_model' => $this->handleCarModel($text),
            'country' => $this->handleCountry($text),
            'city' => $this->handleCity($text),
            'license_photo' => $this->askForLicensePhoto(),
            // 'car_photo' => $this->askForCarPhoto(),
            default => $this->chat->message('Нажмите "Регистрация водителя" для начала.')->send(),
        };
    }

    protected function handleFullName(string $text): void
    {
        $this->chat->storage()->set('full_name', $text)->set('registration_step', 'license_number');
        $this->chat->message('Введите номер лицензии или авто:')->send();
    }

    protected function handleLicenseNumber(string $text): void
    {
        $this->chat->storage()->set('license_number', $text)->set('registration_step', 'car_model');
        $this->chat->message('Введите марку автомобиля:')->send();
    }

    protected function handleCarModel(string $text): void
    {
        $this->chat->storage()->set('car_model', $text)->set('registration_step', 'country');
        $this->chat->message('Введите страну:')->send();
    }

    protected function handleCountry(string $text): void
    {
        $this->chat->storage()->set('country', $text)->set('registration_step', 'city');
        $this->chat->message('Введите город:')->send();
    }

    protected function handleCity(string $text): void
    {
        $this->chat->storage()->set('city', $text)->set('registration_step', 'license_photo');
        $this->chat->message('Пришлите фото водительского удостоверения:')->send();
    }

    protected function askForLicensePhoto(): void
    {
        $this->chat->storage()->set('registration_step', 'car_photo');
        $this->chat->message('Теперь отправьте фото автомобиля:')->send();
    }

    public function handlePhoto(string $fileId, array $photoSizes): void
    {
        $step = $this->chat->storage()->get('registration_step');

        if ($step === 'license_photo') {
            $this->chat->storage()->set('license_photo_file_id', $fileId)->set('registration_step', 'car_photo');
            $this->chat->message('Спасибо. Теперь отправьте фото автомобиля.')->send();
        } elseif ($step === 'car_photo') {
            $this->chat->storage()->set('car_photo_file_id', $fileId);
            $this->saveDriver();
        }
    }

    protected function saveDriver(): void
    {
        $data = $this->chat->storage();

        // Скачиваем фото
        $licensePath = Telegraph::downloadFile($data['license_photo_file_id'], storage_path("app/public/img/license_photo/{$data['telegram_id']}_license.jpg"));
        $carPath = Telegraph::downloadFile($data['car_photo_file_id'], storage_path("app/public/img/car_photo/{$data['telegram_id']}_car.jpg"));

        \App\Models\Driver::create([
            'user_id' => null, // или по логике авторизации
            'full_name' => $data['full_name'],
            'license_number' => $data['license_number'],
            'car_model' => $data['car_model'],
            'country' => $data['country'],
            'city' => $data['city'],
            'license_photo' => 'img/license_photo/' . basename($licensePath),
            'car_photo' => 'img/car_photo/' . basename($carPath),
            'status' => 'pending',
        ]);

        $this->chat->storage()->clear();
        $this->chat->message('Регистрация завершена! 🚗')->send();
    }
}
