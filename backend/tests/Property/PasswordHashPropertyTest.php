<?php

namespace Tests\Property;

use App\Models\User;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Property 3: Password Tersimpan Sebagai Bcrypt Hash
 *
 * For any user created by Head_Manager, password column is bcrypt hash, never plaintext.
 *
 * Validates: Requirements 1.7
 */
class PasswordHashPropertyTest extends TestCase
{
    use DatabaseTransactions;
    use TestTrait;

    /**
     * For any password string, the stored password must be a bcrypt hash (starts with $2y$).
     *
     * **Validates: Requirements 1.7**
     */
    public function testPasswordStoredAsBcryptHash(): void
    {
        $manager = User::factory()->create([
            'role'      => 'head_manager',
            'is_active' => true,
        ]);
        $token = $manager->createToken('test')->plainTextToken;

        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $plainPassword) use ($token) {
            // Ensure password meets minimum length requirement
            $password = strlen($plainPassword) >= 8
                ? $plainPassword
                : str_pad($plainPassword, 8, 'x');

            $email = 'user_' . uniqid() . '@example.com';

            $response = $this->withToken($token)->postJson('/api/v1/users', [
                'name'     => 'Test User',
                'email'    => $email,
                'password' => $password,
                'role'     => 'kasir',
            ]);

            $response->assertStatus(201);

            $userId = $response->json('data.id');
            $user   = User::find($userId);

            $this->assertNotNull($user, "Created user must exist in DB");

            // Retrieve raw password from DB (bypassing hidden cast)
            $rawPassword = \DB::table('users')->where('id', $userId)->value('password');

            // Assert bcrypt prefix
            $this->assertStringStartsWith(
                '$2y$',
                $rawPassword,
                "Stored password must be a bcrypt hash starting with '\$2y\$'"
            );

            // Assert stored password is NOT the plaintext
            $this->assertNotEquals(
                $password,
                $rawPassword,
                "Stored password must never equal the plaintext password"
            );

            // Assert bcrypt verify works
            $this->assertTrue(
                password_verify($password, $rawPassword),
                "Stored hash must verify against the original plaintext password"
            );

            // Cleanup
            $user->delete();
        });
    }

    /**
     * For any user created directly via factory (simulating Head_Manager creation),
     * the stored password is always a bcrypt hash.
     *
     * **Validates: Requirements 1.7**
     */
    public function testDirectUserCreationAlwaysHashesPassword(): void
    {
        $this->limitTo(5)->forAll(
            Generators::string()
        )->then(function (string $plainPassword) {
            $password = strlen($plainPassword) >= 8
                ? $plainPassword
                : str_pad($plainPassword, 8, 'a');

            $user = User::create([
                'name'      => 'Test',
                'email'     => 'hash_test_' . uniqid() . '@example.com',
                'password'  => bcrypt($password),
                'role'      => 'kasir',
                'is_active' => true,
            ]);

            $rawPassword = \DB::table('users')->where('id', $user->id)->value('password');

            $this->assertStringStartsWith(
                '$2y$',
                $rawPassword,
                "Password must be stored as bcrypt hash"
            );

            $this->assertNotEquals(
                $password,
                $rawPassword,
                "Plaintext password must never be stored"
            );

            $user->delete();
        });
    }
}




