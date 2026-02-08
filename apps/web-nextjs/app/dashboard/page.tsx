import { KpiCard } from '../../components/kpi-card';
import { dashboardData } from '../../lib/dashboard-data';

export default function DashboardPage() {
  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand">PulseHRMS</div>
        <div className="muted" style={{ color: '#93c5fd' }}>Acme Holdings • Enterprise</div>

        <nav className="nav-group">
          <a className="nav-item active" href="/dashboard">Dashboard</a>
          <a className="nav-item" href="#">Employee Onboarding</a>
          <a className="nav-item" href="#">Payroll</a>
          <a className="nav-item" href="#">User & Access</a>
          <a className="nav-item" href="#">Reports</a>
          <a className="nav-item" href="#">Billing</a>
        </nav>
      </aside>

      <main className="main">
        <header className="topbar">
          <div>
            <h1 style={{ margin: 0 }}>HR Command Center</h1>
            <p className="muted" style={{ marginTop: 6 }}>Manage people operations, payroll, compliance, and identity from one place.</p>
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <button className="button secondary">Configure SSO</button>
            <button className="button primary">Upgrade Plan</button>
          </div>
        </header>

        <section className="grid-4">
          {dashboardData.kpis.map((kpi) => (
            <KpiCard key={kpi.label} label={kpi.label} value={kpi.value} trend={kpi.trend} />
          ))}
        </section>

        <section className="layout-2">
          <article className="card">
            <h2 style={{ marginTop: 0 }}>Employee Onboarding Pipeline</h2>
            <table className="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Stage</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {dashboardData.onboardingPipeline.map((row) => (
                  <tr key={row.name}>
                    <td>{row.name}</td>
                    <td>{row.role}</td>
                    <td>{row.stage}</td>
                    <td><span className={`badge ${row.status}`}>{row.status}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </article>

          <div style={{ display: 'grid', gap: 14 }}>
            <article className="card">
              <h3 style={{ marginTop: 0 }}>Payroll Operations</h3>
              <ul>
                {dashboardData.payrollQueue.map((item) => <li key={item}>{item}</li>)}
              </ul>
            </article>

            <article className="card" style={{ background: 'linear-gradient(135deg, #eff6ff, #e0e7ff)' }}>
              <h3 style={{ marginTop: 0 }}>Identity & SSO</h3>
              <p className="muted">Connect Google Workspace, Azure AD, Okta, and generic SAML providers.</p>
              <button className="button primary">Enable SSO for your tenant</button>
            </article>

            <article className="card">
              <h3 style={{ marginTop: 0 }}>Announcements</h3>
              <ul>
                {dashboardData.announcements.map((announcement) => <li key={announcement}>{announcement}</li>)}
              </ul>
            </article>
          </div>
        </section>
      </main>
    </div>
  );
}
