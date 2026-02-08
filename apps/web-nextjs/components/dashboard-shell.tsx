'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ApiError, apiRequest } from '../lib/api-client';
import { buildKpiCards, defaultActivities, WorkforceKpiResponse } from '../lib/dashboard-data';
import { AppSession, clearSession, readSession } from '../lib/session';
import { KpiCard } from './kpi-card';

type UserRecord = { user_id: string; email: string; role: string; status: string };
type WorkflowRecord = { workflow_id: string; employee_id: string; status: string };
type PayrollRunRecord = { run_id: string; period: string; status: string; net_total: number };
type ExportRecord = { export_id: string; format: string; status: string; expires_at: string; download_url?: string };
type SubscriptionRecord = { plan?: string; status?: string; provider?: string };

type DashboardPayload = {
  users: UserRecord[];
  workflows: WorkflowRecord[];
  payrollRuns: PayrollRunRecord[];
  exports: ExportRecord[];
  metrics: WorkforceKpiResponse;
  subscription: SubscriptionRecord;
};

const defaultMetrics: WorkforceKpiResponse = {
  headcount: 0,
  pending_onboarding: 0,
  monthly_payroll: 0,
  open_compliance_alerts: 0,
  attrition_rate: 0,
  time_to_hire_days: 0,
};

const currentPeriod = (): string => {
  const today = new Date();
  return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
};

const formatTime = (): string => new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit' }).format(new Date());
const formatCurrency = (value: number): string => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(value);
const mapError = (error: unknown): string => (error instanceof Error ? error.message : 'Unexpected request failure.');

const navItems = ['Dashboard', 'User Access', 'Onboarding', 'Payroll', 'Reporting', 'Billing'] as const;
type NavItem = (typeof navItems)[number];

export function DashboardShell() {
  const router = useRouter();
  const [session, setSession] = useState<AppSession | null>(null);
  const [dashboard, setDashboard] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionBusy, setActionBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [activities, setActivities] = useState<string[]>(defaultActivities);
  const [activeView, setActiveView] = useState<NavItem>('Dashboard');

  const [inviteEmail, setInviteEmail] = useState('manager@acme-enterprise.com');
  const [inviteRole, setInviteRole] = useState('manager');
  const [employeeId, setEmployeeId] = useState('emp-1001');
  const [payrollPeriod, setPayrollPeriod] = useState(currentPeriod());
  const [exportFormat, setExportFormat] = useState('csv');
  const [templateTaxRate, setTemplateTaxRate] = useState('0.16');
  const [templateBenefitsRate, setTemplateBenefitsRate] = useState('0.07');

  const recordActivity = (message: string): void => {
    const line = `[${formatTime()}] ${message}`;
    setActivities((existing) => [line, ...existing].slice(0, 8));
  };

  const handleAuthError = (err: unknown): void => {
    if (err instanceof ApiError && err.status === 401) {
      clearSession();
      router.replace('/');
      return;
    }
    setError(mapError(err));
  };

  const loadDashboard = async (activeSession: AppSession, silent = false): Promise<void> => {
    if (!silent) setLoading(true);
    try {
      await apiRequest('gateway', '/auth/session', { tenantId: activeSession.tenantId, token: activeSession.token });
      const [users, workflows, payrollRuns, metrics, exports, subscription] = await Promise.all([
        apiRequest<{ users: UserRecord[] }>('userManagement', '/users', { tenantId: activeSession.tenantId, token: activeSession.token }),
        apiRequest<{ workflows: WorkflowRecord[] }>('onboarding', '/onboarding/workflows', { tenantId: activeSession.tenantId, token: activeSession.token }),
        apiRequest<{ runs: PayrollRunRecord[] }>('payroll', '/payroll/runs', { tenantId: activeSession.tenantId, token: activeSession.token }),
        apiRequest<WorkforceKpiResponse>('reporting', '/reports/workforce-kpis', { tenantId: activeSession.tenantId, token: activeSession.token }),
        apiRequest<{ exports: ExportRecord[] }>('reporting', '/reports/exports', { tenantId: activeSession.tenantId, token: activeSession.token }),
        apiRequest<SubscriptionRecord>('gateway', '/billing/subscription', { tenantId: activeSession.tenantId, token: activeSession.token }),
      ]);
      setDashboard({ users: users.users ?? [], workflows: workflows.workflows ?? [], payrollRuns: payrollRuns.runs ?? [], exports: exports.exports ?? [], metrics, subscription });
      setError(null);
    } catch (err) {
      handleAuthError(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const activeSession = readSession();
    if (!activeSession) return router.replace('/');
    setSession(activeSession);
    void loadDashboard(activeSession);
  }, [router]);

  const performAction = async (actionName: string, run: () => Promise<void>, successMessage: string): Promise<void> => {
    if (!session) return;
    setError(null);
    setActionBusy(actionName);
    try {
      await run();
      recordActivity(successMessage);
      await loadDashboard(session, true);
    } catch (err) {
      handleAuthError(err);
    } finally {
      setActionBusy(null);
    }
  };

  const onInvite = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!session) return;
    await performAction('invite', async () => {
      await apiRequest('userManagement', '/users/invite', { method: 'POST', tenantId: session.tenantId, token: session.token, body: { email: inviteEmail, role: inviteRole } });
      setInviteEmail('');
    }, `Invitation sent to ${inviteEmail}.`);
  };

  const onCreateWorkflow = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!session) return;
    await performAction('workflow', async () => {
      await apiRequest('onboarding', '/onboarding/workflows', {
        method: 'POST', tenantId: session.tenantId, token: session.token,
        body: { employee_id: employeeId, priority: 'high', steps: ['collect_documents', 'it_setup', 'orientation', 'security_training'] },
      });
      setEmployeeId('');
    }, `Onboarding workflow created for ${employeeId}.`);
  };

  const onRunPayroll = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!session) return;
    await performAction('payroll', async () => {
      await apiRequest('payroll', '/payroll/runs', { method: 'POST', tenantId: session.tenantId, token: session.token, body: { period: payrollPeriod } });
    }, `Payroll run submitted for ${payrollPeriod}.`);
  };

  const onCreateTemplate = async (): Promise<void> => {
    if (!session) return;
    await performAction('template', async () => {
      await apiRequest('payroll', '/payroll/templates', {
        method: 'POST', tenantId: session.tenantId, token: session.token,
        body: { name: 'Custom Template', tax_rate: Number(templateTaxRate), benefits_rate: Number(templateBenefitsRate) },
      });
    }, 'Payroll template saved.');
  };

  const onExportReport = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!session) return;
    await performAction('export', async () => {
      await apiRequest('reporting', '/reports/export', { method: 'POST', tenantId: session.tenantId, token: session.token, body: { format: exportFormat } });
    }, `Workforce report generated (${exportFormat.toUpperCase()}).`);
  };

  const onDownloadReport = async (exportId: string): Promise<void> => {
    if (!session) return;
    await performAction('download', async () => {
      const data = await apiRequest<{ filename: string; content: unknown }>('reporting', `/reports/exports/${exportId}/download`, { tenantId: session.tenantId, token: session.token });
      if (typeof window !== 'undefined') {
        const blob = new Blob([JSON.stringify(data.content, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = data.filename;
        link.click();
        URL.revokeObjectURL(url);
      }
    }, `Downloaded report ${exportId}.`);
  };

  const onUpgradePlan = async (): Promise<void> => {
    if (!session) return;
    await performAction('upgrade', async () => {
      const response = await apiRequest<{ checkout_url: string }>('gateway', '/billing/checkout-session', { method: 'POST', tenantId: session.tenantId, token: session.token, body: { plan: 'enterprise' } });
      window.open(response.checkout_url, '_blank', 'noopener,noreferrer');
    }, 'Stripe checkout session generated for Enterprise plan.');
  };

  const cards = useMemo(() => buildKpiCards(dashboard?.metrics ?? defaultMetrics), [dashboard]);

  return (
    <main className="app-shell">
      <aside className="app-sidebar surface">
        <p className="eyebrow">Enterprise Workspace</p>
        <h2>PulseHRMS</h2>
        <p className="muted-text sidebar-copy">Tenant: <strong>{session?.tenantId ?? '-'}</strong></p>
        <nav className="app-nav">
          {navItems.map((item) => (
            <button key={item} className={`app-nav-item ${activeView === item ? 'active' : ''}`} onClick={() => setActiveView(item)}>{item}</button>
          ))}
        </nav>
      </aside>

      <section className="app-main">
        <header className="app-header surface">
          <div>
            <p className="eyebrow">Authenticated via SSO</p>
            <h1>HR Command Center</h1>
            <p className="muted-text">{session?.user.email} ({session?.user.role}){dashboard?.subscription.plan ? ` • Plan ${dashboard.subscription.plan}` : ''}</p>
          </div>
          <div className="header-actions"><button className="ghost-btn" onClick={() => { clearSession(); router.replace('/'); }}>Sign out</button></div>
        </header>

        {error ? <div className="banner-error">{error}</div> : null}

        {loading || !dashboard ? <section className="surface module-card loading-card">Loading tenant workspace...</section> : (
          <>
            {(activeView === 'Dashboard' || activeView === 'Reporting') && <section className="kpi-grid">{cards.map((c) => <KpiCard key={c.label} {...c} />)}</section>}

            <section className="module-grid">
              {(activeView === 'Dashboard' || activeView === 'User Access') && <article className="module-card surface"><h3>User Access</h3><form className="inline-form" onSubmit={onInvite}><input value={inviteEmail} onChange={(e) => setInviteEmail(e.target.value)} required /><select value={inviteRole} onChange={(e) => setInviteRole(e.target.value)}><option value="employee">employee</option><option value="manager">manager</option><option value="hr-admin">hr-admin</option><option value="finance-admin">finance-admin</option></select><button className="primary-btn" type="submit" disabled={actionBusy !== null}>Invite</button></form><div className="list-block">{dashboard.users.slice(0, 4).map((u) => <p key={u.user_id}>{u.email} <span className="pill">{u.role}</span></p>)}</div></article>}

              {(activeView === 'Dashboard' || activeView === 'Onboarding') && <article className="module-card surface"><h3>Onboarding</h3><form className="inline-form" onSubmit={onCreateWorkflow}><input value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} required /><button className="primary-btn" type="submit" disabled={actionBusy !== null}>New Workflow</button></form><div className="list-block">{dashboard.workflows.slice(0, 4).map((w) => <p key={w.workflow_id}>{w.employee_id} <span className="pill">{w.status}</span></p>)}</div></article>}

              {(activeView === 'Dashboard' || activeView === 'Payroll') && <article className="module-card surface"><h3>Payroll</h3><form className="inline-form" onSubmit={onRunPayroll}><input value={payrollPeriod} onChange={(e) => setPayrollPeriod(e.target.value)} required /><button className="primary-btn" type="submit" disabled={actionBusy !== null}>Run Payroll</button></form><div className="inline-form"><input value={templateTaxRate} onChange={(e) => setTemplateTaxRate(e.target.value)} placeholder="Tax rate" /><input value={templateBenefitsRate} onChange={(e) => setTemplateBenefitsRate(e.target.value)} placeholder="Benefits rate" /><button className="ghost-btn" type="button" onClick={() => void onCreateTemplate()}>Save Template</button></div><div className="list-block">{dashboard.payrollRuns.slice(0, 4).map((r) => <p key={r.run_id}>{r.period} <span className="pill">{r.status}</span> {formatCurrency(r.net_total)}</p>)}</div></article>}

              {(activeView === 'Dashboard' || activeView === 'Reporting' || activeView === 'Billing') && <article className="module-card surface"><h3>Reporting & Billing</h3><form className="inline-form" onSubmit={onExportReport}><select value={exportFormat} onChange={(e) => setExportFormat(e.target.value)}><option value="csv">csv</option><option value="pdf">pdf</option><option value="json">json</option></select><button className="primary-btn" type="submit" disabled={actionBusy !== null}>Generate Report</button></form><button className="ghost-btn full-width" onClick={() => void onUpgradePlan()} disabled={actionBusy !== null}>Upgrade to Enterprise</button><div className="list-block">{dashboard.exports.slice(0, 3).map((record) => <p key={record.export_id}>{record.format.toUpperCase()} <span className="pill">{record.status}</span> <button className="linkish" onClick={() => void onDownloadReport(record.export_id)}>Download</button></p>)}</div></article>}
            </section>

            <section className="module-card surface activity-card"><h3>Recent Activity</h3><div className="list-block">{activities.map((item) => <p key={item}>{item}</p>)}</div></section>
          </>
        )}
      </section>
    </main>
  );
}
