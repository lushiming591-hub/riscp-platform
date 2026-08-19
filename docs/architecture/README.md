# RISCP Architecture

## Core

RISCP Core provides the shared domain model:

- Tenant / Merchant / Brand / Store
- User / Employee / Role / Permission
- Customer / Member / Tag
- Product / SKU / Unit / Supplier
- Order / Order Item / Payment / Refund
- Inventory / Warehouse / Inventory Transaction
- Promotion / Coupon
- Settlement / Reconciliation
- Audit Log / API

## Industry Modules

- Restaurant: POS, QR ordering, KDS, Recipe/BOM, inventory
- Retail: POS, barcode, purchasing, inventory, membership
- Beauty: CRM, appointment, staff, service, cards, commission

## Platform Modules

- Alliance
- SCM
- Data
- AI
- Finance

## Integration Principle

Do not merge three open-source applications blindly. Each industry application connects to RISCP Core through adapters and standardized domain contracts. Shared capabilities are extracted into Core progressively.

## Development Order

1. RISCP Core foundation
2. Restaurant V1.0
3. Retail
4. Beauty
5. Alliance
6. SCM
7. Data / AI / Finance
