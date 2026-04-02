<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Validates: Requirements 1.2, 1.3, 1.5, 1.6, 1.8
 */
class AuthTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $role = 'kasir', string $password = 'password123'): User
    {
        return User::factory()->create([
            'role'      => $role,
            'password'  => bcrypt($password),
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.2 — valid credentials → token + role
    // -------------------------------------------------------------------------

    /**
     * Test: login dengan kredensial valid mengembalikan token dan role.
     */
    public function test_login_with_valid_credentials_returns_token_and_role(): void
    {
        $user = $this->createUser('kasir', 'secret1234');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret1234',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'role',
                'user' => ['id', 'name', 'email', 'role'],
            ],
        ]);
        $response->assertJsonPath('data.role', 'kasir');
        $this->assertNotEmpty($response->json('data.token'));
    }

    /**
     * Test: login dengan role head_manager juga mengembalikan token dan role yang benar.
     */
    public function test_login_returns_correct_role_for_head_manager(): void
    {
        $user = $this->createUser('head_manager', 'managerpass');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'managerpass',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'head_manager');
    }

    // -------------------------------------------------------------------------
    // Requirement 1.3 — invalid credentials → 401
    // -------------------------------------------------------------------------

    /**
     * Test: login dengan password salah mengembalikan 401.
     */
    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = $this->createUser('kasir', 'correctpassword');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }

    /**
     * Test: login dengan email yang tidak terdaftar mengembalikan 401.
     */
    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'notexist@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.5 — akses endpoint protected tanpa token → 401
    // -------------------------------------------------------------------------

    /**
     * Test: akses GET /api/v1/auth/me tanpa token mengembalikan 401.
     */
    public function test_access_protected_endpoint_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test: akses POST /api/v1/auth/logout tanpa token mengembalikan 401.
     */
    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    /**
     * Test: akses GET /api/v1/users tanpa token mengembalikan 401.
     */
    public function test_access_users_list_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.6 — akses endpoint dengan role yang salah → 403
    // -------------------------------------------------------------------------

    /**
     * Test: kasir mengakses endpoint GET /api/v1/users (head_manager only) → 403.
     */
    public function test_kasir_accessing_head_manager_endpoint_returns_403(): void
    {
        $kasir = $this->createUser('kasir');

        $response = $this->actingAs($kasir, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    /**
     * Test: finance mengakses endpoint deactivate user (head_manager only) → 403.
     */
    public function test_finance_accessing_deactivate_endpoint_returns_403(): void
    {
        $finance = $this->createUser('finance');
        $target  = $this->createUser('kasir');

        $response = $this->actingAs($finance, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}/deactivate");

        $response->assertStatus(403);
    }

    /**
     * Test: head_manager dapat mengakses GET /api/v1/users dengan sukses.
     */
    public function test_head_manager_can_access_users_list(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // -------------------------------------------------------------------------
    // Requirement 1.8 — deaktivasi user → semua token dicabut
    // -------------------------------------------------------------------------

    /**
     * Test: deaktivasi user menghapus semua token aktif milik user tersebut.
     */
    public function test_deactivate_user_revokes_all_tokens(): void
    {
        $manager = $this->createUser('head_manager');
        $target  = $this->createUser('kasir');

        // Buat beberapa token untuk target user
        $target->createToken('token-1');
        $target->createToken('token-2');
        $this->assertCount(2, $target->tokens()->get());

        // Head manager menonaktifkan target user
        $response = $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}/deactivate");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'User berhasil dinonaktifkan');

        // Semua token target harus sudah dihapus
        $this->assertCount(0, $target->tokens()->get());

        // User harus ditandai tidak aktif
        $this->assertFalse((bool) $target->fresh()->is_active);
    }

    /**
     * Test: setelah dinonaktifkan, semua token user dihapus sehingga tidak bisa login ulang.
     *
     * Sanctum menolak request jika token tidak ditemukan di DB.
     * Setelah deaktivasi, tokens() harus kosong — membuktikan token dicabut.
     */
    public function test_deactivated_user_has_no_tokens_and_is_marked_inactive(): void
    {
        $manager = $this->createUser('head_manager');
        $target  = $this->createUser('kasir');

        // Buat token aktif untuk target
        $target->createToken('session-token');
        $this->assertCount(1, $target->tokens()->get());

        // Head manager menonaktifkan user
        $response = $this->actingAs($manager, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}/deactivate");

        $response->assertStatus(200);

        // Semua token harus dihapus dari DB
        $this->assertCount(0, $target->tokens()->get());

        // User harus ditandai tidak aktif
        $this->assertFalse((bool) $target->fresh()->is_active);
    }
}
