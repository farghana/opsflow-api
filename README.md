# OpsFlow API

OpsFlow is a multi-tenant SaaS operations platform for service businesses. This repository contains the Laravel REST API powering authentication, organization tenancy, clients, work orders, permissions, reporting, billing workflows, files, background jobs, and AI-assisted intake.

## Backend stack

- Laravel 12
- PHP 8.4
- PostgreSQL
- Laravel Sanctum
- Spatie Laravel Permission
- Pest
- Laravel Queues
- Laravel Filesystem

## Architecture goals

The API is intentionally structured to demonstrate production SaaS engineering patterns:

- row-level organization tenancy
- policy-based authorization
- database-backed roles and permissions
- thin controllers
- Form Requests for validation
- API Resources for response formatting
- Actions / Services for business logic
- enums for workflow state
- queued work for long-running tasks
- audit-friendly activity history
- AI provider abstraction instead of coupling business logic directly to one model vendor

## Initial domain

### Organizations
Every tenant-owned record belongs to an organization.

### Clients
Service-business customers and contact information.

### Work Orders
The core workflow entity, including status, priority, assignment, due dates, estimates, completion state, comments, attachments, and activity history.

### Permissions
Initial roles:

- Owner
- Manager
- Technician
- Billing

### AI intake
A user can submit unstructured intake notes and receive a proposed structured work order. AI output is reviewed by a human before any work order is persisted.

## Planned API areas

- `/api/health`
- authentication / current user
- clients
- work orders
- comments
- attachments
- dashboard metrics
- reports / exports
- invoices
- AI extraction

## Related repository

Frontend: [`opsflow-web`](https://github.com/farghana/opsflow-web)

## Status

Early development. The first milestone is API bootstrap, Sanctum authentication, organization tenancy, permissions, clients, and the work-order domain.
