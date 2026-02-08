# Multi-Tenant HRMS SaaS (Laravel 12 + Next.js)

This repository contains a production-ready blueprint for a **multi-tenant HRMS SaaS** with:

- Laravel 12 microservices (API Gateway, Employee Onboarding, Payroll, User Management, Reporting)
- Next.js 15 App Router frontend with a premium HRMS UI/UX
- Stripe subscription billing
- Enterprise SSO stubs (Okta, Azure AD, Google Workspace, SAML)
- PostgreSQL (SQL), Redis cache/queues
- Dockerized local/prod setup tailored for **Hetzner CAX21**

## Services

| Service | Purpose | Port |
|---|---|---:|
| `api-gateway` | Authentication, tenant routing, Stripe billing + SSO orchestration | 8000 |
| `employee-onboarding` | Candidate -> employee onboarding workflows | 8001 |
| `payroll` | Payroll runs, tax rules, disbursement prep | 8002 |
| `user-management` | RBAC, invite flow, SSO directory policies/sync | 8003 |
| `reporting` | Analytics, compliance exports, KPI dashboards | 8004 |
| `web-nextjs` | Modern HRMS UI for admins/managers/employees | 3000 |

## Architecture highlights

- **Multi-tenancy:** header-based (`X-Tenant-ID`) with DB-level tenant scoping pattern.
- **Billing:** Stripe Checkout + Customer Portal flow via API Gateway.
- **Identity:** SSO provider discovery, tenant config, and login URL generation stubs.
- **Data:** PostgreSQL primary store, Redis for cache/session/queue.
- **Scalability:** independent services, API gateway composition, Redis-backed async jobs.

## Quick start

```bash
docker compose up --build -d
```

Then open:

- UI: http://localhost:3000
- Gateway Health: http://localhost:8000/api/health

## Docs

- Developer guide: `docs/developer/SETUP_AND_ARCHITECTURE.md`
- Developer test cases: `docs/developer/TEST_CASES.md`
- Client user guide: `docs/client/CLIENT_GUIDE.md`
- Client acceptance test pack: `docs/client/UAT_TEST_CASES.md`

## Why this stack for Hetzner CAX21?

- ARM-compatible images (nginx, redis, postgres, php-fpm, node)
- Lean container footprint and horizontally split services
- Tuned defaults for constrained CPU/RAM with Redis-backed caching
