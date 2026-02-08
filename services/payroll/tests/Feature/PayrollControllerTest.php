<?php

namespace Tests\Feature;

use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    public function test_payroll_run_is_accepted(): void
    {
        $response = $this->postJson('/api/payroll/runs', ['period' => '2026-01']);

        $response->assertStatus(202)->assertJsonPath('status', 'processing');
    }
}
