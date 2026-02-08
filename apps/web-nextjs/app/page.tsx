import Link from 'next/link';

export default function HomePage() {
  return (
    <main style={{ padding: '40px', maxWidth: 960, margin: '0 auto' }}>
      <h1>HRMS SaaS Command Center</h1>
      <p>Enterprise-grade multi-tenant HR operations with onboarding, payroll, reports, and billing.</p>
      <Link href="/dashboard">Open Dashboard</Link>
    </main>
  );
}
