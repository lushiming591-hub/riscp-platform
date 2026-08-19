# RISCP Core V1.0

## 目标

RISCP 第一版采用 **Core + Industry Module** 架构。餐饮作为第一条商业化产品线，零售、美业在后续复用 Core。

## Core 原则

- 多租户：Tenant → Merchant/Brand → Store
- 统一身份：User / Employee / Role / Permission
- 统一客户：Member / Customer / Tag
- 统一商品：Product / SKU / Unit / Category
- 统一交易：Order / OrderItem / Payment / Refund
- 统一库存：Warehouse / Stock / StockTransaction
- 统一营销：Campaign / Coupon / Promotion
- 统一结算：Settlement / Commission / Ledger
- 统一审计：AuditLog

## 行业模块

### Restaurant
桌台、点餐、KDS、菜品、规格、BOM/Recipe、餐饮库存。

### Retail
条码、快速收银、采购、库存、盘点、调拨、零售会员。

### Beauty
预约、技师、服务项目、卡项、储值、提成、客户CRM。

## 联盟预留

交易完成后通过领域事件产生 `OrderCompleted`。联盟模块只消费标准交易事件，不修改行业订单核心逻辑。

默认商业参数：

- merchant_discount_rate = 10%
- referrer_commission_rate = 3%
- platform_service_rate = 2%
- referrer_first_order_rate = 3%
- referrer_second_order_rate = 1%
- referrer_lifetime_order_rate = 0%
- attribution_days = 90

所有参数必须配置化，不写死在业务代码中。

## 资金安全原则

支付、退款、分账、提现均必须通过具备相应资质的支付机构/银行等合规渠道完成；RISCP Core 只记录业务订单、支付状态、结算状态及对账结果。
