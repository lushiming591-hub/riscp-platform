<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Core\Payments\Events\PaymentCompletedEvent;
use App\Core\Payments\PaymentTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentCallbackIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_callback_marks_transaction_paid_and_dispatches_completion_once(): void
    {
        Event::fake();
        [$providerId, $transactionId] = $this->createPaymentFixture();

        $changed = app(PaymentTransactionService::class)->markPaid(
            $transactionId,
            'ALI-TRADE-001',
            ['status' => 'success'],
            'evt-001',
        );

        $this->assertTrue($changed);
        $this->assertSame('paid', DB::table('payment_transactions')->where('id', $transactionId)->value('status'));
        $orderId = DB::table('payment_transactions')->where('id', $transactionId)->value('order_id');
        $this->assertSame('paid', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertSame(1, DB::table('payment_callbacks')->where('provider_id', $providerId)->where('event_id', 'evt-001')->count());
        Event::assertDispatched(PaymentCompletedEvent::class, 1);
    }

    public function test_duplicate_event_is_idempotent_and_does_not_dispatch_completion_twice(): void
    {
        Event::fake();
        [$providerId, $transactionId] = $this->createPaymentFixture();
        $service = app(PaymentTransactionService::class);

        $this->assertTrue($service->markPaid($transactionId, 'ALI-TRADE-002', ['status' => 'success'], 'evt-002'));
        $this->assertFalse($service->markPaid($transactionId, 'ALI-TRADE-002', ['status' => 'success'], 'evt-002'));

        $this->assertSame(1, DB::table('payment_callbacks')->where('provider_id', $providerId)->where('event_id', 'evt-002')->count());
        $this->assertSame(1, DB::table('payment_callbacks')->where('payment_transaction_id', $transactionId)->count());
        Event::assertDispatched(PaymentCompletedEvent::class, 1);
    }

    public function test_new_callback_after_paid_is_audited_but_cannot_complete_transaction_again(): void
    {
        Event::fake();
        [$providerId, $transactionId] = $this->createPaymentFixture();
        $service = app(PaymentTransactionService::class);

        $this->assertTrue($service->markPaid($transactionId, 'ALI-TRADE-003', ['status' => 'success'], 'evt-003'));
        $this->assertFalse($service->markPaid($transactionId, 'ALI-TRADE-003', ['status' => 'success'], 'evt-004'));

        $this->assertSame(2, DB::table('payment_callbacks')->where('provider_id', $providerId)->where('payment_transaction_id', $transactionId)->count());
        $this->assertSame('paid', DB::table('payment_transactions')->where('id', $transactionId)->value('status'));
        Event::assertDispatched(PaymentCompletedEvent::class, 1);
    }

    public function test_paid_transaction_cannot_be_moved_back_to_processing_by_callback(): void
    {
        Event::fake();
        [$providerId, $transactionId] = $this->createPaymentFixture('paid');
        $service = app(PaymentTransactionService::class);

        $this->assertFalse($service->markPaid($transactionId, 'ALI-TRADE-004', ['status' => 'processing'], 'evt-005'));

        $this->assertSame('paid', DB::table('payment_transactions')->where('id', $transactionId)->value('status'));
        $this->assertSame(1, DB::table('payment_callbacks')->where('provider_id', $providerId)->where('event_id', 'evt-005')->count());
        Event::assertNotDispatched(PaymentCompletedEvent::class);
    }

    public function test_internal_completion_event_gets_a_deterministic_callback_id_when_event_id_is_absent(): void
    {
        Event::fake();
        [$providerId, $transactionId] = $this->createPaymentFixture();

        $this->assertTrue(app(PaymentTransactionService::class)->markPaid(
            $transactionId,
            'ALI-TRADE-006',
            ['status' => 'success'],
            null,
        ));

        $this->assertSame(1, DB::table('payment_callbacks')->where('provider_id', $providerId)->where('payment_transaction_id', $transactionId)->count());
        $callback = DB::table('payment_callbacks')->where('payment_transaction_id', $transactionId)->first();
        $this->assertNotNull($callback);
        $this->assertNotSame('', (string) $callback->event_id);
        $this->assertSame('internal', $callback->signature_status);
        Event::assertDispatched(PaymentCompletedEvent::class, 1);
    }

    public function test_callback_schema_requires_provider_id_and_event_id(): void
    {
        $columns = collect(DB::select('PRAGMA table_info(payment_callbacks)'))
            ->keyBy('name');

        $this->assertArrayHasKey('provider_id', $columns->all());
        $this->assertArrayHasKey('event_id', $columns->all());
        $this->assertSame(1, (int) $columns['provider_id']->notnull);
        $this->assertSame(1, (int) $columns['event_id']->notnull);
    }

    public function test_empty_callback_event_id_is_rejected(): void
    {
        $service = app(PaymentTransactionService::class);
        [, $transactionId] = $this->createPaymentFixture();

        $this->expectException(\InvalidArgumentException::class);
        $service->markPaid($transactionId, 'ALI-TRADE-005', [], '');
    }

    /** @return array{0:string,1:string} */
    private function createPaymentFixture(string $status = 'created'): array
    {
        $tenantId = (string) Str::uuid();
        $storeId = (string) Str::uuid();
        $providerId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $channelId = (string) Str::uuid();
        $orderId = (string) Str::uuid();
        $transactionId = (string) Str::uuid();

        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Test Tenant', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stores')->insert(['id' => $storeId, 'tenant_id' => $tenantId, 'name' => 'Test Store', 'code' => 'TEST', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_providers')->insert(['id' => $providerId, 'code' => 'allinpay', 'name' => 'Allinpay', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_accounts')->insert(['id' => $accountId, 'provider_id' => $providerId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'merchant_no' => 'TEST-MERCHANT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_channels')->insert(['id' => $channelId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_code' => 'allinpay_jsapi', 'channel_name' => 'Allinpay JSAPI', 'payment_method' => 'wechat', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('orders')->insert(['id' => $orderId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_no' => 'TEST-'.Str::upper(Str::random(8)), 'status' => $status === 'paid' ? 'paid' : 'pending', 'subtotal' => 100, 'discount_amount' => 0, 'total_amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_transactions')->insert(['id' => $transactionId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_id' => $orderId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_id' => $channelId, 'merchant_trade_no' => 'RISC-'.Str::upper(Str::random(12)), 'provider_trade_no' => $status === 'paid' ? 'PREVIOUS-TRADE' : null, 'amount' => 100, 'status' => $status, 'paid_at' => $status === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);

        return [$providerId, $transactionId];
    }
}
