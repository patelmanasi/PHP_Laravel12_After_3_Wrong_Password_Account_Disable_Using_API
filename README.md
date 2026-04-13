# PHP_Laravel12_After_3_Wrong_Password_Account_Disable_Using_API

##  Project Introduction

This project is a **secure authentication REST API** built with **Laravel 12**, designed to automatically **disable an account after three consecutive incorrect password attempts**.  
It uses a **custom authentication table (`accounts`)** and follows **real-world security practices** to protect applications from brute-force login attacks.

---

##  Project Overview

The application provides a clean and beginner-friendly implementation of:

- Laravel 12 **API-based authentication flow**
- Tracking **failed login attempts**
- **Automatic account lock** after three wrong passwords
- Structured **JSON responses for API testing**
- Proper **MVC architecture** aligned with industry standards

---

##  Main Features

- Laravel 12 **REST API authentication**
- Custom authentication table **`accounts`**
- Tracking of **wrong password attempts**
- **Automatic account disable** after three failed logins
- Proper **JSON API responses**
- Clean and maintainable **MVC struct

---

## Step 1 — Create Laravel 12 Project

```bash
composer create-project laravel/laravel PHP_Laravel12_After_3_Wrong_Password_Account_Disable_Using_API "12.*"
cd PHP_Laravel12_After_3_Wrong_Passoword_Account_Disable_Using_API
```
---

## Step 2 — Configure Database

Update .env:

```.env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel12_account_disabled
DB_USERNAME=root
DB_PASSWORD=
```

Run default migration for create database:

```bash
php artisan migrate
```

---

## Step 3 — Create Account Model & Migration

```bash
php artisan make:model Account -m
```

---

## Step 4 — Define accounts Table Structure

Open:

database/migrations/xxxx_create_accounts_table.php

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('wrong_attempts')->default(0);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

Run:

```bash
php artisan migrate
```

---


## Step 5 — Update Account Model

Open:

app/Models/Account.php

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Account extends Authenticatable
{
    protected $table = 'accounts';

    protected $fillable = [
        'name',
        'email',
        'password',
        'wrong_attempts',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
```

---

## Step 6 — Configure Laravel Auth to Use accounts

Open:

config/auth.php


Update providers → users → model:

```
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', App\Models\Account::class),
    ],
],
```

---

## Step 7 — Create API Auth Controller

```bash
php artisan make:controller API/AuthController
```

app/Http/Controllers/API/AuthController.php

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:accounts',
            'password' => 'required|min:6'
        ]);

        $account = Account::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Account registered successfully',
            'data' => $account
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $account = Account::where('email', $request->email)->first();

        if (!$account) {
            return response()->json([
                'status' => false,
                'message' => 'Account not found'
            ], 404);
        }

        //  Check disabled account
        if (!$account->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'Account disabled due to multiple wrong passwords'
            ], 403);
        }

        //  Wrong password
        if (!Hash::check($request->password, $account->password)) {

            $account->increment('wrong_attempts');

            if ($account->wrong_attempts >= 3) {
                $account->update(['is_active' => false]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid password',
                'wrong_attempts' => $account->wrong_attempts
            ], 401);
        }

        //  Correct password → reset attempts
        $account->update(['wrong_attempts' => 0]);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => $account
        ]);
    }
}
```

---

## Step 8 — Register API Routes in Laravel 12

bootstrap/app.php


Ensure API routing is enabled:

```
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

This makes all API routes accessible with the /api prefix.

---

## Step 9 — Create API Routes

Open:

routes/api.php

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
```

---

## API Testing (Postman)

### Register

```
POST /api/register
```

```
{
  "name": "Test User",
  "email": "test@gmail.com",
  "password": "123456"
}
```

### Login

```
POST /api/login
```

- After 3 wrong passwords → account disabled automatically

---

## Output

### Register

<img width="1373" height="999" alt="Screenshot 2026-02-05 123833" src="https://github.com/user-attachments/assets/16e0060c-586a-48ca-abe2-3953eef9c60d" />

## Login With Three Time Wrong Passwords

<img width="1384" height="1002" alt="Screenshot 2026-02-05 124024" src="https://github.com/user-attachments/assets/8b1725d5-edea-4780-a938-7a0473d423f6" />

## Account Disabled

<img width="1382" height="995" alt="Screenshot 2026-02-05 124040" src="https://github.com/user-attachments/assets/c32d4061-3e5c-491c-8f3a-ce673479b9eb" />

---

##  Project Structure

```
PHP_Laravel12_After_3_Wrong_Password_Account_Disable_Using_API/
│
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── API/
│   │           └── AuthController.php
│   │
│   └── Models/
│       └── Account.php
│
├── bootstrap/
│   └── app.php
│
├── config/
│   └── auth.php
│
├── database/
│   └── migrations/
│       └── xxxx_create_accounts_table.php
│
├── routes/
│   └── api.php
│
├── .env
├── README.md
```

---

Your PHP_Laravel12_After_3_Wrong_Password_Account_Disable_Using_API Project is now ready!
<<<<<<< HEAD

=======
>>>>>>> development
