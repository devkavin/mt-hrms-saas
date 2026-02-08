import { describe, expect, it } from 'vitest';
import { buildKpiCards, defaultActivities } from '../lib/dashboard-data';

describe('dashboard data', () => {
  it('builds four KPI cards from reporting metrics', () => {
    const cards = buildKpiCards({
      headcount: 180,
      pending_onboarding: 11,
      monthly_payroll: 920000,
      open_compliance_alerts: 4,
      attrition_rate: 0.08,
      time_to_hire_days: 18,
    });

    expect(cards.length).toBe(4);
    expect(cards[0].label).toBe('Active Employees');
  });

  it('contains baseline dashboard activities', () => {
    expect(defaultActivities.length).toBeGreaterThan(0);
  });
});
