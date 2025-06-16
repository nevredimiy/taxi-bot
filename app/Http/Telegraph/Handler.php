<?php

namespace App\Http\Telegraph;

use App\Models\Driver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\WelcomeClientMail;
use Illuminate\Support\Facades\Mail;


class Handler extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Welcome!')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🚗 Driver registration')->action('register_driver'),
                Button::make('🙋 Client registration')->action('register_client'),
            ]))->send();
    }

    public function register_driver(): void
    {
        // Устанавливаем первый шаг регистрации
        $this->chat->storage()->set('registration_step', 'driver_email');
        $this->chat->message('Please enter your email:')->send();
    }

    public function register_client(): void
    {
        $this->chat->storage()->set('registration_step', 'client_email');
        $this->chat->message('Please enter your email:')->send();
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

            case 'client_email':

                $email = $text;
                if (User::where('email', $email)->exists()) {
                    $this->chat->message('🚫 This email is already taken. Try another one.')->send();
                    return;
                }

                $this->chat->storage()->set('client_email', $text);
                $this->chat->storage()->set('registration_step', 'client_first_name');
                $this->chat->message('Please enter your first name:')->send();
                break;

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

            case 'driver_email':
                $email = $text;
                if (User::where('email', $email)->exists()) {
                    $this->chat->message('🚫 This email is already taken. Try another one.')->send();
                    return;
                }

                $this->chat->storage()->set('driver_email', $email);
                $this->chat->storage()->set('registration_step', 'driver_first_name');
                $this->chat->message('Please enter your first name:')->send();
                break;

            case 'driver_first_name':
                $this->chat->storage()->set('driver_first_name', $text);
                $this->chat->storage()->set('registration_step', 'driver_last_name');
                $this->chat->message('Enter your last name:')->send();
                break;

            case 'driver_last_name':
                $this->chat->storage()->set('driver_last_name', $text);
                $this->chat->storage()->set('registration_step', 'driver_license_number');
                $this->chat->message('Enter the make and model of your vehicle (e.g. Toyota Camry):')->send();
                break;

            case 'driver_license_number':
                $this->chat->storage()->set('driver_license_number', $text);
                $this->chat->storage()->set('registration_step', 'driver_car_model');
                $this->chat->message('Enter country:')->send();
                break;

            case 'driver_car_model':
                $this->chat->storage()->set('driver_car_model', $text);
                $this->chat->storage()->set('registration_step', 'driver_city');
                $this->chat->message('Enter city:')->send();
                break;

            case 'driver_city':
                $this->chat->storage()->set('driver_city', $text);
                $this->chat->storage()->set('registration_step', 'license_photo');
                $this->chat->message('Great! Now send a photo of your driver\'s license:')->send();
                break;

            case 'license_photo':
                $this->chat->storage()->set('license_photo', $text);
                $filename = 'license_' . now()->timestamp . '.jpg';
                $path = 'license_photos/' . $filename;
                Telegraph::store($this->message->photos()->last(), Storage::path('public/' . $path));
                // Сохраняем в хранилище (например, в сессию)
                $this->chat->storage()->set('license_photo', 'storage/' . $path);
                $this->chat->storage()->set('registration_step', 'car_photo');
                $this->chat->message('License photo saved ✅ Now send a photo of your car:')->send();
                break;
            
            case 'car_photo':
                $this->chat->storage()->set('car_photo', $text);
                $filename = 'car_' . now()->timestamp . '.jpg';
                $path = 'car_photos/' . $filename;
                Telegraph::store($this->message->photos()->last(), Storage::path('public/' . $path));
                $this->chat->storage()->set('car_photo', 'storage/' . $path);
                $this->saveDriver(); // Финальный шаг
                break;

            default:
                $this->chat->message('Use /start to begin.')->send();
        }
    }

    protected function saveClient(): void
    {

        $first_name = $this->chat->storage()->get('client_first_name');
        $last_name = $this->chat->storage()->get('client_last_name');
        $email = $this->chat->storage()->get('client_email');
        $password = Str::random(10);

        $user = User::create([
            'name' => $first_name . '_' . $last_name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'client',
        ]);

        Mail::to($user->email)->send(new WelcomeClientMail($user, $password));

        $client = new Client();
        $client->first_name = $this->chat->storage()->get('client_first_name');
        $client->last_name = $this->chat->storage()->get('client_last_name');
        $client->phone = $this->chat->storage()->get('client_phone');
        $client->country = $this->chat->storage()->get('client_country');
        $client->city = $this->chat->storage()->get('client_city');
        $client->telegram_id = $this->message->from()->id();
        $client->user_id = $user->id;

        $client->save();

        $this->chat->message('✅ You have been successfully registered as a client!')->send();

        // Clear storage
        // $this->chat->storage()->forget;
    }

    /**
     * Обрабатывает получение фото от пользователя.
     */
    public function handlePhoto(\DefStudio\Telegraph\DTO\Photo $photo): void
    {
        $step = $this->chat->storage()->get('registration_step');

        if ($step === 'license_photo') {
            // Генерируем уникальное имя файла
            $filename = 'license_' . now()->timestamp . '.jpg';

            // Сохраняем фото в папку
            $path = 'license_photos/' . $filename;
            Telegraph::store($photo, Storage::path('public/' . $path));

            // Сохраняем в хранилище (например, в сессию)
            $this->chat->storage()->set('license_photo', 'storage/' . $path);
            $this->chat->storage()->set('registration_step', 'car_photo');

            $this->chat->message('License photo saved ✅ Now send a photo of your car:')->send();
        } elseif ($step === 'car_photo') {
            $filename = 'car_' . now()->timestamp . '.jpg';
            $path = 'car_photos/' . $filename;
            Telegraph::store($photo, Storage::path('public/' . $path));

            $this->chat->storage()->set('car_photo', 'storage/' . $path);
            $this->saveDriver(); // Финальный шаг
        }
    }


    protected function saveDriver(): void
    {
        // $chatId = $this->chat->chatId;
        $chatId = $this->message->from()->id();

        $data = [
            'first_name' => $this->chat->storage()->get('driver_first_name'),
            'last_name' => $this->chat->storage()->get('driver_last_name'),
            'license_number' => $this->chat->storage()->get('driver_license_number'),
            'car_model' => $this->chat->storage()->get('driver_car_model'),
            'city' => $this->chat->storage()->get('driver_city'),
            'license_photo' => $this->chat->storage()->get('license_photo'),
            'car_photo' => $this->chat->storage()->get('car_photo'),
        ];

        $email = $this->chat->storage()->get('driver_email');

        // Генерация имени и пароля
        $name = strtolower($data['first_name'] . '_' . $data['last_name']);
        $password = Str::random(10);

        // Создание пользователя
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'driver',
        ]);

        // Создание водителя
        Driver::create([
            'user_id' => $user->id,
            'telegram_id' => $chatId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'license_number' => $data['license_number'],
            'car_model' => $data['car_model'],
            'city' => $data['city'],
            'country' => $this->chat->storage()->get('driver_country'),
            'license_photo' => $data['license_photo'],
            'car_photo' => $data['car_photo'],
            'status' => 'pending',
        ]);

        // Отправка письма
        Mail::to($user->email)->send(new WelcomeClientMail($user, $password));

        $this->chat->message('🎉 You have been registered as a driver! Please wait for approval.')->send();
        // $this->chat->storage()->forgetAll(); // Очистка сессии
    }
}
