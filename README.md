# OpsFlow API

Laravel API for **OpsFlow**, an operations management app with AI-assisted work order intake.

The frontend is in the `farghana/opsflow-web` repository.

## What it handles

- Authentication with Laravel Sanctum
- Organizations and tenant-specific data
- Clients
- Work orders and assignments
- Status, priority, due dates, and activity history
- Dashboard metrics
- AI-assisted work order parsing with Anthropic Claude

## AI intake

The AI endpoint takes an unstructured request and returns a suggested work order draft.

Claude can suggest things like the title, description, client, assignee, priority, and due date. Client and assignee matches are checked against the current organization's data before the draft is returned.

The AI does not create the work order. The user reviews the draft in the Vue app and the final data goes through the regular Work Order API and validation.

## Built with

- Laravel 12
- PHP 8.2+
- PostgreSQL
- Laravel Sanctum
- Anthropic Claude
- Pest

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure your PostgreSQL connection, then run:

```bash
php artisan migrate
php artisan serve --host=localhost --port=8000
```

To use AI intake locally, add your Anthropic credentials to `.env`:

```env
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6
```

Do not commit API keys.

## Tests

```bash
php artisan test
```

The AI tests use a fake provider response, so running the test suite doesn't use API credits.

## Frontend

See `farghana/opsflow-web` for the Vue app, dashboard, Clients and Work Orders screens, and AI Intake review flow.
