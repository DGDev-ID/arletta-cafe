<?php

namespace Tests\Feature;

use App\Models\Balance;
use App\Models\BalanceLog;
use App\Models\BalanceTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TopupBalanceWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.qrisly.topup_qris_id' => '231']);
        config(['services.thirdparty.secret_key' => Hash::make('my-secret-key')]);
        config(['services.thirdparty.allowed_domain' => 'core.cafellio.my.id']);
    }

    public function test_ignores_request_when_secret_key_is_missing_or_invalid(): void
    {
        $response = $this->postJson('/api/webhook/topup-balance', [
            'event' => 'payment.success',
            'data'  => [
                'qris_history_id' => 'hist-123',
                'qris_id'         => '231',
                'status'          => 'paid',
            ],
        ], [
            'x-thirdparty-key' => 'wrong-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ignored',
        ]);
    }

    public function test_ignores_request_when_qris_id_mismatches(): void
    {
        $response = $this->postJson('/api/webhook/topup-balance', [
            'event' => 'payment.success',
            'data'  => [
                'qris_history_id' => 'hist-123',
                'qris_id'         => '999', // mismatch
                'status'          => 'paid',
            ],
        ], [
            'x-thirdparty-key' => 'my-secret-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'ignored',
            'message' => 'QRIS ID mismatch.',
        ]);
    }

    public function test_ignores_request_when_topup_record_not_found(): void
    {
        $response = $this->postJson('/api/webhook/topup-balance', [
            'event' => 'payment.success',
            'data'  => [
                'qris_history_id' => 'non-existent-hist',
                'qris_id'         => '231',
                'status'          => 'paid',
            ],
        ], [
            'x-thirdparty-key' => 'my-secret-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'ignored',
            'message' => 'Topup record not found.',
        ]);
    }

    public function test_processes_successful_payment_and_updates_balance(): void
    {
        $balance = Balance::create(['balance' => 50000]);
        $topup = BalanceTopup::create([
            'balance_id'      => $balance->id,
            'topup_amount'    => 100000,
            'unique_amount'   => 1,
            'total_amount'    => 100001,
            'qris_history_id' => '8c5b8e8d-7b22-3e31-7a0e-0d5a2d1d6c09',
            'status'          => 'pending',
        ]);

        $payload = [
            'event'     => 'payment.success',
            'timestamp' => '2025-01-14T10:35:22Z',
            'data'      => [
                'qris_history_id'  => '8c5b8e8d-7b22-3e31-7a0e-0d5a2d1d6c09',
                'qris_id'          => '231',
                'amount'           => 100001,
                'original_amount'  => 100000,
                'status'           => 'paid',
                'paid_at'          => '2025-01-14T10:35:22Z',
                'payment_method'   => 'Bank Transfer',
                'payment_provider' => 'Bank BCA',
                'created_at'       => '2025-01-14T10:30:00Z',
            ],
        ];

        $response = $this->postJson('/api/webhook/topup-balance', $payload, [
            'x-thirdparty-key' => 'my-secret-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => 'success',
            'message' => 'Topup processed and balance updated.',
        ]);

        $this->assertDatabaseHas('balance_topups', [
            'id'     => $topup->id,
            'status' => 'success',
        ]);

        $this->assertEquals(150000, $balance->fresh()->balance);

        $this->assertDatabaseHas('balance_logs', [
            'balance_id'     => $balance->id,
            'type'           => 'in',
            'amount'         => 100000,
            'transaction_id' => $topup->id,
        ]);

        // Test idempotency: re-sending the same webhook should not add balance again
        $reResponse = $this->postJson('/api/webhook/topup-balance', $payload, [
            'x-thirdparty-key' => 'my-secret-key',
        ]);

        $reResponse->assertStatus(200);
        $reResponse->assertJson([
            'status'  => 'success',
            'message' => 'Topup already processed.',
        ]);

        $this->assertEquals(150000, $balance->fresh()->balance);
        $this->assertEquals(1, BalanceLog::where('transaction_id', $topup->id)->count());
    }

    public function test_handles_expired_payment_without_adding_balance(): void
    {
        $balance = Balance::create(['balance' => 50000]);
        $topup = BalanceTopup::create([
            'balance_id'      => $balance->id,
            'topup_amount'    => 100000,
            'unique_amount'   => 1,
            'total_amount'    => 100001,
            'qris_history_id' => '8c5b8e8d-7b22-3e31-7a0e-0d5a2d1d6c09',
            'status'          => 'pending',
        ]);

        $payload = [
            'event'     => 'payment.expired',
            'timestamp' => '2025-01-15T23:59:59Z',
            'data'      => [
                'qris_history_id' => '8c5b8e8d-7b22-3e31-7a0e-0d5a2d1d6c09',
                'qris_id'         => '231',
                'amount'          => 100001,
                'status'          => 'expired',
                'created_at'      => '2025-01-14T10:30:00Z',
                'expired_at'      => '2025-01-15T23:59:59Z',
            ],
        ];

        $response = $this->postJson('/api/webhook/topup-balance', $payload, [
            'x-thirdparty-key' => 'my-secret-key',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('balance_topups', [
            'id'     => $topup->id,
            'status' => 'failed',
        ]);

        $this->assertEquals(50000, $balance->fresh()->balance);
        $this->assertEquals(0, BalanceLog::count());
    }
}

