
# Pangi App

Business travel management system built with Laravel 13 and modern web technologies.

## Quick Start

### Requirements
- PHP 8.4
- Laravel 13
- Node.js

### Installation

```bash
# Clone repository
git clone <repository-url>
cd pangi-app

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run build
```

### Development

```bash
composer run dev
```

## Project Structure

- **`app/`** — Laravel application code (controllers, models, requests, services)
- **`database/`** — Migrations, factories, seeders
- **`resources/`** — Frontend assets and views
- **`tests/`** — PHPUnit test suite
- **`config/`** — Application configuration

## Key Technologies

- **Framework:** Laravel 13
- **Testing:** PHPUnit 12
- **Code Quality:** Laravel Pint
- **CLI Tools:** Laravel Prompts, Laravel Pail
- **Logging:** Laravel Pail

## Testing

```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/ExampleTest.php

# Run test by name
php artisan test --compact --filter=testName
```

## Code Style

This project uses Laravel Pint for code formatting. Before committing:

```bash
vendor/bin/pint --dirty --format agent
```

