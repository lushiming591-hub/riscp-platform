# RISCP Restaurant V1.0

## Goal

Build the first sellable RISCP industry product from the selected restaurant open-source base, while establishing the common Core contracts needed by Retail, Beauty, Alliance and SCM.

## V1 Modules

1. Merchant / Store
2. Staff / Roles / Permissions
3. Tables / Areas
4. Categories / Products / SKUs / Modifiers / Combos
5. Recipe / BOM / Ingredients
6. POS / QR Ordering
7. Kitchen / KDS
8. Orders / Payments / Refunds
9. Inventory / Stock Ledger / Loss
10. Members / Coupons
11. Daily Closing
12. Operations Dashboard

## Main Flow

Open table -> order -> kitchen routing -> preparation -> settlement -> payment -> recipe stock deduction -> inventory ledger -> member record -> daily close.

## Non-negotiable Engineering Rules

- Money uses decimal types.
- Payment callbacks are idempotent.
- Inventory mutations are ledger-based and auditable.
- Order, payment, refund and settlement have explicit states.
- Tenant/store data must be isolated.
- Business rules such as discounts and fees are configurable.

## Alliance Reservation

The order and settlement model must leave room for future fields/services for:

- merchant-funded consumer benefit
- referrer commission
- RISCP platform service fee
- attribution window
- commission settlement status

The platform does not fund merchant promotions in the baseline model.
