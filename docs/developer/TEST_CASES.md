# Developer Test Cases (Comprehensive)

## A. Multi-tenancy

1. **Tenant isolation across services**
   - Given tenant A and tenant B
   - When querying onboarding workflows for tenant A
   - Then no tenant B data appears.
2. **Missing tenant header rejection**
   - Verify 401/422 response when `X-Tenant-ID` is absent.

## B. Stripe billing

1. **Checkout session creation**
   - POST `/api/billing/checkout-session`
   - Assert Stripe provider payload and checkout URL are returned.
2. **Portal session creation**
   - POST `/api/billing/portal-session`
   - Assert billing portal URL is returned.
3. **Webhook replay idempotency**
   - Replay `invoice.paid` events; assert single invoice record update.

## C. Employee onboarding

1. Create workflow returns workflow ID and pending status.
2. Complete step updates workflow to completed state.
3. Upload of mandatory document rejects unsupported MIME type.

## D. Payroll

1. Payroll run request returns accepted (202).
2. Payroll retrieval returns gross and net totals.
3. Lock period prevents duplicate payroll submissions.

## E. User management

1. Invite user sends invitation event.
2. Role assignment updates RBAC matrix.
3. Attempt privilege escalation returns 403.

## F. Reporting

1. Workforce KPI endpoint returns headcount/attrition metrics.
2. Export endpoint queues generation and returns export ID.
3. Export link expires based on configured TTL.

## G. Frontend UX tests

1. Dashboard loads KPI cards in under 1.5s on broadband profile.
2. Keyboard-only navigation reaches all primary actions.
3. Mobile viewport (390x844) keeps critical actions above fold.

## H. Non-functional

1. Sustained load: 300 RPS across read endpoints at p95 < 300ms.
2. Failover: Redis restart should not crash API containers.
3. Backup/restore validates tenant-scoped data recovery.
