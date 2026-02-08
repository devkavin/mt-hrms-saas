export type WorkforceKpiResponse = {
  headcount: number;
  pending_onboarding: number;
  monthly_payroll: number;
  open_compliance_alerts: number;
  attrition_rate: number;
  time_to_hire_days: number;
};

export type DashboardKpiCard = {
  label: string;
  value: string;
  hint: string;
  tone: 'neutral' | 'good' | 'warn' | 'hot';
};

const formatMoney = (amount: number): string =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(amount);

export const buildKpiCards = (metrics: WorkforceKpiResponse): DashboardKpiCard[] => [
  {
    label: 'Active Employees',
    value: metrics.headcount.toLocaleString('en-US'),
    hint: `Attrition ${(metrics.attrition_rate * 100).toFixed(1)}%`,
    tone: 'good',
  },
  {
    label: 'Pending Onboarding',
    value: metrics.pending_onboarding.toLocaleString('en-US'),
    hint: `Avg time-to-hire ${metrics.time_to_hire_days} days`,
    tone: metrics.pending_onboarding > 15 ? 'warn' : 'neutral',
  },
  {
    label: 'Monthly Payroll',
    value: formatMoney(metrics.monthly_payroll),
    hint: 'Net disbursement estimate',
    tone: 'neutral',
  },
  {
    label: 'Compliance Alerts',
    value: metrics.open_compliance_alerts.toLocaleString('en-US'),
    hint: 'Actionable policy signals',
    tone: metrics.open_compliance_alerts > 5 ? 'hot' : 'warn',
  },
];

export const defaultActivities = [
  'Tenant-level SSO is enforced for all sign-ins.',
  'Finance and HR admins can run payroll and exports.',
  'Role assignment and invitations are tracked per tenant.',
];
