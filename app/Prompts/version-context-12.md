## Laravel Version: 12.x

You are generating code for **Laravel 12** (PHP 8.3+).

### Key structural differences:
- Same structure as Laravel 11 (no Kernel.php, routes in bootstrap/app.php)
- **Starter kits** use React/Vue/Livewire via `laravel/breeze` or `laravel/jetstream`
- Enhanced type safety with PHP 8.3 features (typed constants, readonly classes)
- `routes/api.php` must be explicitly enabled in `bootstrap/app.php`

### composer.json version constraints:
```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "laravel/horizon": "^5.0",
        "predis/predis": "^3.0"
    }
}
```

### Stack reference:
- PHP: 8.3+
- Laravel: 12.x
- Sanctum: 4.x
- Horizon: 5.x
