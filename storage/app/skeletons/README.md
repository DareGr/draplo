# Laravel Base Skeletons

Each directory contains a clean Laravel project for that version:

- `laravel-10/` — Laravel 10.x, PHP 8.1+
- `laravel-11/` — Laravel 11.x, PHP 8.2+
- `laravel-12/` — Laravel 12.x, PHP 8.3+
- `laravel-13/` — Laravel 13.x, PHP 8.3+

## Populating skeletons

Run on the server (requires Composer + PHP):
```
php artisan skeleton:create 10
php artisan skeleton:create 11
php artisan skeleton:create 12
php artisan skeleton:create 13
```

Each skeleton includes the shared Docker + CI configs from `storage/app/skeletons/shared/`.

## What's included

- Full Laravel project structure for that version
- Dockerfile (multi-stage: composer → node → PHP-FPM + Nginx)
- docker-compose.yml (app + postgres + redis)
- docker/nginx.conf, docker/entrypoint.sh, docker/supervisord.conf
- .github/workflows/deploy.yml (Coolify auto-deploy on push)
- .env.example (Docker service hostnames)
- .dockerignore
