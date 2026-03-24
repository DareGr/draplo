## Laravel Version: 11.x

You are generating code for **Laravel 11** (PHP 8.2+).

### Key structural differences:
- **No `app/Http/Kernel.php`** — middleware is registered in `bootstrap/app.php`
- **No `app/Providers/RouteServiceProvider.php`** — routes configured in `bootstrap/app.php` using `->withRouting()`
- Simplified config — many configs use inline defaults
- `routes/api.php` must be explicitly enabled in `bootstrap/app.php`:
  ```php
  ->withRouting(
      web: __DIR__.'/../routes/web.php',
      api: __DIR__.'/../routes/api.php',
      commands: __DIR__.'/../routes/console.php',
  )
  ```
- Use `$middleware->append()` in `bootstrap/app.php` for global middleware

### composer.json version constraints:
```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "laravel/horizon": "^5.0",
        "predis/predis": "^2.0"
    }
}
```

### Stack reference:
- PHP: 8.2+
- Laravel: 11.x
- Sanctum: 4.x
- Horizon: 5.x
