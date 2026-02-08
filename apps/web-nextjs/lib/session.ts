export type SessionUser = {
  id: string;
  email: string;
  role: string;
};

export type AppSession = {
  tenantId: string;
  token: string;
  user: SessionUser;
  createdAt: string;
  expiresIn: number;
};

const SESSION_KEY = 'mt_hrms_session_v1';

export const readSession = (): AppSession | null => {
  if (typeof window === 'undefined') {
    return null;
  }

  const raw = window.localStorage.getItem(SESSION_KEY);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as Partial<AppSession>;
    if (!parsed || typeof parsed !== 'object') {
      return null;
    }

    if (!parsed.token || !parsed.tenantId || !parsed.user) {
      return null;
    }

    return {
      tenantId: String(parsed.tenantId),
      token: String(parsed.token),
      user: {
        id: String(parsed.user.id),
        email: String(parsed.user.email),
        role: String(parsed.user.role),
      },
      createdAt: String(parsed.createdAt ?? new Date().toISOString()),
      expiresIn: Number(parsed.expiresIn ?? 0),
    };
  } catch {
    return null;
  }
};

export const saveSession = (session: AppSession): void => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(SESSION_KEY, JSON.stringify(session));
};

export const clearSession = (): void => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.removeItem(SESSION_KEY);
};
