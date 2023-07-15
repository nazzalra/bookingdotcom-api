<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_fails_with_admin_role()
    {
        $response = $this->postJson('/api/auth/register',[
            'name' => 'mario',
            'email' => 'mario@test.com',
            'password' => 'MarioMario',
            'password_confirmation' => 'MarioMario',
            'role_id' => Role::ROLE_ADMINISTRATOR
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_succeeds_with_user_role()
    {
        $response = $this->postJson('/api/auth/register',[
            'name' => 'mario',
            'email' => 'mario@test.com',
            'password' => 'MarioMario',
            'password_confirmation' => 'MarioMario',
            'role_id' => Role::ROLE_USER
        ]);

        $response->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_registration_succeeds_with_owner_role()
    {
        $response = $this->postJson('/api/auth/register',[
            'name' => 'mario',
            'email' => 'mario@test.com',
            'password' => 'MarioMario',
            'password_confirmation' => 'MarioMario',
            'role_id' => Role::ROLE_OWNER
        ]);

        $response->assertStatus(200)->assertJsonStructure(['access_token']);
    }
}
