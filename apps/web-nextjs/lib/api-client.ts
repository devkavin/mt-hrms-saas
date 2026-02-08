export type ServiceName = 'gateway' | 'userManagement' | 'onboarding' | 'payroll' | 'reporting';

export class ApiError extends Error {
  status: number;
  payload: unknown;

  constructor(message: string, status: number, payload: unknown) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

type ApiMethod = 'GET' | 'POST' | 'PATCH';

type ApiRequestOptions = {
  method?: ApiMethod;
  tenantId?: string;
  token?: string;
  body?: unknown;
};

const normalizeBase = (url: string): string => url.replace(/\/+$/, '');

const serviceBaseUrls: Record<ServiceName, string> = {
  gateway: normalizeBase(process.env.NEXT_PUBLIC_GATEWAY_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000/api'),
  userManagement: normalizeBase(process.env.NEXT_PUBLIC_USER_MGMT_URL ?? 'http://localhost:8003/api'),
  onboarding: normalizeBase(process.env.NEXT_PUBLIC_ONBOARDING_URL ?? 'http://localhost:8001/api'),
  payroll: normalizeBase(process.env.NEXT_PUBLIC_PAYROLL_URL ?? 'http://localhost:8002/api'),
  reporting: normalizeBase(process.env.NEXT_PUBLIC_REPORTING_URL ?? 'http://localhost:8004/api'),
};

const pathWithLeadingSlash = (path: string): string => (path.startsWith('/') ? path : `/${path}`);

const parsePayload = (raw: string): unknown => {
  if (raw.trim() === '') {
    return {};
  }

  try {
    return JSON.parse(raw);
  } catch {
    return { raw };
  }
};

export async function apiRequest<T>(service: ServiceName, path: string, options: ApiRequestOptions = {}): Promise<T> {
  const url = `${serviceBaseUrls[service]}${pathWithLeadingSlash(path)}`;
  const method = options.method ?? 'GET';

  const headers: Record<string, string> = {
    Accept: 'application/json',
  };

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  if (options.tenantId) {
    headers['X-Tenant-ID'] = options.tenantId;
  }

  if (options.token) {
    headers['Authorization'] = `Bearer ${options.token}`;
  }

  const response = await fetch(url, {
    method,
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    cache: 'no-store',
  });

  const text = await response.text();
  const payload = parsePayload(text);

  if (!response.ok) {
    const message = typeof payload === 'object' && payload !== null && 'error' in payload ? String((payload as { error: unknown }).error) : `API request failed (${response.status})`;
    throw new ApiError(message, response.status, payload);
  }

  return payload as T;
}
