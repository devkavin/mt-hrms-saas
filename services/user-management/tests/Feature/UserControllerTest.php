<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_invite_endpoint_creates_invitation(): void
    {
        $response = $this->postJson('/api/users/invite', ['email' => 'new.user@client.com']);

        $response->assertCreated()->assertJsonPath('status', 'sent');
    }
}
