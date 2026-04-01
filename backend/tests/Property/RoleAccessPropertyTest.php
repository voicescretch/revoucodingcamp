<?php

namespace Tests\Property;

use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 2: Role-Based Access Control
 *
 * For any protected endpoint, access is granted iff role is in allowed list.
 *
 * Validates: Requirements 1.6
 */
class RoleAccessPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    /**
     * All roles that are NOT head_manager should get 403 on head_manager-only endpoints.
     *
     * **Validates: Requirements 1.6**
     */
    public function testNonHeadManagerRolesAreDeniedFromManagerEndpoints(): void
    {
        $forbiddenRoles = ['pelanggan', 'kasir', 'finance'];

        $this->limitTo(5)->forAll(
            Generators::elements($forbiddenRoles)
        )->then(function (string $role) {
            $user = User::factory()->create([
                'role'      => $role,
                'is_active' => true,
            ]);

            $token = $user->createToken('test')->plainTextToken;

            // GET /api/v1/users is head_manager only
            $response = $this->withToken($token)->getJson('/api/v1/users');

            $response->assertStatus(403);

            // Cleanup
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * head_manager role should be granted access to manager-only endpoints.
     *
     * **Validates: Requirements 1.6**
     */
    public function testHeadManagerRoleIsGrantedAccessToManagerEndpoints(): void
    {
        $this->limitTo(5)->forAll(
            Generators::elements(['head_manager'])
        )->then(function (string $role) {
            $user = User::factory()->create([
                'role'      => $role,
                'is_active' => true,
            ]);

            $token = $user->createToken('test')->plainTextToken;

            // GET /api/v1/users is head_manager only
            $response = $this->withToken($token)->getJson('/api/v1/users');

            $response->assertStatus(200);

            // Cleanup
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * Any request without a token to a protected endpoint returns 401.
     *
     * **Validates: Requirements 1.6**
     */
    public function testUnauthenticatedRequestToProtectedEndpointReturns401(): void
    {
        $protectedEndpoints = [
            ['method' => 'GET',  'path' => '/api/v1/users'],
            ['method' => 'GET',  'path' => '/api/v1/auth/me'],
        ];

        $this->limitTo(5)->forAll(
            Generators::elements($protectedEndpoints)
        )->then(function (array $endpoint) {
            if ($endpoint['method'] === 'GET') {
                $response = $this->getJson($endpoint['path']);
            } else {
                $response = $this->postJson($endpoint['path'], []);
            }

            $response->assertStatus(401);
        });
    }

    /**
     * Non-head_manager roles must always be denied from manager-only endpoints.
     *
     * **Validates: Requirements 1.6**
     */
    public function testRoleAccessIsExclusiveToAllowedRoles(): void
    {
        $forbiddenRoles = ['pelanggan', 'kasir', 'finance'];

        $this->limitTo(5)->forAll(
            Generators::elements($forbiddenRoles)
        )->then(function (string $role) {
            $user = User::factory()->create([
                'role'      => $role,
                'is_active' => true,
            ]);

            $token = $user->createToken('test_exclusive_' . uniqid())->plainTextToken;

            // POST /api/v1/users is head_manager only — non-managers must get 403
            $response = $this->withToken($token)->postJson('/api/v1/users', [
                'name'     => 'Test User',
                'email'    => 'test_' . uniqid() . '@example.com',
                'password' => 'password123',
                'role'     => 'kasir',
            ]);

            $response->assertStatus(403);

            // Cleanup
            $user->tokens()->delete();
            $user->delete();
        });
    }
}




