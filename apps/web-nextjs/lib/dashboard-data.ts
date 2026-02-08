export const dashboardData = {
  kpis: [
    { label: 'Active Employees', value: '1,842', trend: '+4.1% vs last month' },
    { label: 'Onboarding in Progress', value: '42', trend: '9 pending document checks' },
    { label: 'Monthly Payroll', value: '$3.2M', trend: '98.9% paid on time' },
    { label: 'Compliance Alerts', value: '3', trend: '2 high-priority' }
  ],
  onboardingPipeline: [
    { name: 'Aisha Rahman', role: 'Senior Recruiter', stage: 'Offer Accepted', status: 'success' },
    { name: 'Daniel Kim', role: 'Product Designer', stage: 'Document Collection', status: 'warning' },
    { name: 'Monica Silva', role: 'QA Engineer', stage: 'Background Check', status: 'danger' }
  ],
  payrollQueue: [
    'Nigeria payroll run locked for approval (Jan 2026).',
    'US contractor payout file generated.',
    'Tax filing summary ready for CFO review.'
  ],
  announcements: [
    'New SSO login is available for all enterprise plans.',
    'Enhanced onboarding checklists launched this week.'
  ]
};
