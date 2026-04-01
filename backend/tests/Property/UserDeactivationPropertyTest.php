<?php

namespace Tests\Property;

use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 4: Deaktivasi User Mencabut Semua Token
 *
 * For any user with active tokens, deactivation invalidates all tokens.
 *
 * Validates: Requirements 1.8
 */
class UserDeactivationPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    private User $manager;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role'      => 'head_manager',
            'is_active' => true,
        ]);
        $this->managerToken = $this->manager->createToken('manager_setup')->plainTextToken;
    }

    /**
     * For any number of active tokens (1-5), deactivating a user deletes all tokens.
     *
     * **Validates: Requirements 1.8**
     */
    public function testDeactivationRevokesAllTokens(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 3)
        )->then(function (int $tokenCount) {
            $targetUser = User::factory()->create(['role' => 'kasir', 'is_active' => true]);

            $tokens = [];
            for ($i = 0; $i < $tokenCount; $i++) {
                $tokens[] = $targetUser->createToken("tok_{$i}_" . uniqid())->plainTextToken;
            }

            $this->assertEquals($tokenCount, $targetUser->tokens()->count());

            $this->withToken($this->managerToken)
                ->putJson("/api/v1/users/{$targetUser->id}/deactivate")
                ->assertStatus(200);

            $this->assertEquals(0, $targetUser->tokens()->count(),
                "All tokens must be revoked after deactivation");

            $targetUser->refresh();
            $this->assertFalse($targetUser->is_active);

            // Cleanup for next iteration
            $targetUser->forceDelete();
        });
    }

    /**
     * After deactivation, previously valid tokens must be rejected by protected endpoints.
     *
     * **Validates: Requirements 1.8**
     */
    public function testDeactivatedUserTokensAreRejected(): void
    {
        $this->limitTo(5)->forAll(
            Generators::choose(1, 3)
        )->then(function (int $tokenCount) {
            // Re-fetch manager to ensure fresh state within this iteration
            $manager = User::find($this->manager->id);
            $this->assertNotNull($manager, 'Manager must exist');
            $this->assertEquals('head_manager', $manager->role, 'Manager must have head_manager role');

            $targetUser = User::factory()->create(['role' => 'kasir', 'is_active' => true]);

            $plainTokens = [];
            for ($i = 0; $i < $tokenCount; $i++) {
                $plainTokens[] = $targetUser->createToken("sess_{$i}_" . uniqid())->plainTextToken;
            }

            // Tokens work before deactivation
            $this->withToken($plainTokens[0])->getJson('/api/v1/auth/me')->assertStatus(200);
            $this->app->make('auth')->forgetGuards();

            // Deactivate
            $deactivateResponse = $this->withToken($this->managerToken)
                ->putJson("/api/v1/users/{$targetUser->id}/deactivate");

            $deactivateResponse->assertStatus(200);

            // All tokens must now be invalid
            // Reset auth guard cache between requests
            foreach ($plainTokens as $plainToken) {
                $this->app->make('auth')->forgetGuards();
                $this->withToken($plainToken)->getJson('/api/v1/auth/me')->assertStatus(401);
            }

            // Cleanup for next iteration
            $targetUser->forceDelete();
        });
    }
}
