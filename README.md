# LaravelAICoder — Laravel API Backend

AI-powered Laravel IDE platform backend.

## Stack
- Laravel 11
- MySQL 8
- Redis + Laravel Horizon
- Laravel Sanctum (auth)
- Flutterwave (billing)

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan horizon
```

## Deploy
Railway.app — see `railway.json`
