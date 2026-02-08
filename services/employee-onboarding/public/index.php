<?php
header('Content-Type: application/json');
echo json_encode([
    'service' => 'employee-onboarding',
    'status' => 'ok',
    'message' => 'Laravel 12 service entrypoint scaffolded.'
]);
