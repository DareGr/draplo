## Laravel Version: 13.x

You are generating code for **Laravel 13** (PHP 8.3+).

### Key structural differences:
- Same structure as Laravel 12 (no Kernel.php, routes in bootstrap/app.php)
- Latest Laravel features and conventions
- `routes/api.php` must be explicitly enabled in `bootstrap/app.php`

### composer.json version constraints:
```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.0",
        "laravel/sanctum": "^4.0",
        "laravel/horizon": "^5.0",
        "predis/predis": "^3.0"
    }
}
```

### Stack reference:
- PHP: 8.3+
- Laravel: 13.x
- Sanctum: 4.x
- Horizon: 5.x
