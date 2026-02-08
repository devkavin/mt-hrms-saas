# MT HRMS SaaS (Multi-Tenant, Microservice-Ready)

A production-oriented **multi-tenant HRMS SaaS blueprint + starter implementation** with:

- Laravel-oriented backend service boundaries
- Stripe-ready billing model
- SQL + Redis architecture
- Docker Compose single-file runtime orchestration for Hetzner CAX21 (ARM64)
- Modern, user-friendly dashboard UI starter
- Comprehensive test strategy and documentation for developers and clients

## Core capabilities

- Tenant provisioning and subscription lifecycle
- Employee onboarding workflows
- Payroll management
- User and role management
- Reporting and analytics
- HRMS essentials (leave, attendance, documents, announcements)

## Quick start

```bash
docker compose up -d
```

Open:
- UI: `http://localhost:3000`
- API Gateway: `http://localhost:8080`

> This repository is a **starter kit architecture** focused on strong foundations. See `docs/developer-guide.md` for implementation milestones.
