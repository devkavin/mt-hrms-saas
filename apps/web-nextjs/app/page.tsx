import Link from 'next/link';

export default function HomePage() {
  return (
    <main style={{ maxWidth: 900, margin: '100px auto', textAlign: 'center' }}>
      <h1 style={{ fontSize: 52, marginBottom: 8 }}>PulseHRMS</h1>
      <p style={{ color: '#64748b', marginBottom: 22 }}>
        Premium multi-tenant HRMS for onboarding, payroll, workforce intelligence, and enterprise SSO.
      </p>
      <Link href="/dashboard" style={{ background: '#2f6fed', color: '#fff', padding: '12px 18px', borderRadius: 10 }}>
        Enter HR Dashboard
      </Link>
    </main>
  );
}
