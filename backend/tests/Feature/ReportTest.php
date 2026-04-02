<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Validates: Requirements 8.4, 8.7
 */
class ReportTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $role = 'head_manager'): User
    {
        return User::factory()->create([
            'role'      => $role,
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Requirement 8.4 — generate laporan stok PDF → return file PDF
    // -------------------------------------------------------------------------

    /**
     * Test: generate laporan stok PDF → response Content-Type: application/pdf.
     *
     * Validates: Requirement 8.4
     */
    public function test_generate_stock_report_pdf_returns_pdf_content_type(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->get('/api/v1/reports/stock?format=pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test: generate laporan stok PDF oleh finance role → berhasil.
     *
     * Validates: Requirement 8.4
     */
    public function test_finance_can_generate_stock_report_pdf(): void
    {
        $finance = $this->createUser('finance');

        $response = $this->actingAs($finance, 'sanctum')
            ->get('/api/v1/reports/stock?format=pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test: generate laporan stok tanpa auth → 401.
     *
     * Validates: Requirement 8.4
     */
    public function test_generate_stock_report_without_auth_returns_401(): void
    {
        $this->getJson('/api/v1/reports/stock?format=pdf')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Requirement 8.7 — start_date > end_date → return 400
    // -------------------------------------------------------------------------

    /**
     * Test: generate laporan stok dengan start_date > end_date → return 400.
     *
     * Validates: Requirement 8.7
     */
    public function test_generate_stock_report_with_invalid_date_range_returns_400(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/stock?format=pdf&start_date=2024-01-31&end_date=2024-01-01');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: generate laporan stock-movement dengan start_date > end_date → return 400.
     *
     * Validates: Requirement 8.7
     */
    public function test_generate_stock_movement_report_with_invalid_date_range_returns_400(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/stock-movement?format=pdf&start_date=2024-03-31&end_date=2024-03-01');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: generate laporan profit-loss dengan start_date > end_date → return 400.
     *
     * Validates: Requirement 8.7
     */
    public function test_generate_profit_loss_report_with_invalid_date_range_returns_400(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/profit-loss?format=pdf&start_date=2024-12-31&end_date=2024-01-01');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: generate laporan tanpa parameter format → return 400.
     *
     * Validates: Requirement 8.4
     */
    public function test_generate_report_without_format_returns_400(): void
    {
        $manager = $this->createUser('head_manager');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/reports/stock');

        $response->assertStatus(400);
        $response->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: kasir tidak bisa akses report endpoint → 403.
     *
     * Validates: Requirement 8.4
     */
    public function test_kasir_cannot_access_report_endpoint(): void
    {
        $kasir = $this->createUser('kasir');

        $this->actingAs($kasir, 'sanctum')
            ->getJson('/api/v1/reports/stock?format=pdf')
            ->assertStatus(403);
    }
}
