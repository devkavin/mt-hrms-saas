import { KpiCard } from '../../components/kpi-card';
import { dashboardData } from '../../lib/dashboard-data';

export default function DashboardPage() {
  return (
    <main style={{ padding: 32, maxWidth: 1200, margin: '0 auto' }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <h1 style={{ margin: 0 }}>Executive HR Dashboard</h1>
          <p style={{ marginTop: 8, color: '#64748b' }}>Real-time workforce insights across all tenants.</p>
        </div>
        <button style={{ border: 0, background: '#4f46e5', color: '#fff', borderRadius: 12, padding: '10px 16px' }}>
          Upgrade Plan
        </button>
      </header>

      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(0, 1fr))', gap: 16 }}>
        {dashboardData.kpis.map((kpi) => (
          <KpiCard key={kpi.label} label={kpi.label} value={kpi.value} />
        ))}
      </section>

      <section style={{ marginTop: 24, background: '#fff', borderRadius: 16, padding: 20 }}>
        <h2 style={{ marginTop: 0 }}>Recent Activity</h2>
        <ul>
          {dashboardData.activities.map((activity) => (
            <li key={activity}>{activity}</li>
          ))}
        </ul>
      </section>
    </main>
  );
}
