# Comprehensive Test Cases

## A) Developer Test Suite

### 1. Tenant & Billing
- Create tenant with valid plan.
- Prevent duplicate tenant domains.
- Stripe webhook signature validation.
- Subscription status transition (active, past_due, canceled).
- Block premium endpoints when subscription is inactive.

### 2. Authentication & Authorization
- Login success/failure conditions.
- JWT contains tenant_id and role claims.
- RBAC enforcement for HR-only and payroll-only endpoints.
- Session/token revocation behavior.

### 3. Onboarding
- Create onboarding workflow template.
- Assign checklist to new employee.
- Reminder queue dispatch and retry.
- Document upload metadata validation.
- Completion state when all tasks closed.

### 4. Payroll
- Payroll cycle creation and lock cutoff logic.
- Gross/net calculations with deductions and tax rules.
- Re-run prevention for already-finalized cycle.
- Payslip generation and secure retrieval.
- Async payout pipeline queue handling.

### 5. Reporting
- KPI aggregation over configurable date windows.
- Export CSV/PDF generation queue flow.
- Cross-tenant data leakage test (must fail access).

### 6. Performance & Reliability
- P95 API latency under synthetic load.
- Redis cache hit/miss behavior.
- Queue retry and dead-letter handling.

## B) Client Acceptance Test Suite (UAT)

### 1. Dashboard Experience
- Dashboard loads within expected time.
- Metrics reflect latest operational data.
- Navigation is understandable for non-technical HR users.

### 2. Onboarding Experience
- HR can add a new employee and trigger onboarding.
- Employee can see pending tasks and complete them.
- HR can track progress and bottlenecks.

### 3. Payroll Experience
- Payroll admin can run payroll for a month.
- Payslips are generated and downloadable.
- Changes in compensation reflect correctly in next cycle.

### 4. Access Control Experience
- HR manager can create users and assign roles.
- Unauthorized user cannot access payroll admin pages.

### 5. Billing Experience
- Client can subscribe to paid plan using Stripe checkout.
- Invoice and payment status are visible in billing page.
- Failed payment triggers clear in-product notification.

## C) Command-level validation (starter)

```bash
docker compose config

docker compose up -d

curl http://localhost:8080/health
curl http://localhost:8080/api/reporting/kpis
curl http://localhost:8080/api/users/roles
```
