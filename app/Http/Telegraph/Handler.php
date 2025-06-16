<?php

namespace App\Http\Telegraph;

use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;

class Handler extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Добро пожаловать!')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🚗 Регистрация водителя')->action('register_driver'),
            ]))->send();
    }

    public function register_driver(): void
    {
        // Устанавливаем первый шаг регистрации
        $this->chat->storage()->set('registration_step', 'full_name');
        $this->chat->message('Введите ваше полное имя (ФИО):')->send();
    }

    /**
     * Обрабатывает текстовые сообщения от пользователя.
     * @param Stringable $text
     */
    protected function handleChatMessage(Stringable $text): void
    {

        Log::info(json_encode($this->message->toArray(), JSON_UNESCAPED_UNICODE));
        
        $step = $this->chat->storage()->get('registration_step');

        // Если мы ожидаем фото, а получили текст, просим отправить фото.
        if (in_array($step, ['license_photo', 'car_photo'])) {
            $this->chat->message('Пожалуйста, на этом шаге отправьте фотографию, а не текст.')->send();
            return;
        }

        // Используем match для маршрутизации по шагам, которые ожидают текст.
        match ($step) {
            'full_name' => $this->handleFullName($text->toString()),
            'license_number' => $this->handleLicenseNumber($text->toString()),
            'car_model' => $this->handleCarModel($text->toString()),
            'country' => $this->handleCountry($text->toString()),
            'city' => $this->handleCity($text->toString()),
            default => $this->chat->message('Для начала работы, пожалуйста, нажмите "Регистрация водителя".')->send(),
        };
    }

    /**
     * Обрабатывает получение фото от пользователя.
     */
    public function handlePhoto(): void
    {
        $step = $this->chat->storage()->get('registration_step');
        // Получаем file_id из объекта сообщения
        $fileId = $this->message->photo()->last()->fileId();

        if ($step === 'license_photo') {
            $this->chat->storage()->set('license_photo_file_id', $fileId);
            $this->chat->storage()->set('registration_step', 'car_photo');
            $this->chat->message('Спасибо. Теперь отправьте фото автомобиля.')->send();
        } elseif ($step === 'car_photo') {
            $this->chat->storage()->set('car_photo_file_id', $fileId);
            // Если это последнее фото, сохраняем данные водителя
            $this->saveDriver();
        } else {
            // На случай, если фото прислали не на том шаге
            $this->chat->message('Я не ожидал получить фото на этом шаге.')->send();
        }
    }

    protected function handleFullName(string $text): void
    {
        $this->chat->storage()->set('full_name', $text);
        $this->chat->storage()->set('registration_step', 'license_number');
        $this->chat->message('Введите номер лицензии или гос. номер авто:')->send();
    }

    protected function handleLicenseNumber(string $text): void
    {
        $this->chat->storage()->set('license_number', $text);
        $this->chat->storage()->set('registration_step', 'car_model');
        $this->chat->message('Введите марку и модель автомобиля (например, Toyota Camry):')->send();
    }

    protected function handleCarModel(string $text): void
    {
        $this->chat->storage()->set('car_model', $text);
        $this->chat->storage()->set('registration_step', 'country');
        $this->chat->message('Введите страну:')->send();
    }

    protected function handleCountry(string $text): void
    {
        $this->chat->storage()->set('country', $text);
        $this->chat->storage()->set('registration_step', 'city');
        $this->chat->message('Введите город:')->send();
    }

    protected function handleCity(string $text): void
    {
        $this->chat->storage()->set('city', $text);
        $this->chat->storage()->set('registration_step', 'license_photo');
        $this->chat->message('Отлично! Теперь пришлите фото водительского удостоверения:')->send();
    }

    protected function saveDriver(): void
    {
        $data = $this->chat->storage()->all();
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
                'full_name' => $data['full_name'],
                'license_number' => $data['license_number'],
                'car_model' => $data['car_model'],
                'country' => $data['country'],
                'city' => $data['city'],
                'license_photo' => $licenseRelativePath, // Сохраняем относительный путь
                'car_photo' => $carRelativePath, // Сохраняем относительный путь
                'status' => 'pending',
            ]);

            // Очищаем хранилище после успешной регистрации
            $this->chat->storage()->clear();
            $this->chat->message('Регистрация успешно завершена! Ожидайте подтверждения. 🚗')->send();

        } catch (\Throwable $e) {
            // В случае ошибки сообщаем пользователю и логируем
            report($e); // Отправляем ошибку в систему логирования Laravel
            $this->chat->message('Произошла непредвиденная ошибка при сохранении данных. Попробуйте позже.')->send();
        }
    }
}
