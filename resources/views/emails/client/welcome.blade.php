<x-mail::message>
# Introduction

# Welcome, {{ $user->name }}!

Thank you for registering in our TaxiBot service.

Here are your login details:

- **Email:** {{ $user->email }}
- **Password:** {{ $password }}

The body of your message.

<x-mail::button :url="''">
Button Text
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
