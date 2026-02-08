# Client UAT Test Cases

## 1. Dashboard experience

- Login as Company Admin.
- Confirm KPI widgets display workforce, onboarding, payroll, and alerts.
- Confirm onboarding pipeline and payroll activity panels are visible.

## 2. Onboarding flow

- Create new employee onboarding workflow.
- Complete at least one step and verify status changes.
- Confirm employee receives invitation email template (mock/integration env).

## 3. Payroll flow

- Start payroll for current month.
- Verify system returns processing status.
- Open payroll result and validate gross/net totals are visible.

## 4. User management

- Invite HR manager account.
- Assign role and verify permissions in navigation.

## 5. Reporting

- Open Workforce KPI report.
- Request CSV export and confirm queued status.

## 6. Billing

- Click Upgrade Plan.
- Confirm redirect to Stripe checkout link.

## 7. SSO

- Open Configure SSO.
- Select provider (Okta/Azure AD/Google Workspace/SAML).
- Save settings and confirm SSO status is enabled.
- Trigger directory sync and confirm queued response.

## UAT exit criteria

- 100% of above tests pass.
- No critical defects.
- Stakeholder sign-off from HR, Finance, and IT owner.
