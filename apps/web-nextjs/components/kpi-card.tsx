import React from 'react';

export function KpiCard({ label, value }: { label: string; value: string }) {
  return (
    <article style={{ background: '#fff', borderRadius: 16, padding: 20, boxShadow: '0 8px 28px rgba(15, 23, 42, 0.08)' }}>
      <div style={{ color: '#64748b', fontSize: 14 }}>{label}</div>
      <div style={{ fontSize: 28, fontWeight: 700, marginTop: 8 }}>{value}</div>
    </article>
  );
}
