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

$runs = new JsonStore('payroll_runs');
$templates = new JsonStore('payroll_templates');
$users = new JsonStore('user_management_users');

$nowIso = static fn (): string => gmdate('c');

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'payroll',
    'status' => 'ok',
    'version' => '2.1.0',
]));

$router->get('/api/health', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'payroll',
    'status' => 'ok',
    'timestamp' => gmdate('c'),
]));

$router->get('/api/views/overview', static function (Request $request) use ($tokens, $runs): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $tenantRuns = $runs->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'service' => 'payroll',
        'tenant_id' => $tenantId,
        'run_count' => count($tenantRuns),
        'latest_run' => $tenantRuns[0] ?? null,
    ]);
});

$router->get('/api/payroll/runs', static function (Request $request) use ($tokens, $runs): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $records = $runs->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'runs' => $records,
        'count' => count($records),
    ]);
});

$router->post('/api/payroll/templates', static function (Request $request) use ($tokens, $templates, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'super-admin', 'finance-admin']);

    $templateName = trim((string) $request->input('name', 'default'));
    $taxRate = (float) $request->input('tax_rate', 0.16);
    $benefitsRate = (float) $request->input('benefits_rate', 0.07);

    if ($taxRate < 0 || $taxRate > 0.8 || $benefitsRate < 0 || $benefitsRate > 0.5) {
        throw new HttpException(422, 'tax_rate or benefits_rate out of supported range.');
    }

    $templateId = IdGenerator::next('paytpl');
    $timestamp = $nowIso();
    $template = [
        'template_id' => $templateId,
        'tenant_id' => $tenantId,
        'name' => $templateName,
        'tax_rate' => $taxRate,
        'benefits_rate' => $benefitsRate,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $templates->set($templateId, $template);

    return JsonResponse::make($template, 201);
});

$router->get('/api/payroll/templates', static function (Request $request) use ($tokens, $templates): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $records = $templates->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make(['tenant_id' => $tenantId, 'templates' => $records]);
});

$router->post('/api/payroll/runs', static function (Request $request) use ($tokens, $runs, $templates, $users, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'super-admin', 'finance-admin', 'hr-admin']);

    $period = trim((string) $request->input('period', ''));
    if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
        throw new HttpException(422, 'period must be in YYYY-MM format.');
    }

    $existingForPeriod = $runs->list(static fn (array $record): bool => (
        ($record['tenant_id'] ?? '') === $tenantId
        && ($record['period'] ?? '') === $period
        && in_array((string) ($record['status'] ?? ''), ['processing', 'completed', 'approved'], true)
    ));
    if (count($existingForPeriod) > 0) {
        throw new HttpException(409, 'Payroll already exists for this period.');
    }

    $headcount = count($users->list(static fn (array $record): bool => (
        ($record['tenant_id'] ?? '') === $tenantId
        && in_array((string) ($record['status'] ?? ''), ['active', 'invited'], true)
    )));
    if ($headcount === 0) {
        $headcount = 25;
    }

    $averageSalary = (float) $request->input('average_salary', 5800);
    if ($averageSalary <= 0) {
        throw new HttpException(422, 'average_salary must be greater than zero.');
    }

    $defaultTaxRate = 0.16;
    $defaultBenefitsRate = 0.07;
    $templateId = trim((string) $request->input('template_id', ''));
    if ($templateId !== '') {
        $template = $templates->find($templateId);
        if ($template === null || ($template['tenant_id'] ?? '') !== $tenantId) {
            throw new HttpException(404, 'Payroll template not found.');
        }
        $defaultTaxRate = (float) ($template['tax_rate'] ?? $defaultTaxRate);
        $defaultBenefitsRate = (float) ($template['benefits_rate'] ?? $defaultBenefitsRate);
    }

    $taxRate = (float) $request->input('tax_rate', $defaultTaxRate);
    $benefitsRate = (float) $request->input('benefits_rate', $defaultBenefitsRate);

    $grossTotal = round($headcount * $averageSalary, 2);
    $taxTotal = round($grossTotal * $taxRate, 2);
    $benefitsTotal = round($grossTotal * $benefitsRate, 2);
    $netTotal = round($grossTotal - $taxTotal - $benefitsTotal, 2);

    $timestamp = $nowIso();
    $runId = IdGenerator::next('pay');
    $run = [
        'run_id' => $runId,
        'tenant_id' => $tenantId,
        'period' => $period,
        'status' => 'processing',
        'headcount' => $headcount,
        'gross_total' => $grossTotal,
        'tax_total' => $taxTotal,
        'benefits_total' => $benefitsTotal,
        'net_total' => $netTotal,
        'tax_rate' => $taxRate,
        'benefits_rate' => $benefitsRate,
        'template_id' => $templateId,
        'notes' => trim((string) $request->input('notes', '')),
        'created_by' => (string) ($claims['sub'] ?? ''),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $runs->set($runId, $run);

    return JsonResponse::make($run, 202);
});

$router->patch('/api/payroll/runs/{runId}', static function (Request $request, array $params) use ($tokens, $runs, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'super-admin', 'finance-admin']);

    $runId = (string) ($params['runId'] ?? '');
    $run = $runs->find($runId);
    if ($run === null || ($run['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'Payroll run not found.');
    }

    $nextStatus = trim((string) $request->input('status', (string) ($run['status'] ?? 'processing')));
    $allowedStatuses = ['processing', 'completed', 'approved', 'cancelled'];
    if (!in_array($nextStatus, $allowedStatuses, true)) {
        throw new HttpException(422, 'status must be one of: ' . implode(', ', $allowedStatuses));
    }

    $run['status'] = $nextStatus;
    $run['notes'] = trim((string) $request->input('notes', (string) ($run['notes'] ?? '')));
    if ($nextStatus === 'completed' || $nextStatus === 'approved') {
        $run['completed_at'] = $nowIso();
    }
    $run['updated_at'] = $nowIso();
    $runs->set($runId, $run);

    return JsonResponse::make($run);
});

$router->get('/api/payroll/runs/{runId}', static function (Request $request, array $params) use ($tokens, $runs, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $runId = (string) ($params['runId'] ?? '');
    $run = $runs->find($runId);
    if ($run === null || ($run['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'Payroll run not found.');
    }

    if (($run['status'] ?? '') === 'processing') {
        $run['status'] = 'completed';
        $run['completed_at'] = $nowIso();
        $run['updated_at'] = $nowIso();
        $runs->set($runId, $run);
    }

    return JsonResponse::make($run);
});

$router->dispatch($request);
