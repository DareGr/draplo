# Contributing to Draplo

Thank you for your interest in contributing to Draplo!

## Prerequisites

- PHP 8.3+
- Node.js 20+
- Docker & Docker Compose
- Composer

## Development Setup

```bash
git clone https://github.com/yourusername/draplo.git
cd draplo
cp .env.example .env
docker-compose up -d
composer install
npm install
php artisan migrate --seed
npm run dev &
php artisan serve
```

## Code Style

- Follow existing patterns in the codebase
- PHP: PSR-12, Laravel conventions
- React: functional components, hooks
- CSS: Tailwind utility classes with design system tokens
- No emoji in UI — use Material Symbols Outlined

## Testing

All tests must pass before submitting a PR:

```bash
php artisan test
npm run build
```

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Make your changes
4. Ensure tests pass
5. Submit a PR against `main`

## Reporting Issues

Open an issue on GitHub with:
- Clear description of the problem
- Steps to reproduce
- Expected vs actual behavior
- Environment details (OS, PHP version, Node version)

## License

By contributing, you agree that your contributions will be licensed under the AGPL-3.0 license.
