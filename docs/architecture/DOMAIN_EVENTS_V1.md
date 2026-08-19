# RISCP Domain Events V1.0

## 核心事件

- OrderCreated
- OrderPaid
- OrderRefunded
- OrderCompleted
- StockReserved
- StockDeducted
- StockAdjusted
- InventoryReceived
- SettlementCreated
- SettlementReleased
- CouponIssued
- CouponRedeemed
- ReferralAttributed

## 设计要求

事件必须包含：event_id、event_type、occurred_at、tenant_id、store_id、aggregate_type、aggregate_id、payload、schema_version。

所有消费者必须幂等处理 event_id。

## 联盟事件

联盟不直接读取餐饮/零售/美业内部实现。联盟只订阅 `OrderCompleted`、`OrderRefunded`、`CouponRedeemed` 等标准事件，并根据配置计算引流归属、优惠承担、引流佣金和平台服务费。
