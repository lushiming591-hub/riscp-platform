<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Core\Payments\Contracts\PaymentProviderContract;
use App\Core\Payments\PaymentRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentRefundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_intent_is_idempotent_and_completed_refund_marks_transaction_refunded(): void
    {
        [$providerId, $transactionId] = $this->createPaidTransaction();
        $service = app(PaymentRefundService::class);
        $provider = new FakeRefundProvider();

        $first = $service->request($transactionId, 100.00, 'RF-001', $provider, 'customer request');
        $second = $service->request($transactionId, 100.00, 'RF-001', $provider, 'customer request');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, DB::table('payment_refunds')->where('provider_id', $providerId)->where('refund_no', 'RF-001')->count());

        $this->assertTrue($service->recordCallback($first['id'], 'paid', 'REF-001', ['status' => 'paid']));
        $this->assertFalse($service->recordCallback($first['id'], 'paid', 'REF-001', ['status' => 'paid']));
        $this->assertSame('completed', DB::table('payment_refunds')->where('id', $first['id'])->value('status'));
        $this->assertSame('refunded', DB::table('payment_transactions')->where('id', $transactionId)->value('status'));
    }

    public function test_partial_refund_does_not_mark_transaction_fully_refunded(): void
    {
        [$providerId, $transactionId] = $this->createPaidTransaction();
        $service = app(PaymentRefundService::class);
        $provider = new FakeRefundProvider();

        $refund = $service->request($transactionId, 40.00, 'RF-002', $provider);
        $this->assertTrue($service->recordCallback($refund['id'], 'paid', 'REF-002'));

        $this->assertSame('completed', DB::table('payment_refunds')->where('id', $refund['id'])->value('status'));
        $this->assertSame('paid', DB::table('payment_transactions')->where('id', $transactionId)->value('status'));
        $this->assertSame($providerId, DB::table('payment_refunds')->where('id', $refund['id'])->value('provider_id'));
    }

    public function test_refund_cannot_exceed_remaining_refundable_amount(): void
    {
        [, $transactionId] = $this->createPaidTransaction();
        $service = app(PaymentRefundService::class);
        $provider = new FakeRefundProvider();

        $refund = $service->request($transactionId, 60.00, 'RF-003', $provider);
        $this->assertTrue($service->recordCallback($refund['id'], 'paid', 'REF-003'));

        $this->expectException(\RuntimeException::class);
        $service->request($transactionId, 50.01, 'RF-004', $provider);
    }

    public function test_refund_requires_paid_transaction(): void
    {
        [, $transactionId] = $this->createPaidTransaction('created');
        $service = app(PaymentRefundService::class);

        $this->expectException(\RuntimeException::class);
        $service->createIntent($transactionId, 10.00, 'RF-005');
    }

    /** @return array{0:string,1:string} */
    private function createPaidTransaction(string $status = 'paid'): array
    {
        $tenantId = (string) Str::uuid();
        $storeId = (string) Str::uuid();
        $providerId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $channelId = (string) Str::uuid();
        $orderId = (string) Str::uuid();
        $transactionId = (string) Str::uuid();

        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Refund Tenant', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stores')->insert(['id' => $storeId, 'tenant_id' => $tenantId, 'name' => 'Refund Store', 'code' => 'REFUND', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_providers')->insert(['id' => $providerId, 'code' => 'allinpay', 'name' => 'Allinpay', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_accounts')->insert(['id' => $accountId, 'provider_id' => $providerId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'merchant_no' => 'REFUND-MERCHANT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_channels')->insert(['id' => $channelId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_code' => 'allinpay_jsapi', 'channel_name' => 'Allinpay JSAPI', 'payment_method' => 'wechat', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('orders')->insert(['id' => $orderId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_no' => 'REF-'.Str::upper(Str::random(8)), 'status' => $status === 'paid' ? 'paid' : 'pending', 'subtotal' => 100, 'discount_amount' => 0, 'total_amount' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_transactions')->insert(['id' => $transactionId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_id' => $orderId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_id' => $channelId, 'merchant_trade_no' => 'REFUND-'.Str::upper(Str::random(12)), 'provider_trade_no' => $status === 'paid' ? 'ALI-PAID-001' : null, 'amount' => 100, 'status' => $status, 'paid_at' => $status === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);

        return [$providerId, $transactionId];
    }
}

final class FakeRefundProvider implements PaymentProviderContract
{
    public function pay(array $request): array { return []; }
    public function scanPay(array $request): array { return []; }
    public function microPay(array $request): array { return []; }
    public function query(array $request): array { return []; }
    public function confirmQuery(array $request): array { return []; }
    public function closeNative(array $request): array { return []; }
    public function refund(array $request): array
    {
        return [
            'status' => 'processing',
            'refund_trade_no' => $request['refund_trade_no'] ?? null,
            'provider_trade_no' => 'REF-PROVIDER-001',
        ];
    }
    public function verifyCallback(array $payload, array $headers = []): bool { return true; }
    public function parseCallback(array $payload, array $headers = []): array { return $payload; }
    public function reconcile(array $request): array { return []; }
}
