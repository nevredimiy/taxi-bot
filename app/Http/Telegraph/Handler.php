<?php

namespace App\Http\Telegraph;

use App\Models\Driver;
use App\Models\Client;
use App\Models\User;
use App\Models\Order;
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
use DefStudio\Telegraph\DTO\Photo;

use DefStudio\Telegraph\Models\TelegraphBot;

TelegraphBot::first()->registerCommands([
    'start' => 'Getting Started with the Bot',
    'order' => 'Create an order a taxi',
    'driver' => 'Driver registration',
    'cancel' => 'Cancel all actions and clear cache'
]);


class Handler extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Welcome!')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('🚗 Driver registration')->action('register_driver'),
                Button::make('🙋 Client registration')->action('register_client'),
                Button::make('📝 Create order')->action('create_order')
            ]))->send();
    }

    public function order(): void
    {
        $this->create_order();
    }

    public function driver(): void
    {
        $this->register_driver();
    }


    public function register_driver(): void
    {
        $this->chat->storage()
            ->forget('registration_step')
            ->forget('order_step')
            ->set('registration_step', 'driver_email');

        $this->chat->message('Please enter your email:')->send();
    }

    public function register_client(): void
    {
        $telegramId = $this->chat->chat_id;

        $user = User::where('telegram_id', $telegramId)->first();

       // Если пользователь уже зарегистрирован как клиент
        if ($user && $user->role === 'client' && $user->client) {
            $this->chat
                ->message('⚠️ You are already registered as a client. What would you like to do?')
                ->keyboard(
                    Keyboard::make()->buttons([
                        Button::make('🔄 Update info')->action('update_client_info'),
                        Button::make('📝 Create order')->action('create_order'),
                    ])
                )
                ->send();
            return;
        }

        $this->chat->storage()
            ->forget('registration_step')
            ->forget('order_step')
            ->set('registration_step', 'client_email');

        $this->chat->message('Please enter your email:')->send();
    }

    public function create_order(): void
    {
        $telegramId = $this->chat->chat_id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'client') {
            $this->chat->message('❌ You are not registered as a client.')
                ->keyboard(
                    Keyboard::make()->buttons([
                        Button::make('🙋 Client registration')->action('register_client'),
                    ])
                )
                ->send();
            return;
        }
        $this->chat->storage()
            ->forget('registration_step')
            ->forget('order_step')
            ->set('order_step', 'pickup_address');

        $this->chat->message('Order registration has begun! \n Enter pickup address:')->send();
    }

    /**
     * Обрабатывает текстовые сообщения от пользователя.
     * @param Stringable $text
     */
    protected function handleChatMessage(Stringable $text): void
    {
        Log::info(json_encode($this->message->toArray(), JSON_UNESCAPED_UNICODE));

        $registrationStep = $this->chat->storage()->get('registration_step');

        if (Str::startsWith($registrationStep, 'client_update_')) {
            $this->handleUpdateStep($registrationStep, $text);
            return;
        }

        $orderStep = $this->chat->storage()->get('order_step');
        // $updateStep = $this->chat->storage()->get('client_update_first_name');

        if ($registrationStep) {
            $this->handleRegistrationStep($registrationStep, $text);
            return;
        }

        if ($orderStep) {
            $this->handleOrderStep($orderStep, $text);
            return;
        }

        // if ($updateStep) {
        //     $this->handleUpdateStep($updateStep, $text);
        //     return;
        // }

        $this->chat->message('Use /start to begin.')->send();
    }

    protected function handleRegistrationStep(string $step, Stringable $text): void
    {
        switch ($step) {
            // Регистрация клиента
            case 'client_email':
                $email = $text;
                $domain = substr(strrchr($email, "@"), 1);
                // 1. Проверка корректности email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->chat->message('❌ Invalid email format.')->send();
                    return;
                }
                // 2. Проверка DNS (наличие MX-записей у домена)
                if (!checkdnsrr($domain, 'MX')) {
                    $this->chat->message("❌ Email domain '$domain' does not accept mail.")->send();
                    return;
                }
                // 3. Проверка уникальности
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
            // Регистрация водителя
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
                $this->chat->message('Enter license number:')->send();
                break;

            case 'driver_license_number':
                $this->chat->storage()->set('driver_license_number', $text);
                $this->chat->storage()->set('registration_step', 'driver_car_model');
                $this->chat->message('Enter the make and model of your vehicle (e.g. Toyota Camry):')->send();
                break;

            case 'driver_car_model':
                $this->chat->storage()->set('driver_car_model', $text);
                $this->chat->storage()->set('registration_step', 'driver_country');
                $this->chat->message('Enter country:')->send();
                break;

            case 'driver_country':
                $this->chat->storage()->set('driver_country', $text);
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
                $this->handlePhoto($this->message->photos()->last());
                break;

            case 'car_photo':
                $this->chat->storage()->set('car_photo', $text);
                $this->handlePhoto($this->message->photos()->last());
                break;
        }
    }

    protected function handleOrderStep(string $step, Stringable $text): void
    {
        switch ($step) {
            case 'pickup_address':
                $this->chat->storage()->set('pickup_address', $text)
                    ->set('order_step', 'destination_address');
                $this->chat->message('Enter destination address:')->send();
                break;
            case 'destination_address':
                $this->chat->storage()->set('destination_address', $text)
                    ->set('order_step', 'budget');
                $this->chat->message('Enter budget:')->send();
                break;
            case 'budget':
                $this->chat->storage()->set('budget', $text)
                    ->set('order_step', 'details');
                $this->chat->message('Additional details? (or "-" if none):')->send();
                break;
            case 'details':
                $this->chat->storage()->set('details', $text);
                $this->saveOrder();
                $this->chat->storage()->forget('order_step');
                break;
        }
    }

    protected function handleUpdateStep(string $step, Stringable $text): void
    {
        switch ($step) {
            case 'client_update_first_name':
                $this->chat->storage()->set('client_first_name', $text);
                $this->chat->storage()->set('registration_step', 'client_update_last_name');
                $this->chat->message('Enter your last name:')->send();
                break;

            case 'client_update_last_name':
                $this->chat->storage()->set('client_last_name', $text);
                $this->chat->storage()->set('registration_step', 'client_update_phone');
                $this->chat->message('Enter your phone number:')->send();
                break;

            case 'client_update_phone':
                $this->chat->storage()->set('client_phone', $text);
                $this->chat->storage()->set('registration_step', 'client_update_country');
                $this->chat->message('Enter your country:')->send();
                break;

            case 'client_update_country':
                $this->chat->storage()->set('client_country', $text);
                $this->chat->storage()->set('registration_step', 'client_update_city');
                $this->chat->message('Enter your city:')->send();
                break;

            case 'client_update_city':
                $this->chat->storage()->set('client_city', $text);
                $this->saveUpdatedClient();
                break;
        }
    }

    protected function saveUpdatedClient(): void
    {
        $telegramId = $this->message->from()->id();

        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'client') {
            $this->chat->message('❌ You are not registered as a client.')
                ->keyboard(
                    Keyboard::make()->buttons([
                        Button::make('🙋 Client registration')->action('register_client'),
                    ])
                )
                ->send();
            return;
        }

        $client = $user->client;

        $client->update([
            'first_name' => $this->chat->storage()->get('client_first_name'),
            'last_name'  => $this->chat->storage()->get('client_last_name'),
            'phone'      => $this->chat->storage()->get('client_phone'),
            'country'    => $this->chat->storage()->get('client_country'),
            'city'       => $this->chat->storage()->get('client_city'),
        ]);

        $this->chat->message('✅ Your info has been updated!')
            ->keyboard(
                Keyboard::make()->buttons([
                    Button::make('📝 Create order')->action('create_order'),
                    Button::make('🏠 Main menu')->action('start'),
                ])
            )
            ->send();

        $this->chat->storage()->forget('registration_step');
    }


    public function handlePhoto(Photo $photo): void
    {

        $step = $this->chat->storage()->get('registration_step');

        if ($step === 'license_photo') {
            $filename = 'license_' . now()->timestamp . '.jpg';
            $relativePath = 'license_photos/';
            Storage::disk('public')->makeDirectory($relativePath); // создаём папку если нет
            Storage::disk('public')->path($relativePath . '/' . $filename); // полный путь к файлу
            $absolutePath = storage_path('app/public/' . $relativePath);

            Telegraph::store($photo, $absolutePath, $filename); // сохраняем файл в нужное место

            $this->chat->storage()->set('license_photo', 'license_photos/' . $filename);

            $this->chat->storage()->set('registration_step', 'car_photo');
            $this->chat->message('✅ License photo saved. Now send a photo of your car:')->send();
        }

        if ($step === 'car_photo') {
            $filename = 'car_' . now()->timestamp . '.jpg';
            $relativePath = 'car_photos/';
            Storage::disk('public')->makeDirectory($relativePath); // создаём папку если нет
            Storage::disk('public')->path($relativePath . '/' . $filename); // полный путь к файлу

            $absolutePath = storage_path('app/public/' . $relativePath);

            Telegraph::store($photo, $absolutePath, $filename); // сохраняем файл в нужное место

            $this->chat->storage()->set('car_photo', 'car_photos/' . $filename); // сохраняем путь для БД

            $this->saveDriver(); // Финальная регистрация
        }
    }

    protected function saveClient(): void
    {

        $telegramId = $this->message->from()?->id();
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user) {
            $this->chat
                ->message('⚠️ You are already registered as a client. What would you like to do?')
                ->keyboard(
                    Keyboard::make()->buttons([
                        Button::make('🔄 Update info')->action('update_client_info'),
                        Button::make('📝 Create order')->action('create_order'),
                    ])
                )
                ->send();
            return;
        }

        $first_name = $this->chat->storage()->get('client_first_name');
        $last_name = $this->chat->storage()->get('client_last_name');
        $email = $this->chat->storage()->get('client_email');
        $password = Str::random(10);


        $user = User::create([
            'name' => $first_name . '_' . $last_name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'client',
            'telegram_id' => $telegramId,
        ]);


        try {
            Mail::to($user->email)->send(new WelcomeClientMail($user, $password));
        } catch (\Throwable $e) {
            Log::error('Не удалось отправить письмо клиенту: ' . $e->getMessage());
            $this->chat->message('⚠️ Клиент зарегистрирован, но письмо не удалось отправить.')->send();
        }


        $client = new Client();
        $client->first_name = $this->chat->storage()->get('client_first_name');
        $client->last_name = $this->chat->storage()->get('client_last_name');
        $client->phone = $this->chat->storage()->get('client_phone');
        $client->country = $this->chat->storage()->get('client_country');
        $client->city = $this->chat->storage()->get('client_city');
        $client->user_id = $user->id;

        $client->save();

        $this->chat->message('✅ You have been successfully registered as a client!/n Now you can create an order')
            ->keyboard(
                Keyboard::make()->buttons([
                    Button::make('📝 Create order')->action('create_order'),
                ])
            )
            ->send();
    }

    public function update_client_info(): void
    {
        $this->chat->storage()->set('registration_step', 'client_update_first_name');
        $this->chat->message('🔄 Let\'s update your info. Enter your first name:')
            ->keyboard(
                Keyboard::make()->buttons([
                    Button::make('❌ Cancel')->action('cancel'),
                ])
            )
            ->send();
    }


    protected function saveDriver(): void
    {
        $telegramId = $this->message->from()->id();

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
            'telegram_id' => $telegramId,
        ]);

        // Создание водителя
        Driver::create([
            'user_id' => $user->id,
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
    }

    protected function saveOrder(): void
    {

        $telegramId = $this->message->from()?->id();

        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'client') {
            $this->chat->message('❌ You are not registered as a client.')
                ->keyboard(
                    Keyboard::make()->buttons([
                        Button::make('🙋 Client registration')->action('register_client'),
                    ])
                )
                ->send();
            return;
        }

        $client = $user->client;

        if (!$client) {
            $this->chat->message('❌ Client profile not found.')->send();
            return;
        }

        $order = Order::create([
            'client_id' => $client->id,
            'pickup_address' => $this->chat->storage()->get('pickup_address'),
            'destination_address' => $this->chat->storage()->get('destination_address'),
            'budget' => $this->chat->storage()->get('budget'),
            'details' => $this->chat->storage()->get('details'),
            'status' => 'new',
        ]);

        $this->chat->message('✅ Your order has been created! Wait for the drivers response.')->send();

        $this->notifyDrivers($order);
    }

    /**
     * Notify available drivers about a new order.
     *
     * @param Order $order
     * @return void
     */
    protected function notifyDrivers(Order $order): void
    {
        // Example: Notify all drivers in the same city as the order's client
        $client = $order->client;
        $drivers = Driver::where('city', $client->city)->where('status', 'active')->get();
        Log::info("🔔 Order #{$order->id} count drivers = {$drivers->count()}");

        foreach ($drivers as $driver) {
            if ($driver->user && $driver->user->telegram_id) {
                Telegraph::chat($driver->user->telegram_id)
                    ->message(
                        "🚕 New order!\n
                        📍 From: {$order->pickup_address}\n
                        🏁 To: {$order->destination_address}
                        💵 Budget: {$order->budget}\n
                        📝 Details: {$order->details}"
                    )
                    ->keyboard(
                        Keyboard::make()->buttons([
                            Button::make("✅ Accept order")->action('accept_order')->param('order_id', $order->id),
                        ])
                    )
                    ->send();
                // Логгирование
                Log::info("🔔 Order #{$order->id} sent to driver ID={$driver->id}, Telegram ID={$driver->user->telegram_id}");
            }
        }
    }

    public function accept_order(): void
    {
        $orderId = $this->data->get('order_id');
        $order = Order::find($orderId);

        if (!$order || $order->status !== 'new') {
            $this->chat->message('❌ This order has already been accepted.')->send();
            return;
        }

        // $telegramId = $this->message->from()?->id();
        $telegramId = $this->chat->chat_id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (!$user || $user->role !== 'driver') {
            $this->chat->message('❌ You are not registered as a driver.')->send();
            return;
        }

        $driver = $user->driver;

        if (!$driver) {
            $this->chat->message('❌ Driver profile not found.')->send();
            return;
        }

        $order->update([
            'driver_id' => $driver->id,
            'status' => 'accepted',
        ]);

        $this->chat->message('✅ You have accepted the order!')->send();

        // Уведомить клиента
        $clientUser = $order->client->user;


        if ($clientUser && $clientUser->telegram_id) {
            Telegraph::chat($clientUser->telegram_id)
                ->message("🚕 Your order has been accepted by the driver {$driver->first_name} \n
                 model car: {$driver->car_model}\n
                 car license: {$driver->license_number}")
                ->send();
        }
    }


    public function cancel(): void
    {
        $this->chat->storage()
            ->forget('registration_step')
            ->forget('order_step')
            ->forget('driver_email')
            ->forget('client_email')
            ->forget('driver_first_name')
            ->forget('client_first_name');
        // можешь добавить и другие поля при необходимости

        $this->chat->message('❌ Action cancelled.')->send();

        $this->start(); // показать меню заново
    }

}


