@component('mail::message')
# Welcome, {{ $user->name }}!

Thank you for registering in our TaxiBot service.

Here are your login details:

- **Email:** {{ $user->email }}
- **Password:** {{ $password }}

You can now access your personal dashboard or manage your bookings.

@component('mail::button', ['url' => url('/')])
Go to Site
@endcomponent

Thanks,<br>
The TaxiBot Team
@endcomponent
