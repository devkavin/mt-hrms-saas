<?php
header('Content-Type: application/json');
echo json_encode([
    'service' => 'payroll',
    'status' => 'ok',
    'message' => 'Laravel 12 service entrypoint scaffolded.'
]);
