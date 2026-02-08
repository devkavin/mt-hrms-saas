import React from 'react';
import './globals.css';

export const metadata = {
  title: 'PulseHRMS',
  description: 'World-class HRMS SaaS platform'
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
