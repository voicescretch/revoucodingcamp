<?php

namespace Tests\Property;

use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 1: Valid Login Returns Token dan Role
 *
 * For any valid user credentials (email + password), login should return token + role.
 *
 * Validates: Requirements 1.2
 */
class AuthPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    /**
     * For each valid role, login with valid credentials returns token and correct role.
     *
     * **Validates: Requirements 1.2**
     */
    public function testValidLoginReturnsTokenAndRole(): void
    {
        $roles = ['pelanggan', 'kasir', 'finance', 'head_manager'];

        $this->limitTo(5)->forAll(
            Generators::elements($roles)
        )->then(function (string $role) {
            $password = 'password123';

            $user = User::factory()->create([
                'role'      => $role,
                'password'  => bcrypt($password),
                'is_active' => true,
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email'    => $user->email,
                'password' => $password,
            ]);

            $response->assertStatus(200);

            $data = $response->json('data');

            $this->assertArrayHasKey('token', $data, "Login response must contain 'token'");
            $this->assertArrayHasKey('role', $data, "Login response must contain 'role'");
            $this->assertNotEmpty($data['token'], "Token must not be empty");
            $this->assertEquals($role, $data['role'], "Returned role must match user's role");

            // Cleanup for next iteration
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * For any valid user, login response contains user data with correct structure.
     *
     * **Validates: Requirements 1.2**
     */
    public function testValidLoginResponseContainsUserData(): void
    {
        $roles = ['pelanggan', 'kasir', 'finance', 'head_manager'];

        $this->limitTo(5)->forAll(
            Generators::elements($roles)
        )->then(function (string $role) {
            $password = 'secret_pass_99';

            $user = User::factory()->create([
                'role'      => $role,
                'password'  => bcrypt($password),
                'is_active' => true,
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'email'    => $user->email,
                'password' => $password,
            ]);

            $response->assertStatus(200);

            $data = $response->json('data');

            $this->assertArrayHasKey('user', $data, "Login response must contain 'user'");
            $this->assertEquals($user->email, $data['user']['email']);
            $this->assertEquals($role, $data['user']['role']);

            // Cleanup
            $user->tokens()->delete();
            $user->delete();
        });
    }
}




