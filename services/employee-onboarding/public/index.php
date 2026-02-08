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
$workflows = new JsonStore('onboarding_workflows');

$nowIso = static fn (): string => gmdate('c');

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'employee-onboarding',
    'status' => 'ok',
    'version' => '2.1.0',
]));

$router->get('/api/health', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'employee-onboarding',
    'status' => 'ok',
    'timestamp' => gmdate('c'),
]));

$router->get('/api/views/overview', static function (Request $request) use ($tokens, $workflows): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $records = $workflows->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'service' => 'employee-onboarding',
        'tenant_id' => $tenantId,
        'workflow_count' => count($records),
        'pending' => count(array_filter($records, static fn (array $r): bool => ($r['status'] ?? '') !== 'completed')),
    ]);
});

$router->get('/api/onboarding/workflows', static function (Request $request) use ($tokens, $workflows): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $records = $workflows->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'workflows' => $records,
        'count' => count($records),
    ]);
});

$router->post('/api/onboarding/workflows', static function (Request $request) use ($tokens, $workflows, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);

    $employeeId = trim((string) $request->input('employee_id', ''));
    if ($employeeId === '') {
        throw new HttpException(422, 'employee_id is required.');
    }

    $stepsInput = $request->input('steps', []);
    $steps = [];
    if (is_array($stepsInput) && count($stepsInput) > 0) {
        foreach ($stepsInput as $stepName) {
            $cleanStep = strtolower(trim((string) $stepName));
            if ($cleanStep !== '') {
                $steps[] = ['step' => $cleanStep, 'status' => 'pending'];
            }
        }
    }

    if (count($steps) === 0) {
        $steps = [
            ['step' => 'collect_documents', 'status' => 'pending'],
            ['step' => 'sign_contract', 'status' => 'pending'],
            ['step' => 'it_setup', 'status' => 'pending'],
            ['step' => 'orientation', 'status' => 'pending'],
        ];
    }

    $timestamp = $nowIso();
    $workflowId = IdGenerator::next('onb');

    $workflow = [
        'workflow_id' => $workflowId,
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'status' => 'pending_documents',
        'priority' => strtolower(trim((string) $request->input('priority', 'normal'))),
        'start_date' => (string) $request->input('start_date', gmdate('Y-m-d')),
        'manager_email' => strtolower(trim((string) $request->input('manager_email', ''))),
        'steps' => $steps,
        'created_by' => (string) ($claims['sub'] ?? ''),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];

    $workflows->set($workflowId, $workflow);

    return JsonResponse::make($workflow, 201);
});

$router->patch('/api/onboarding/workflows/{workflow}', static function (Request $request, array $params) use ($tokens, $workflows, $nowIso): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'super-admin', 'hr-admin', 'manager']);

    $workflowId = (string) ($params['workflow'] ?? '');
    $workflow = $workflows->find($workflowId);
    if ($workflow === null || ($workflow['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'Workflow not found.');
    }

    $workflow['status'] = trim((string) $request->input('status', (string) ($workflow['status'] ?? 'in_progress')));
    $workflow['priority'] = trim((string) $request->input('priority', (string) ($workflow['priority'] ?? 'normal')));
    $workflow['manager_email'] = strtolower(trim((string) $request->input('manager_email', (string) ($workflow['manager_email'] ?? ''))));
    $workflow['updated_at'] = $nowIso();
    $workflows->set($workflowId, $workflow);

    return JsonResponse::make($workflow);
});

$router->post('/api/onboarding/workflows/{workflow}/steps/{step}/complete', static function (Request $request, array $params) use (
    $tokens,
    $workflows,
    $nowIso
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);

    $workflowId = (string) ($params['workflow'] ?? '');
    $stepName = strtolower(trim((string) ($params['step'] ?? '')));

    $workflow = $workflows->find($workflowId);
    if ($workflow === null || ($workflow['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'Workflow not found.');
    }

    $updatedSteps = [];
    $stepMatched = false;
    foreach ((array) ($workflow['steps'] ?? []) as $step) {
        if (!is_array($step)) {
            continue;
        }

        if (strtolower((string) ($step['step'] ?? '')) === $stepName) {
            $step['status'] = 'completed';
            $step['completed_by'] = (string) ($claims['sub'] ?? '');
            $step['completed_at'] = $nowIso();
            $stepMatched = true;
        }

        $updatedSteps[] = $step;
    }

    if (!$stepMatched) {
        throw new HttpException(404, 'Workflow step not found.');
    }

    $allCompleted = true;
    foreach ($updatedSteps as $step) {
        if (($step['status'] ?? 'pending') !== 'completed') {
            $allCompleted = false;
            break;
        }
    }

    $workflow['steps'] = $updatedSteps;
    $workflow['status'] = $allCompleted ? 'completed' : 'in_progress';
    $workflow['updated_at'] = $nowIso();
    $workflows->set($workflowId, $workflow);

    return JsonResponse::make([
        'workflow_id' => $workflowId,
        'tenant_id' => $tenantId,
        'step' => $stepName,
        'status' => (string) $workflow['status'],
    ]);
});

$router->dispatch($request);
