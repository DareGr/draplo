## Laravel Version: 10.x

You are generating code for **Laravel 10** (PHP 8.1+).

### Key structural differences:
- **Middleware** is registered in `app/Http/Kernel.php`
- **Routes** are registered in `app/Providers/RouteServiceProvider.php`
- **Config** files are fully expanded in `config/` directory
- `routes/api.php` exists by default and is loaded by RouteServiceProvider
- Use `$this->middleware()` in controllers for inline middleware

### composer.json version constraints:
```json
{
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.0",
        "laravel/sanctum": "^3.0",
        "laravel/horizon": "^5.0",
        "predis/predis": "^2.0"
    }
}
```

### Stack reference:
- PHP: 8.1+
- Laravel: 10.x
- Sanctum: 3.x
- Horizon: 5.x
