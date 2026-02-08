# Developer Guide

## 1) Architecture Overview

This solution is structured as domain microservices behind an API gateway:

1. **Tenant Service**: multi-tenant lifecycle, organizations, subscriptions, Stripe webhooks.
2. **Onboarding Service**: candidate-to-employee pipeline, tasks, documents, checklists.
3. **Payroll Service**: payroll periods, earnings, deductions, net-pay computation, payout orchestration.
4. **User Service**: authentication, roles, permissions, SSO hooks.
5. **Reporting Service**: KPI aggregation, cross-service reporting, exports.

All services share:
- MySQL 8.4 (SQL source of truth)
- Redis 7.4 (cache + queue)

## 2) Multi-tenancy model

Recommended model for Laravel implementation:

- `tenants` table in control plane.
- `tenant_id` in all transactional tables.
- Tenant scoping middleware (`X-Tenant-ID` header + JWT claims).
- Row-level scoping in repositories/query builders.
- Per-tenant cache keys: `tenant:{tenant_id}:{resource}:{id}`.

## 3) Stripe Integration Design

Billing flow:
1. Tenant signs up and selects plan.
2. Tenant service creates Stripe customer + subscription.
3. Webhook endpoint ingests:
   - `invoice.paid`
   - `invoice.payment_failed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
4. Subscription state propagates via Redis queue events.

Security:
- Verify Stripe webhook signatures.
- Store only Stripe IDs, never raw PAN/payment data.

## 4) SQL baseline schema

Core tables:
- `tenants`, `subscriptions`, `plans`
- `users`, `roles`, `permissions`, `role_user`
- `employees`, `employee_documents`, `onboarding_tasks`
- `payroll_cycles`, `payslips`, `pay_items`
- `leave_requests`, `attendance_logs`
- `reports`, `report_exports`

## 5) Redis usage

- **Cache**: dashboard KPIs, role matrix, payroll run snapshots.
- **Queue**: onboarding reminders, payroll jobs, report generation.
- **Rate limiting**: API gateway / auth endpoints.

## 6) Hetzner CAX21 optimization notes (ARM64)

- Use ARM-compatible images (all images used are multi-arch).
- Keep MySQL buffer pool at 512MB for memory headroom.
- Redis maxmemory 256MB with LRU eviction.
- Offload heavy report generation to async queues.
- Enable gzip/Brotli at edge when deploying with reverse proxy.

## 7) Suggested Laravel implementation roadmap

1. Bootstrap each microservice from Laravel 11.
2. Introduce contract-first API definitions (OpenAPI per service).
3. Add OAuth2/JWT and tenant middleware.
4. Implement event contracts and idempotent consumers.
5. Add observability: OpenTelemetry traces, centralized logs.
6. Harden with retries, dead-letter queues, and strict schema validations.
