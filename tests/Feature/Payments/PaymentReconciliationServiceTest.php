<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Core\Payments\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_matches_provider_rows_and_detects_amount_mismatch(): void
    {
        [$providerId, $accountId] = $this->createPaymentFixture();

        $result = app(PaymentReconciliationService::class)->reconcile(
            $providerId,
            $accountId,
            now()->toDateString(),
            [
                [
                    'provider_trade_no' => 'ALI-001',
                    'merchant_trade_no' => 'RISC-001',
                    'amount' => 100.00,
                    'trade_time' => now()->format('YmdHis'),
                    'raw' => ['ALI-001', 'PAY', '10000', '0', now()->format('YmdHis'), 'RISC-001'],
                ],
                [
                    'provider_trade_no' => 'ALI-002',
                    'merchant_trade_no' => 'RISC-002',
                    'amount' => 99.00,
                    'trade_time' => now()->format('YmdHis'),
                    'raw' => ['ALI-002', 'PAY', '9900', '0', now()->format('YmdHis'), 'RISC-002'],
                ],
            ],
        );

        $this->assertSame('exception', $result['status']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['matched']);
        $this->assertGreaterThanOrEqual(1, $result['mismatched']);
        $this->assertSame(2, DB::table('payment_reconciliation_items')->where('reconciliation_id', $result['reconciliation_id'])->count());
    }

    /** @return array{0:string,1:string} */
    private function createPaymentFixture(): array
    {
        $tenantId = (string) Str::uuid();
        $storeId = (string) Str::uuid();
        $providerId = (string) Str::uuid();
        $accountId = (string) Str::uuid();
        $channelId = (string) Str::uuid();
        $orderId = (string) Str::uuid();

        DB::table('tenants')->insert(['id' => $tenantId, 'name' => 'Recon Tenant', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stores')->insert(['id' => $storeId, 'tenant_id' => $tenantId, 'name' => 'Recon Store', 'code' => 'RECON', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_providers')->insert(['id' => $providerId, 'code' => 'allinpay', 'name' => 'Allinpay', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_accounts')->insert(['id' => $accountId, 'provider_id' => $providerId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'merchant_no' => 'RECON-MERCHANT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_channels')->insert(['id' => $channelId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_code' => 'allinpay_jsapi', 'channel_name' => 'Allinpay JSAPI', 'payment_method' => 'wechat', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([['RISC-001', 'ALI-001', 100.00], ['RISC-002', 'ALI-002', 100.00]] as [$merchantNo, $providerNo, $amount]) {
            $orderId = (string) Str::uuid();
            DB::table('orders')->insert(['id' => $orderId, 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_no' => $merchantNo, 'status' => 'paid', 'subtotal' => $amount, 'discount_amount' => 0, 'total_amount' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('payment_transactions')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'store_id' => $storeId, 'order_id' => $orderId, 'provider_id' => $providerId, 'payment_account_id' => $accountId, 'channel_id' => $channelId, 'merchant_trade_no' => $merchantNo, 'provider_trade_no' => $providerNo, 'amount' => $amount, 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$providerId, $accountId];
    }
}
