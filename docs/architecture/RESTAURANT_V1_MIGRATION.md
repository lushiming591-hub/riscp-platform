# Restaurant V1.0 源码改造计划

## 基础底座

采用 LaraCarte 作为餐饮业务加速底座。当前公开源码基于 Laravel 12、Livewire 3、MySQL，并已包含多租户、QR 点餐、POS、KDS、会员、库存、Recipe/BOM、支付等能力。

## 保留能力

- Tenant Scoping
- Merchant/Owner 后台
- 菜品/分类/规格/加料
- 桌台与二维码点餐
- POS 收银
- KDS / 厨房状态
- Customer / Loyalty
- Recipe / Ingredient
- Stock Movement
- Payment adapter
- Realtime / Reverb

## 必须RISCP化的能力

1. Tenant、Merchant、Brand、Store 统一命名和关系。
2. Customer → Member/Customer，进入统一CRM模型。
3. Product/ProductVariant → RISCP Product/SKU。
4. Order/OrderItem → RISCP Order 标准。
5. 库存扣减改成 StockTransaction 账本式记录，保证可追溯和幂等。
6. 支付改为 PaymentGateway Adapter，不把具体支付机构写死在订单模型。
7. 增加 Settlement/Ledger 预留，支持未来联盟佣金和平台服务费。
8. 增加 AuditLog。
9. 增加统一 API 与领域事件。

## 第一版业务验收

开店 → 建菜品 → 配方 → 开台 → 扫码点餐 → POS收银 → 支付状态确认 → KDS → 出餐 → BOM扣料 → 库存流水 → 会员累计 → 日结。

## 重要改造

现有源码的 Order 模型包含直接 decrement 产品和 ingredient stock 的逻辑。RISCP V1 不应继续把库存变化作为订单模型中的直接副作用，而应由 Inventory/StockTransaction Service 在事务中执行，并以业务事件保证幂等。
