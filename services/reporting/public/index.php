<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/bootstrap.php';

use MtHrms\Shared\AuthTokenService;
use MtHrms\Shared\HttpException;
use MtHrms\Shared\IdGenerator;
use MtHrms\Shared\JsonResponse;
use MtHrms\Shared\JsonStore;
use MtHrms\Shared\Request;
use MtHrms\Shared\Router;
use MtHrms\Shared\TenantGuard;

$request = Request::fromGlobals();
$router = new Router();
$tokens = new AuthTokenService();

$users = new JsonStore('user_management_users');
$workflows = new JsonStore('onboarding_workflows');
$payrollRuns = new JsonStore('payroll_runs');
$exports = new JsonStore('reporting_exports');

$nowIso = static fn (): string => gmdate('c');

$buildMetrics = static function (string $tenantId) use ($users, $workflows, $payrollRuns): array {
    $tenantUsers = $users->list(static fn (array $record): bool => (
        ($record['tenant_id'] ?? '') === $tenantId
        && in_array((string) ($record['status'] ?? ''), ['active', 'invited'], true)
    ));
    $headcount = max(count($tenantUsers), 1);

    $tenantWorkflows = $workflows->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);
    $pendingOnboarding = count(array_filter(
        $tenantWorkflows,
        static fn (array $workflow): bool => ($workflow['status'] ?? '') !== 'completed'
    ));

    $tenantRuns = $payrollRuns->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);
    $latestRun = $tenantRuns[0] ?? null;
    $monthlyPayroll = (float) ($latestRun['net_total'] ?? 0.0);

    $attritionRate = round(min(0.25, max(0.03, 0.05 + ($pendingOnboarding / max($headcount, 1)) * 0.02)), 4);
    $timeToHire = (int) max(10, 25 - min(10, floor($headcount / 20)));
    $openComplianceAlerts = (int) max(0, ceil($pendingOnboarding / 3));

    return [
        'tenant_id' => $tenantId,
        'headcount' => $headcount,
        'attrition_rate' => $attritionRate,
        'time_to_hire_days' => $timeToHire,
        'pending_onboarding' => $pendingOnboarding,
        'monthly_payroll' => $monthlyPayroll,
        'open_compliance_alerts' => $openComplianceAlerts,
        'kpis' => [
            ['label' => 'Active Employees', 'value' => number_format($headcount)],
            ['label' => 'Pending Onboarding', 'value' => number_format($pendingOnboarding)],
            ['label' => 'Monthly Payroll', 'value' => '$' . number_format($monthlyPayroll, 2)],
            ['label' => 'Open Compliance Alerts', 'value' => number_format($openComplianceAlerts)],
        ],
    ];
};

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'reporting',
    'status' => 'ok',
    'version' => '2.1.0',
]));

$router->get('/api/health', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'reporting',
    'status' => 'ok',
    'timestamp' => gmdate('c'),
]));

$router->get('/api/views/overview', static function (Request $request) use ($tokens, $buildMetrics): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    return JsonResponse::make([
        'service' => 'reporting',
        'summary' => $buildMetrics($tenantId),
    ]);
});

$router->get('/api/reports/workforce-kpis', static function (Request $request) use ($tokens, $buildMetrics): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    return JsonResponse::make($buildMetrics($tenantId));
});

$router->post('/api/reports/export', static function (Request $request) use ($tokens, $exports, $buildMetrics, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'super-admin', 'finance-admin', 'hr-admin', 'manager']);

    $format = strtolower(trim((string) $request->input('format', 'csv')));
    if (!in_array($format, ['csv', 'pdf', 'json'], true)) {
        throw new HttpException(422, 'format must be csv, pdf, or json.');
    }

    $timestamp = $nowIso();
    $metrics = $buildMetrics($tenantId);
    $exportId = IdGenerator::next('rpt');
    $record = [
        'export_id' => $exportId,
        'tenant_id' => $tenantId,
        'format' => $format,
        'status' => 'completed',
        'download_url' => '/api/reports/exports/' . rawurlencode($exportId) . '/download',
        'filters' => [
            'requested_at' => $timestamp,
            'include_kpis' => true,
        ],
        'report_payload' => $metrics,
        'expires_at' => gmdate('c', strtotime('+24 hours')),
        'created_by' => (string) ($claims['sub'] ?? ''),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $exports->set($exportId, $record);

    return JsonResponse::make($record, 201);
});

$router->get('/api/reports/exports', static function (Request $request) use ($tokens, $exports): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $records = $exports->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'exports' => $records,
        'count' => count($records),
    ]);
});

$router->get('/api/reports/exports/{exportId}/download', static function (Request $request, array $params) use ($tokens, $exports): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $exportId = (string) ($params['exportId'] ?? '');
    $record = $exports->find($exportId);
    if ($record === null || ($record['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'Export not found.');
    }

    return JsonResponse::make([
        'export_id' => $exportId,
        'format' => $record['format'] ?? 'json',
        'filename' => 'workforce-report-' . gmdate('Ymd') . '.' . ($record['format'] ?? 'json'),
        'content' => $record['report_payload'] ?? [],
    ]);
});

$router->dispatch($request);
