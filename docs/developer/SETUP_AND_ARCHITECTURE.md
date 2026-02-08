# Developer Setup & Architecture

## 1) Platform goals

- Multi-tenant HRMS SaaS for SMB and enterprise customers.
- API-first Laravel 12 backend split into business microservices.
- Next.js frontend optimized for speed and premium UX.
- Stripe for subscription lifecycle (checkout, billing portal, renewals).

## 2) Microservice boundaries

- **API Gateway:** auth, tenancy resolution, billing orchestration.
- **Employee Onboarding:** workflows, documents, pre-joining tasks.
- **Payroll:** run orchestration, deductions, approvals.
- **User Management:** RBAC, invitations, profile + policy controls.
- **Reporting:** strategic HR analytics + CSV/PDF exports.

## 3) Data and caching

- PostgreSQL stores tenant-scoped transactional data.
- Redis stores cache, queue, and session artifacts.
- Every write model includes `tenant_id` and created/updated metadata.

## 4) Hetzner CAX21 optimization notes

- ARM-native container images reduce emulation overhead.
- Redis memory policy set to `allkeys-lru` and 256MB cap.
- Split services allow targeted scaling and better fault isolation.
- Nginx reverse-proxy keeps frontend + API access on a single endpoint.

## 5) Security & compliance baseline

- Tenant context required via token + `X-Tenant-ID`.
- Service-to-service calls should use signed JWT service tokens.
- SOC2/GDPR readiness path: audit logs, data retention controls, DSR flows.
