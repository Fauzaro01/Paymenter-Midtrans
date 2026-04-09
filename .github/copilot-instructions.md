# Copilot instructions for Paymenter-Midtrans

## Big picture

- This repository is a **Paymenter gateway extension** (not a full Laravel app). The core integration lives in [Midtrans.php](../Midtrans.php).
- `Midtrans` extends Paymenter’s `Gateway` base and is loaded by Paymenter from `app/Extensions/Gateways/Midtrans`.
- `boot()` wires extension-local routes from [routes/web.php](../routes/web.php) and registers blade views from [resources/views](../resources/views).

## Payment flow (important)

1. `pay($invoice, $total)` in [Midtrans.php](../Midtrans.php) validates constraints (`IDR`, minimum `5000`).
2. It creates `order_id` with `PAYMENTER-{invoice_id}-{hash}` format and calls Midtrans Snap API (`/snap/v1/transactions`).
3. On success it renders [resources/views/pay.blade.php](../resources/views/pay.blade.php), which dynamically loads `snap.js` and calls `window.snap.pay(...)`.
4. Frontend callbacks redirect back to Paymenter invoice routes with query flags (`checkPayment`, `midtrans`).
5. Midtrans server notifications hit the webhook route in [routes/web.php](../routes/web.php), then `webhook(Request $request)` records payment via `ExtensionHelper::addPayment(...)`.

## Webhook and idempotency assumptions

- Webhook processing currently accepts only status code `"200"` with `transaction_status` in `['capture','settlement']`.
- Invoice ID is parsed from `order_id` by splitting `PAYMENTER-{invoice_id}-...`; keep this format stable if changing order ID generation.
- Payment recording depends on `transaction_id` from Midtrans and `ExtensionHelper::addPayment(...)` in [Midtrans.php](../Midtrans.php).

## Project-specific conventions

- Currency is intentionally hard-limited to `IDR` in both `pay()` and `canUseGateway()`.
- Minimum amount check is duplicated by design (`pay()` + `canUseGateway()`) to guard both UX and gateway eligibility.
- Use Paymenter helpers for reporting/recording (`ExtensionHelper::error`, `ExtensionHelper::addPayment`) instead of custom persistence.
- Logging uses Laravel `\Log` with structured arrays; preserve this pattern for new diagnostics.
- Views rely on Paymenter blade directives (`@script`) and existing app routes (`invoices.show`, `invoices.index`).

## External integration points

- Midtrans API endpoints are environment-switched by `debug_mode`:
  - Sandbox: `https://app.sandbox.midtrans.com/snap/v1/transactions`
  - Production: `https://app.midtrans.com/snap/v1/transactions`
- Frontend script source also switches by `debugMode` in [resources/views/pay.blade.php](../resources/views/pay.blade.php).
- Webhook URL implemented in code is `/extensions/gateways/midtrans/webhook` (see [routes/web.php](../routes/web.php)). If docs mention another path, treat code as source of truth.

## Developer workflow in this repo

- There is no standalone test/build pipeline here; validate changes by loading this extension inside a running Paymenter instance.
- Manual install path from docs: copy repo to `app/Extensions/Gateways/Midtrans` (see [README.md](../README.md)).
- Typical verification loop:
  1. Configure gateway keys (`server_key`, `merchant_id`, `client_key`, `debug_mode`).
  2. Create IDR invoice >= 5000 and run checkout.
  3. Confirm Snap popup loads and redirects to invoice.
  4. Trigger/receive webhook and verify payment is added once.

## When making edits

- Keep public method signatures unchanged (`boot`, `getMetadata`, `getConfig`, `pay`, `webhook`, `canUseGateway`) unless Paymenter API requires it.
- Prefer minimal, extension-scoped changes in [Midtrans.php](../Midtrans.php), [routes/web.php](../routes/web.php), and [resources/views](../resources/views).
- If behavior changes affect setup/callbacks, update [README.md](../README.md) in the same change.
