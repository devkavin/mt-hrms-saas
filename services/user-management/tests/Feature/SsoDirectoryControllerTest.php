<?php

namespace Tests\Feature;

use Tests\TestCase;

class SsoDirectoryControllerTest extends TestCase
{
    public function test_sso_directory_sync_is_queued(): void
    {
        $response = $this->postJson('/api/sso/directory-sync', ['provider' => 'azure-ad']);

        $response->assertStatus(202)->assertJsonPath('status', 'queued');
    }
}
