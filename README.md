# Rakez ERP — Real Estate ERP

> Production-oriented real-estate ERP built with Laravel 12, REST APIs, Laravel Sanctum, granular RBAC/permission workflows, Redis, PHPUnit, third-party integrations, and GitHub Actions-based production delivery.

## Overview

Rakez ERP is a business operations platform for real-estate workflows. The system is designed around production business logic rather than a generic CRUD structure, with backend modules covering accounting, commissions, deposits, salaries, notifications, dashboards, authenticated APIs, granular permissions, and operational processes.

This repository is one of the primary backend-focused projects in my portfolio and reflects work across **requirements analysis, ERD/database design, backend architecture, REST API implementation, access control, integrations, testing, CI/CD, server deployment, and production troubleshooting**.

The Laravel application is located under [`rakez-erp/`](rakez-erp/).

## Engineering Scope

### Backend & API

- Laravel 12 application architecture
- REST API endpoints for business workflows
- Laravel Sanctum authentication
- Granular roles and permissions using Spatie Laravel Permission
- Service-oriented business logic across operational modules
- Validation, authorization, and protected route groups
- Real-time capabilities through Laravel Reverb

### ERP & Accounting Workflows

The repository includes backend flows and documentation for areas such as:

- Accounting dashboards
- Commissions
- Deposits
- Salary workflows
- Notifications
- Claims and operational records
- Sales and marketing workflows
- Credit-related workflows
- HR and inventory-related areas

The exact behavior of individual modules should be evaluated from the implementation and tests rather than inferred only from documentation.

### Data & Infrastructure

- Relational database modeling through Laravel migrations and Eloquent
- Redis support through Predis
- Server-side PDF/document tooling
- Excel import/export support
- Queue/background workflow support through Laravel tooling
- Environment-based configuration with secrets kept outside source control
- Linux/VPS-oriented production deployment structure

### Integrations

Repository dependencies and implementation areas include support for integrations such as:

- Twilio
- Meta / Facebook Business SDK
- TikTok Marketing API
- OpenAI integration

The presence of an SDK or dependency does not by itself imply that every integration path is enabled in every environment.

## CI/CD & Production Delivery

The repository includes a GitHub Actions production deployment workflow under [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

The deployment workflow is intentionally explicit about production safety:

- production runs use a dedicated GitHub environment;
- SSH host, username, private key, port, and fingerprint are supplied through GitHub Secrets;
- the SSH action is pinned to a specific commit rather than a floating tag;
- deployment uses `composer install --no-dev --optimize-autoloader`;
- frontend dependencies are installed with `npm ci` and built for production;
- Laravel configuration, routes, views, migrations, and optimization are applied on the server;
- deployment is serialized through a production concurrency group.

This provides repository-level evidence for the CI/CD, GitHub Actions, Linux/VPS, SSH, environment configuration, and production-deployment experience described in my CV.

## Security & Configuration Hygiene

- Runtime credentials belong in server environment configuration or GitHub Secrets, never in source control.
- Reverb/WebSocket documentation uses placeholders rather than production secrets or infrastructure-specific credentials.
- Frontend configuration exposes only client-safe values; privileged tokens and `REVERB_APP_SECRET` remain server-side.
- API authentication and permission boundaries are enforced through Sanctum and granular role/permission workflows.
- Real third-party credentials used by integrations or live tests must be supplied through environment configuration.

> Historical credentials that were ever committed should be treated as exposed and rotated at the provider, even after the current branch is sanitized.

## Technology Stack

| Area | Technologies |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| API | REST APIs, Laravel Sanctum |
| Authorization | Spatie Laravel Permission, RBAC-style permission workflows |
| Data / Cache | Eloquent ORM, relational database, Redis / Predis |
| Real-Time | Laravel Reverb |
| Testing | PHPUnit 11, Laravel testing tools |
| Integrations | Twilio, Meta Business SDK, TikTok Marketing API, OpenAI |
| CI/CD | GitHub Actions, GitHub Environments / Secrets |
| Production Delivery | Linux/VPS, SSH, Composer, npm/Vite, Laravel optimization and migrations |

## Repository Structure

```text
.
├── .github/
│   └── workflows/
│       └── deploy.yml
├── rakez-erp/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── docs/
│   ├── routes/
│   ├── tests/
│   ├── composer.json
│   └── package.json
└── README.md
```

## Key Backend Evidence

The codebase contains concrete backend evidence for the CV-level positioning of this project, including:

- Laravel 12
- Laravel Sanctum
- Spatie Laravel Permission
- Redis / Predis
- PHPUnit
- Accounting and commission-related controllers and routes
- Deposit and salary workflow documentation / implementation areas
- Authenticated API route groups
- Third-party SDKs and integration packages
- OpenAI integration package and AI-related test suites
- GitHub Actions production deployment

## Engineering Positioning

This project is best presented as a **real-estate ERP and backend business platform**, not simply as a Laravel application.

It demonstrates work across:

`Requirements → Data Model → Backend Architecture → REST APIs → Authentication / Authorization → Business Logic → Integrations → Testing → CI/CD → Deployment → Production Support`

## Portfolio

**Majd Bayer — Full Stack Software Engineer | Backend-Focused**  
Laravel · FastAPI · Next.js · REST APIs · ERP/CRM · System Design · CI/CD

Portfolio / Company: https://www.hexaterminal.com/en
