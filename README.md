# ManageCT Project Laravel

Laravel migration of **ManageCT**, a multi-tenant SaaS platform for project management, Gantt scheduling, and role-based collaboration.

This repository is intentionally independent from the stable Phalcon application. It provides the new backend foundation for a controlled migration to a PHP stack that can run on shared hosting today and scale to VPS or managed infrastructure later.

## Product scope

- Company-isolated project portfolios and users
- Project planning, task dependencies, and Gantt schedules
- Role-based access control, audit trails, and secure authentication
- Project overview, operational indicators, and release history
- Secure document uploads and future client collaboration features

## Migration principles

1. Preserve tenant isolation: every business query and mutation must be scoped to the active company.
2. Preserve authorization, auditability, CSRF protection, and upload safeguards from the stable application.
3. Keep database migrations backward compatible while both applications coexist.
4. Start with MySQL and scheduled tasks compatible with shared hosting; introduce Redis, queues, and external object storage only when the deployment platform supports them.
5. Do not switch production traffic until authentication, tenant isolation, Gantt updates, file access, and database restoration have passed acceptance tests.

## Local setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

On Linux or macOS, use `cp .env.example .env` instead of `copy`.

## Current status

Laravel 12 baseline on PHP 8.2. Domain modules have not yet been migrated; the stable Phalcon repository remains the functional reference during the transition.

## License

Proprietary software. © NexoCore Tecnologia.
