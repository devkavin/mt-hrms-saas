<?php
header('Content-Type: application/json');
echo json_encode([
    'service' => 'user-management',
    'status' => 'ok',
    'message' => 'Laravel 12 service entrypoint scaffolded.'
]);
