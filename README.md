# Rakez ERP — Real Estate ERP

> Production-oriented real-estate ERP built with Laravel 12, REST APIs, Laravel Sanctum, granular role/permission workflows, Redis, PHPUnit, and third-party integrations.

## Overview

Rakez ERP is a business operations platform for real-estate workflows. The system is designed around production business logic rather than a generic CRUD structure, with backend modules covering accounting, commissions, deposits, salaries, notifications, dashboards, authenticated APIs, granular permissions, and operational processes.

This repository is one of the primary backend-focused projects in my portfolio and reflects work across requirements analysis, ERD/database design, backend architecture, REST API implementation, access control, integrations, testing, deployment support, and production troubleshooting.

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
- Production-oriented deployment and configuration structure

### Integrations

Repository dependencies and implementation areas include support for integrations such as:

- Twilio
- Meta / Facebook Business SDK
- TikTok Marketing API
- OpenAI integration

The presence of an SDK or dependency does not by itself imply that every integration path is enabled in every environment.

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
| Delivery | Composer, npm/Vite tooling, Linux/VPS-oriented deployment workflows |

## Repository Structure

```text
.
├── .github/
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

## Engineering Positioning

This project is best presented as a **real-estate ERP and backend business platform**, not simply as a Laravel application.

It demonstrates work across:

`Requirements → Data Model → Backend Architecture → REST APIs → Authentication / Authorization → Business Logic → Integrations → Testing → Deployment / Production Support`

## Portfolio

**Majd Bayer — Full Stack Software Engineer | Backend-Focused**  
Laravel · FastAPI · Next.js · REST APIs · ERP/CRM · System Design

Portfolio / Company: https://www.hexaterminal.com/en
