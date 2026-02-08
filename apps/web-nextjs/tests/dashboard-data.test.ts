import { describe, expect, it } from 'vitest';
import { dashboardData } from '../lib/dashboard-data';

describe('dashboard data', () => {
  it('contains at least four KPI cards', () => {
    expect(dashboardData.kpis.length).toBeGreaterThanOrEqual(4);
  });

  it('contains recent activities', () => {
    expect(dashboardData.activities.length).toBeGreaterThan(0);
  });
});
