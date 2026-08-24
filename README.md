# OpsFlow API

Backend API for **OpsFlow**, an AI-assisted operations workspace for turning unstructured requests into trackable work.

OpsFlow is a portfolio-scale full-stack application built around a realistic operational workflow: organizations manage clients, create and assign work orders, track status and activity, monitor operational metrics, and use AI to turn messy free-text requests into structured work-order drafts.

> This repository contains the Laravel API. The Vue frontend lives in `farghana/opsflow-web`.

## Why this project exists

Operational requests often arrive as emails, calls, chat messages, or loosely written notes. Important details such as the client, urgency, due date, and assignee then have to be copied into a tracking system manually.

OpsFlow demonstrates a safer AI-assisted workflow: AI extracts a **draft**, tenant-owned entities are resolved server-side, a human reviews or corrects the result, and the normal validated Work Order API performs the final write.

## Highlights

- Multi-tenant organization architecture with explicit tenant isolation
- Laravel Sanctum SPA authentication
- Client management with search, sorting, pagination, and validation
- Work Orders linked to clients and optional team-member assignees
- Workflow statuses, priorities, due dates, overdue detection, and activity history
- Tenant-scoped operational dashboard metrics
- Anthropic Claude integration for structured AI-assisted intake
- Human-in-the-loop review: AI never creates work orders directly
- PostgreSQL relational data model
- Pest feature tests covering authentication, tenancy, CRUD, dashboard, and AI provider behavior

## Architecture

```text
Vue 3 SPA
   |
   | Sanctum session + JSON API
   v
Laravel 12 API
   |-------------------------------|
   |                               |
PostgreSQL                   Anthropic Messages API
   |                               |
Organizations                    structured draft
Clients                            |
Users/Assignees                    v
Work Orders <-------------- tenant-safe resolution
Activity History                   |
                                   v
                           human review in Vue
                                   |
                                   v
                         validated Work Order API
```

The AI provider is kept behind a backend service. API credentials never reach the browser, and the provider cannot bypass Laravel validation or tenant boundaries.

## Core domain

### Organizations

Users belong to an organization. Clients, team members, work orders, dashboard metrics, and AI entity resolution are scoped to the authenticated organization.

### Clients

Clients support authenticated CRUD, validation, server-side search, sorting, and pagination.

### Work Orders

A work order belongs to an organization and client and may be assigned to a team member. It includes:

- generated order number
- title and description
- priority: `low`, `normal`, `high`, `urgent`
- status: `draft`, `queued`, `in_progress`, `blocked`, `completed`, `cancelled`
- due date and overdue state
- completion timestamp
- activity history for meaningful changes

### AI-assisted intake

`POST /api/work-order-intake/parse` accepts an unstructured operational request. Claude returns a schema-constrained draft containing fields such as client, assignee, title, priority, status, due date, confidence, and warnings.

Client and assignee IDs are checked against the authenticated organization. Ambiguous or invalid matches are cleared rather than trusted. The response is only a draft; the frontend requires review before calling the regular Work Order endpoint.

## Selected API endpoints

```text
GET    /api/user
GET    /api/dashboard/summary

GET    /api/clients
POST   /api/clients
GET    /api/clients/{client}
PUT    /api/clients/{client}
DELETE /api/clients/{client}

GET    /api/work-orders
POST   /api/work-orders
GET    /api/work-orders/{workOrder}
PUT    /api/work-orders/{workOrder}
DELETE /api/work-orders/{workOrder}

GET    /api/team-members
POST   /api/work-order-intake/parse
```

## Tech stack

| Area | Technology |
| --- | --- |
| Framework | Laravel 12 / PHP 8.2+ |
| Database | PostgreSQL |
| Authentication | Laravel Sanctum |
| AI | Anthropic Claude Messages API + Structured Outputs |
| Testing | Pest |
| API style | REST / JSON |

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure PostgreSQL and the frontend origin in `.env`, then run:

```bash
php artisan migrate
php artisan serve --host=localhost --port=8000
```

For AI intake, add your own Anthropic API credentials locally:

```env
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6
```

Never commit API keys.

## Tests

```bash
php artisan test
```

AI feature tests fake the external provider response, so the automated suite does not require or spend Anthropic API credits.

## Security and reliability decisions

- SPA authentication uses secure server-side sessions through Sanctum.
- Tenant-owned resources are queried through the authenticated organization.
- AI-proposed client and assignee IDs are revalidated server-side.
- Provider failures are handled without exposing credentials to the frontend.
- AI output is treated as untrusted draft data and still passes through application validation before persistence.

## Frontend

The companion Vue application provides the operational dashboard, Clients and Work Orders interfaces, filtering and sorting, activity timeline, and AI Intake review experience.

Repository: `farghana/opsflow-web`

## Status

OpsFlow is actively developed as a production-style portfolio project. The current focus is presentation, demo data, deployment, and documentation rather than adding more CRUD surface area.
