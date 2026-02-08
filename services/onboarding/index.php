<?php
header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$service = getenv('SERVICE_NAME') ?: 'unknown';

$base = [
    'service' => $service,
    'method' => $method,
    'path' => $path,
    'status' => 'ok',
    'timestamp' => gmdate('c'),
];

if ($service === 'tenant' && $path === '/stripe/webhook' && $method === 'POST') {
    echo json_encode($base + ['message' => 'Stripe webhook accepted']);
    exit;
}

if ($service === 'onboarding' && $path === '/workflow/template' && $method === 'GET') {
    echo json_encode($base + [
        'template' => [
            'steps' => ['offer', 'document collection', 'policy acceptance', 'asset assignment', 'orientation'],
        ],
    ]);
    exit;
}

if ($service === 'payroll' && $path === '/run' && $method === 'POST') {
    echo json_encode($base + ['message' => 'Payroll run queued in Redis-backed queue']);
    exit;
}

if ($service === 'user' && $path === '/roles' && $method === 'GET') {
    echo json_encode($base + ['roles' => ['SuperAdmin', 'HRManager', 'PayrollOfficer', 'Employee', 'Auditor']]);
    exit;
}

if ($service === 'reporting' && $path === '/kpis' && $method === 'GET') {
    echo json_encode($base + ['kpis' => ['headcount', 'attrition', 'payroll cost', 'leave utilization']]);
    exit;
}

echo json_encode($base);
