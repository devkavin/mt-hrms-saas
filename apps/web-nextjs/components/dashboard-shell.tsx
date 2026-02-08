'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ApiError, apiRequest } from '../lib/api-client';
import { buildKpiCards, defaultActivities, WorkforceKpiResponse } from '../lib/dashboard-data';
import { AppSession, clearSession, readSession } from '../lib/session';
import { KpiCard } from './kpi-card';

type UserRecord = {
  user_id: string;
  email: string;
  role: string;
  status: string;
};

type WorkflowRecord = {
  workflow_id: string;
  employee_id: string;
  status: string;
};

type PayrollRunRecord = {
  run_id: string;
  period: string;
  status: string;
  net_total: number;
};

type ExportRecord = {
  export_id: string;
  format: string;
  status: string;
  expires_at: string;
};

type SubscriptionRecord = {
  plan?: string;
  status?: string;
  provider?: string;
};

type DashboardPayload = {
  users: UserRecord[];
  workflows: WorkflowRecord[];
  payrollRuns: PayrollRunRecord[];
  exports: ExportRecord[];
  metrics: WorkforceKpiResponse;
  subscription: SubscriptionRecord;
};

type UserListResponse = {
  users: UserRecord[];
};

type WorkflowListResponse = {
  workflows: WorkflowRecord[];
};

type PayrollRunListResponse = {
  runs: PayrollRunRecord[];
};

type ExportListResponse = {
  exports: ExportRecord[];
};

type BillingCheckoutResponse = {
  checkout_url: string;
  plan: string;
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

const formatTime = (): string =>
  new Intl.DateTimeFormat('en-US', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date());

const formatCurrency = (value: number): string =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(value);

const mapError = (error: unknown): string => {
  if (error instanceof Error) {
    return error.message;
  }

  return 'Unexpected request failure.';
};

export function DashboardShell() {
  const router = useRouter();
  const [session, setSession] = useState<AppSession | null>(null);
  const [dashboard, setDashboard] = useState<DashboardPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionBusy, setActionBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [activities, setActivities] = useState<string[]>(defaultActivities);

  const [inviteEmail, setInviteEmail] = useState('manager@acme-enterprise.com');
  const [inviteRole, setInviteRole] = useState('manager');
  const [employeeId, setEmployeeId] = useState('emp-1001');
  const [payrollPeriod, setPayrollPeriod] = useState(currentPeriod());
  const [exportFormat, setExportFormat] = useState('csv');

  const recordActivity = (message: string): void => {
    const line = `[${formatTime()}] ${message}`;
    setActivities((existing) => [line, ...existing].slice(0, 8));
  };

  const handleAuthError = (error: unknown): void => {
    if (error instanceof ApiError && error.status === 401) {
      clearSession();
      router.replace('/');
      return;
    }

    setError(mapError(error));
  };

  const loadDashboard = async (activeSession: AppSession, silent = false): Promise<void> => {
    if (!silent) {
      setLoading(true);
    }

    try {
      await apiRequest('gateway', '/auth/session', {
        tenantId: activeSession.tenantId,
        token: activeSession.token,
      });

      const [usersResponse, workflowsResponse, payrollResponse, metricsResponse, exportsResponse, subscriptionResponse] =
        await Promise.all([
          apiRequest<UserListResponse>('userManagement', '/users', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
          apiRequest<WorkflowListResponse>('onboarding', '/onboarding/workflows', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
          apiRequest<PayrollRunListResponse>('payroll', '/payroll/runs', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
          apiRequest<WorkforceKpiResponse>('reporting', '/reports/workforce-kpis', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
          apiRequest<ExportListResponse>('reporting', '/reports/exports', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
          apiRequest<SubscriptionRecord>('gateway', '/billing/subscription', {
            tenantId: activeSession.tenantId,
            token: activeSession.token,
          }),
        ]);

      setDashboard({
        users: usersResponse.users ?? [],
        workflows: workflowsResponse.workflows ?? [],
        payrollRuns: payrollResponse.runs ?? [],
        exports: exportsResponse.exports ?? [],
        metrics: metricsResponse,
        subscription: subscriptionResponse,
      });
      setError(null);
    } catch (error) {
      handleAuthError(error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const activeSession = readSession();
    if (!activeSession) {
      router.replace('/');
      return;
    }

    setSession(activeSession);
    void loadDashboard(activeSession);
  }, [router]);

  const performAction = async (actionName: string, run: () => Promise<void>, successMessage: string): Promise<void> => {
    if (!session) {
      return;
    }

    setError(null);
    setActionBusy(actionName);
    try {
      await run();
      recordActivity(successMessage);
      await loadDashboard(session, true);
    } catch (error) {
      handleAuthError(error);
    } finally {
      setActionBusy(null);
    }
  };

  const onInvite = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (!session) {
      return;
    }

    await performAction(
      'invite',
      async () => {
        await apiRequest('userManagement', '/users/invite', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { email: inviteEmail, role: inviteRole },
        });
        setInviteEmail('');
      },
      `Invitation sent to ${inviteEmail}.`
    );
  };

  const onDirectorySync = async (): Promise<void> => {
    if (!session) {
      return;
    }

    await performAction(
      'sync',
      async () => {
        await apiRequest('userManagement', '/sso/directory-sync', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { provider: 'okta' },
        });
      },
      'Directory sync job queued from IdP.'
    );
  };

  const onCreateWorkflow = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (!session) {
      return;
    }

    await performAction(
      'workflow',
      async () => {
        await apiRequest('onboarding', '/onboarding/workflows', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { employee_id: employeeId },
        });
        setEmployeeId('');
      },
      `Onboarding workflow created for ${employeeId}.`
    );
  };

  const onRunPayroll = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (!session) {
      return;
    }

    await performAction(
      'payroll',
      async () => {
        await apiRequest('payroll', '/payroll/runs', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { period: payrollPeriod },
        });
      },
      `Payroll run submitted for ${payrollPeriod}.`
    );
  };

  const onExportReport = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (!session) {
      return;
    }

    await performAction(
      'export',
      async () => {
        await apiRequest('reporting', '/reports/export', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { format: exportFormat },
        });
      },
      `Workforce report export queued (${exportFormat.toUpperCase()}).`
    );
  };

  const onUpgradePlan = async (): Promise<void> => {
    if (!session) {
      return;
    }

    await performAction(
      'upgrade',
      async () => {
        const response = await apiRequest<BillingCheckoutResponse>('gateway', '/billing/checkout-session', {
          method: 'POST',
          tenantId: session.tenantId,
          token: session.token,
          body: { plan: 'enterprise' },
        });

        if (typeof window !== 'undefined') {
          window.open(response.checkout_url, '_blank', 'noopener,noreferrer');
        }
      },
      'Stripe checkout session generated for Enterprise plan.'
    );
  };

  const onSignOut = (): void => {
    clearSession();
    router.replace('/');
  };

  const cards = useMemo(() => buildKpiCards(dashboard?.metrics ?? defaultMetrics), [dashboard]);

  return (
    <main className="app-shell">
      <aside className="app-sidebar surface">
        <p className="eyebrow">Enterprise Workspace</p>
        <h2>PulseHRMS</h2>
        <p className="muted-text sidebar-copy">
          Tenant: <strong>{session?.tenantId ?? '-'}</strong>
        </p>
        <nav className="app-nav">
          <span className="app-nav-item active">Dashboard</span>
          <span className="app-nav-item">User Access</span>
          <span className="app-nav-item">Onboarding</span>
          <span className="app-nav-item">Payroll</span>
          <span className="app-nav-item">Reporting</span>
          <span className="app-nav-item">Billing</span>
        </nav>
      </aside>

      <section className="app-main">
        <header className="app-header surface">
          <div>
            <p className="eyebrow">Authenticated via SSO</p>
            <h1>HR Command Center</h1>
            <p className="muted-text">
              {session?.user.email} ({session?.user.role}){dashboard?.subscription.plan ? ` • Plan ${dashboard.subscription.plan}` : ''}
            </p>
          </div>
          <div className="header-actions">
            <button className="ghost-btn" onClick={() => void onDirectorySync()} disabled={actionBusy !== null}>
              Sync Directory
            </button>
            <button className="ghost-btn" onClick={onSignOut}>
              Sign out
            </button>
          </div>
        </header>

        {error ? <div className="banner-error">{error}</div> : null}

        {loading || !dashboard ? (
          <section className="surface module-card loading-card">Loading tenant workspace...</section>
        ) : (
          <>
            <section className="kpi-grid">
              {cards.map((card) => (
                <KpiCard key={card.label} label={card.label} value={card.value} hint={card.hint} tone={card.tone} />
              ))}
            </section>

            <section className="module-grid">
              <article className="module-card surface">
                <h3>User Access</h3>
                <p className="muted-text">Invite users and assign secure roles.</p>
                <form className="inline-form" onSubmit={onInvite}>
                  <input value={inviteEmail} onChange={(event) => setInviteEmail(event.target.value)} placeholder="user@company.com" required />
                  <select value={inviteRole} onChange={(event) => setInviteRole(event.target.value)}>
                    <option value="employee">employee</option>
                    <option value="manager">manager</option>
                    <option value="hr-admin">hr-admin</option>
                    <option value="finance-admin">finance-admin</option>
                  </select>
                  <button className="primary-btn" type="submit" disabled={actionBusy !== null}>
                    {actionBusy === 'invite' ? 'Sending...' : 'Invite'}
                  </button>
                </form>
                <div className="list-block">
                  {dashboard.users.slice(0, 4).map((user) => (
                    <p key={user.user_id}>
                      {user.email} <span className="pill">{user.role}</span>
                    </p>
                  ))}
                  {dashboard.users.length === 0 ? <p className="muted-text">No users found yet.</p> : null}
                </div>
              </article>

              <article className="module-card surface">
                <h3>Onboarding</h3>
                <p className="muted-text">Create and track onboarding workflows.</p>
                <form className="inline-form" onSubmit={onCreateWorkflow}>
                  <input value={employeeId} onChange={(event) => setEmployeeId(event.target.value)} placeholder="employee id" required />
                  <button className="primary-btn" type="submit" disabled={actionBusy !== null}>
                    {actionBusy === 'workflow' ? 'Creating...' : 'New Workflow'}
                  </button>
                </form>
                <div className="list-block">
                  {dashboard.workflows.slice(0, 4).map((workflow) => (
                    <p key={workflow.workflow_id}>
                      {workflow.employee_id} <span className="pill">{workflow.status}</span>
                    </p>
                  ))}
                  {dashboard.workflows.length === 0 ? <p className="muted-text">No workflows yet.</p> : null}
                </div>
              </article>

              <article className="module-card surface">
                <h3>Payroll</h3>
                <p className="muted-text">Run payroll with period-level lock protection.</p>
                <form className="inline-form" onSubmit={onRunPayroll}>
                  <input value={payrollPeriod} onChange={(event) => setPayrollPeriod(event.target.value)} placeholder="YYYY-MM" required />
                  <button className="primary-btn" type="submit" disabled={actionBusy !== null}>
                    {actionBusy === 'payroll' ? 'Running...' : 'Run Payroll'}
                  </button>
                </form>
                <div className="list-block">
                  {dashboard.payrollRuns.slice(0, 4).map((run) => (
                    <p key={run.run_id}>
                      {run.period} <span className="pill">{run.status}</span> {formatCurrency(run.net_total)}
                    </p>
                  ))}
                  {dashboard.payrollRuns.length === 0 ? <p className="muted-text">No payroll runs yet.</p> : null}
                </div>
              </article>

              <article className="module-card surface">
                <h3>Reporting & Billing</h3>
                <p className="muted-text">Queue exports and manage plan upgrades.</p>
                <form className="inline-form" onSubmit={onExportReport}>
                  <select value={exportFormat} onChange={(event) => setExportFormat(event.target.value)}>
                    <option value="csv">csv</option>
                    <option value="pdf">pdf</option>
                  </select>
                  <button className="primary-btn" type="submit" disabled={actionBusy !== null}>
                    {actionBusy === 'export' ? 'Queueing...' : 'Export'}
                  </button>
                </form>
                <button className="ghost-btn full-width" onClick={() => void onUpgradePlan()} disabled={actionBusy !== null}>
                  {actionBusy === 'upgrade' ? 'Creating Checkout...' : 'Upgrade to Enterprise'}
                </button>
                <div className="list-block">
                  {dashboard.exports.slice(0, 3).map((record) => (
                    <p key={record.export_id}>
                      {record.format.toUpperCase()} <span className="pill">{record.status}</span> exp {record.expires_at.slice(0, 10)}
                    </p>
                  ))}
                  {dashboard.exports.length === 0 ? <p className="muted-text">No exports queued yet.</p> : null}
                </div>
              </article>
            </section>

            <section className="module-card surface activity-card">
              <h3>Recent Activity</h3>
              <div className="list-block">
                {activities.map((item) => (
                  <p key={item}>{item}</p>
                ))}
              </div>
            </section>
          </>
        )}
      </section>
    </main>
  );
}
