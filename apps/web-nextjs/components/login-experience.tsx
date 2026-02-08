'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiRequest } from '../lib/api-client';
import { readSession, saveSession } from '../lib/session';

type ProvidersResponse = {
  providers: string[];
};

type StartSsoResponse = {
  state: string;
  provider: string;
};

type SsoCallbackResponse = {
  token: string;
  expires_in: number;
  user: {
    id: string;
    email: string;
    role: string;
  };
};

const toTenantSlug = (value: string): string =>
  value
    .toLowerCase()
    .replace(/[^a-z0-9-]/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^-|-$/g, '');

const toCompanyName = (slug: string): string =>
  slug
    .split('-')
    .filter((part) => part.length > 0)
    .map((part) => part.slice(0, 1).toUpperCase() + part.slice(1))
    .join(' ');

export function LoginExperience() {
  const router = useRouter();
  const [mode, setMode] = useState<'signin' | 'register'>('signin');
  const [tenantInput, setTenantInput] = useState('acme-enterprise');
  const [email, setEmail] = useState('admin@acme-enterprise.com');
  const [password, setPassword] = useState('');
  const [provider, setProvider] = useState('okta');
  const [providers, setProviders] = useState<string[]>(['okta', 'azure-ad', 'google-workspace', 'saml-custom']);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const tenantId = useMemo(() => toTenantSlug(tenantInput), [tenantInput]);

  useEffect(() => {
    const activeSession = readSession();
    if (activeSession) {
      router.replace('/dashboard');
      return;
    }

    void (async () => {
      try {
        const response = await apiRequest<ProvidersResponse>('gateway', '/auth/sso/providers');
        if (Array.isArray(response.providers) && response.providers.length > 0) {
          setProviders(response.providers);
          setProvider(response.providers[0]);
        }
      } catch {
        // fallback providers
      }
    })();
  }, [router]);

  const onRegister = async (): Promise<void> => {
    await apiRequest('gateway', '/tenants/register', {
      method: 'POST',
      body: {
        name: toCompanyName(tenantId),
        slug: tenantId,
        admin_email: email,
        password,
        domain: `${tenantId}.example.com`,
        plan: 'enterprise',
      },
    });

    await apiRequest('gateway', '/auth/sso/configure', {
      method: 'POST',
      tenantId,
      body: {
        provider,
        mfa_required: true,
        jit_provisioning: true,
      },
    });
  };

  const onSignIn = async (): Promise<void> => {
    await apiRequest('gateway', '/auth/password/verify', {
      method: 'POST',
      body: { tenant_id: tenantId, password },
    });

    const start = await apiRequest<StartSsoResponse>('gateway', '/auth/sso/start', {
      method: 'POST',
      tenantId,
      body: {
        provider,
        email,
      },
    });

    const callback = await apiRequest<SsoCallbackResponse>('gateway', '/auth/sso/callback', {
      method: 'POST',
      tenantId,
      body: {
        state: start.state,
        code: 'mock-local-code',
      },
    });

    saveSession({
      tenantId,
      token: callback.token,
      user: callback.user,
      createdAt: new Date().toISOString(),
      expiresIn: callback.expires_in,
    });
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setBusy(true);

    try {
      if (!tenantId) {
        throw new Error('Tenant identifier is required.');
      }

      if (!email.includes('@')) {
        throw new Error('Enter a valid work email.');
      }

      if (password.length < 8) {
        throw new Error('Password must be at least 8 characters.');
      }

      if (mode === 'register') {
        await onRegister();
      }

      await onSignIn();
      router.push('/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to complete login.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="auth-root">
      <section className="auth-hero surface">
        <p className="eyebrow">Enterprise HR Operating System</p>
        <h1>Tenant-aware SSO with registration and password validation.</h1>
        <p>Only registered tenants can sign in and every sign-in now requires tenant credentials before SSO callback.</p>
        <div className="hero-badges">
          <span>Multi-Tenant</span>
          <span>SSO Required</span>
          <span>Password Verified</span>
        </div>
      </section>

      <section className="auth-card surface">
        <div className="auth-mode-toggle">
          <button className={`ghost-btn ${mode === 'signin' ? 'active-chip' : ''}`} type="button" onClick={() => setMode('signin')}>
            Sign in
          </button>
          <button className={`ghost-btn ${mode === 'register' ? 'active-chip' : ''}`} type="button" onClick={() => setMode('register')}>
            Register tenant
          </button>
        </div>

        <h2>{mode === 'signin' ? 'Sign in with SSO' : 'Register tenant + sign in'}</h2>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label className="field">
            <span>Tenant ID</span>
            <input value={tenantInput} onChange={(event) => setTenantInput(event.target.value)} placeholder="acme-enterprise" required />
          </label>

          <label className="field">
            <span>Work Email</span>
            <input value={email} onChange={(event) => setEmail(event.target.value)} placeholder="admin@acme-enterprise.com" required />
          </label>

          <label className="field">
            <span>Password</span>
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Minimum 8 characters" required />
          </label>

          <label className="field">
            <span>SSO Provider</span>
            <select value={provider} onChange={(event) => setProvider(event.target.value)}>
              {providers.map((providerName) => (
                <option key={providerName} value={providerName}>
                  {providerName}
                </option>
              ))}
            </select>
          </label>

          {error ? <p className="form-error">{error}</p> : null}

          <button className="primary-btn" type="submit" disabled={busy}>
            {busy ? 'Working...' : mode === 'signin' ? 'Continue with SSO' : 'Register & Continue'}
          </button>
        </form>
      </section>
    </main>
  );
}
