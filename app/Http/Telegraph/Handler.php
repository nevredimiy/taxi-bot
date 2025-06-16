<?php

namespace App\Http\Telegraph;

use App\Models\Driver;
use App\Models\Client;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Handler extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Welcome!')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🚗 Driver registration')->action('register_driver'),
                Button::make('Client registration')->action('register_client'),
            ]))->send();
    }

    public function register_driver(): void
    {
        // Устанавливаем первый шаг регистрации
        $this->chat->storage()->set('registration_step', 'first_name');
        $this->chat->message('Please enter your first name:')->send();
    }

    public function register_client(): void
    {
        $this->chat->storage()->set('registration_step', 'client_first_name');
        $this->chat->message('Please enter your first name:')->send();
    }

    /**
     * Обрабатывает текстовые сообщения от пользователя.
     * @param Stringable $text
     */
    protected function handleChatMessage(Stringable $text): void
    {

        Log::info(json_encode($this->message->toArray(), JSON_UNESCAPED_UNICODE));
        
        $step = $this->chat->storage()->get('registration_step');

        switch ($step) {
            case 'client_first_name':
                $this->chat->storage()->set('client_first_name', $text);
                $this->chat->storage()->set('registration_step', 'client_last_name');
                $this->chat->message('Enter your last name:')->send();
                break;

            case 'client_last_name':
                $this->chat->storage()->set('client_last_name', $text);
                $this->chat->storage()->set('registration_step', 'client_phone');
                $this->chat->message('Enter your phone number:')->send();
                break;

            case 'client_phone':
                $this->chat->storage()->set('client_phone', $text);
                $this->chat->storage()->set('registration_step', 'client_country');
                $this->chat->message('Enter your country:')->send();
                break;

            case 'client_country':
                $this->chat->storage()->set('client_country', $text);
                $this->chat->storage()->set('registration_step', 'client_city');
                $this->chat->message('Enter your city:')->send();
                break;

            case 'client_city':
                $this->chat->storage()->set('client_city', $text);
                $this->saveClient();
                break;

            default:
                $this->chat->message('Use /start to begin.')->send();
        }
        

        // Если мы ожидаем фото, а получили текст, просим отправить фото.
        if (in_array($step, ['license_photo', 'car_photo'])) {
            $this->chat->message('Пожалуйста, на этом шаге отправьте фотографию, а не текст.')->send();
            return;
        }

        // Используем match для маршрутизации по шагам, которые ожидают текст.
        match ($step) {
            'first_name' => $this->handleFirstName($text->toString()),
            'last_name' => $this->handleLastName($text->toString()),
            'license_number' => $this->handleLicenseNumber($text->toString()),
            'car_model' => $this->handleCarModel($text->toString()),
            'country' => $this->handleCountry($text->toString()),
            'city' => $this->handleCity($text->toString()),
            default => $this->chat->message('Для начала работы, пожалуйста, нажмите "Регистрация водителя".')
                ->keyboard(Keyboard::make()->buttons([
                    Button::make('🚗 Driver registration')->action('register_driver'),
                ]))
                ->send(),
        };
    }

    protected function saveClient(): void
    {
        $client = new Client();
        $client->first_name = $this->chat->storage()->get('client_first_name');
        $client->last_name = $this->chat->storage()->get('client_last_name');
        $client->phone = $this->chat->storage()->get('client_phone');
        $client->country = $this->chat->storage()->get('client_country');
        $client->city = $this->chat->storage()->get('client_city');
        $client->telegram_id = $this->message->from()->id();

        $client->save();

        $this->chat->message('✅ You have been successfully registered as a client!')->send();

        // Clear storage
        $this->chat->storage()->forget;
    }

    /**
     * Обрабатывает получение фото от пользователя.
     */
    public function handlePhoto(): void
    {
        $step = $this->chat->storage()->get('registration_step');
        // Получаем file_id из объекта сообщения
        $fileId = $this->message->photos()->last()->id();

        if ($step === 'license_photo') {
            $this->chat->storage()->set('license_photo_file_id', $fileId);
            $this->chat->storage()->set('registration_step', 'car_photo');
            $this->chat->message('Thank you. Now send a photo of the car.')->send();
        } elseif ($step === 'car_photo') {
            $this->chat->storage()->set('car_photo_file_id', $fileId);
            // Если это последнее фото, сохраняем данные водителя
            $this->saveDriver();
        } else {
            // На случай, если фото прислали не на том шаге
            $this->chat->message('Я не ожидал получить фото на этом шаге.')->send();
        }
    }

    protected function handleFirstName(string $text): void
    {
        $this->chat->storage()->set('first_name', $text);
        $this->chat->storage()->set('registration_step', 'license_number');
        $this->chat->message('Enter your last name:')->send();
    }

    protected function handleLastName(string $text): void
    {
        $this->chat->storage()->set('last_name', $text);
        $this->chat->storage()->set('registration_step', 'license_number');
        $this->chat->message('Enter the license number or state registration number of the car:')->send();
    }

    protected function handleLicenseNumber(string $text): void
    {
        $this->chat->storage()->set('license_number', $text);
        $this->chat->storage()->set('registration_step', 'car_model');
        $this->chat->message('Enter the make and model of your vehicle (e.g. Toyota Camry):')->send();
    }

    protected function handleCarModel(string $text): void
    {
        $this->chat->storage()->set('car_model', $text);
        $this->chat->storage()->set('registration_step', 'country');
        $this->chat->message('Enter country:')->send();
    }

    protected function handleCountry(string $text): void
    {
        $this->chat->storage()->set('country', $text);
        $this->chat->storage()->set('registration_step', 'city');
        $this->chat->message('Enter city:')->send();
    }

    protected function handleCity(string $text): void
    {
        $this->chat->storage()->set('city', $text);
        $this->chat->storage()->set('registration_step', 'license_photo');
        $this->chat->message('Great! Now send a photo of your driver\'s license:')->send();
    }

    protected function saveDriver(): void
    {
        $data = $this->chat->storage()->get;
        $chatId = $this->chat->chat_id; // Используем ID чата для уникальности

        try {
            // Скачиваем фото
            $licenseFileId = $data['license_photo_file_id'];
            $carFileId = $data['car_photo_file_id'];
            
            // Определяем относительные пути для сохранения в БД
            $licenseRelativePath = "img/license_photo/{$chatId}_license.jpg";
            $carRelativePath = "img/car_photo/{$chatId}_car.jpg";

            // Скачиваем файлы напрямую в public storage
            Telegraph::download($licenseFileId, Storage::path("public/{$licenseRelativePath}"));
            Telegraph::download($carFileId, Storage::path("public/{$carRelativePath}"));

            Driver::create([
                'user_id' => null, // или по логике авторизации
                'telegram_id' => $chatId, // Сохраняем ID для связи
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'license_number' => $data['license_number'],
                'car_model' => $data['car_model'],
                'country' => $data['country'],
                'city' => $data['city'],
                'license_photo' => $licenseRelativePath, // Сохраняем относительный путь
                'car_photo' => $carRelativePath, // Сохраняем относительный путь
                'status' => 'pending',
            ]);

            // Очищаем хранилище после успешной регистрации
            // $this->chat->storage()->clear();
            $this->chat->message('Registration completed successfully! Wait for confirmation. 🚗')->send();

        } catch (\Throwable $e) {
            // В случае ошибки сообщаем пользователю и логируем
            report($e); // Отправляем ошибку в систему логирования Laravel
            $this->chat->message('Произошла непредвиденная ошибка при сохранении данных. Попробуйте позже.')->send();
        }
    }
}
