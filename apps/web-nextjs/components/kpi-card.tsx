import React from 'react';

export function KpiCard({ label, value, trend }: { label: string; value: string; trend: string }) {
  return (
    <article className="card">
      <div className="muted" style={{ fontSize: 14 }}>{label}</div>
      <div className="kpi-value" style={{ marginTop: 8 }}>{value}</div>
      <div style={{ marginTop: 6, color: '#2563eb', fontSize: 13 }}>{trend}</div>
    </article>
  );
}
