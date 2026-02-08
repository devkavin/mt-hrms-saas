<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    public function test_workflow_creation_returns_identifier(): void
    {
        $response = $this->postJson('/api/onboarding/workflows', ['employee_id' => 'emp_001']);

        $response->assertCreated()->assertJsonStructure(['workflow_id', 'employee_id', 'status']);
    }
}
