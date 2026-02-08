import { describe, expect, it } from 'vitest';
import { dashboardData } from '../lib/dashboard-data';

describe('dashboard data', () => {
  it('contains at least four KPI cards', () => {
    expect(dashboardData.kpis.length).toBeGreaterThanOrEqual(4);
  });

  it('contains onboarding pipeline rows', () => {
    expect(dashboardData.onboardingPipeline.length).toBeGreaterThan(0);
  });

  it('contains payroll queue updates', () => {
    expect(dashboardData.payrollQueue.length).toBeGreaterThan(0);
  });
});
