# Copilot instructions for Paymenter-Midtrans

## Big picture

- This repo is a **Paymenter gateway extension**, not a standalone Laravel app.
- Core logic is in [Midtrans.php](../Midtrans.php); `Midtrans` extends Paymenter’s `Gateway`.
- `boot()` loads extension routes from [routes/web.php](../routes/web.php) and registers Blade views from [resources/views](../resources/views).

## End-to-end payment flow

1. `pay($invoice, $total)` validates hard requirements: currency must be `IDR`, amount must be `>= 5000`.
2. It generates `order_id` as `PAYMENTER-{invoice_id}-{hash}` and creates a Snap transaction via Midtrans `/snap/v1/transactions`.
3. On success, it renders [resources/views/pay.blade.php](../resources/views/pay.blade.php), which injects `snap.js` and calls `window.snap.pay(token, callbacks)`.
4. Frontend `onSuccess`/`onPending` callbacks redirect to `route('invoices.show', $invoice) . '?checkPayment=true'`.
5. Midtrans sends async notifications to `/extensions/gateways/midtrans/webhook`; `webhook()` records payment with `ExtensionHelper::addPayment(...)`.

## Integration and safety assumptions

- Keep `order_id` format stable; webhook invoice extraction depends on splitting `PAYMENTER-{invoice_id}-...`.
- Webhook currently processes only `status_code === "200"` and `transaction_status` in `['capture', 'settlement']`.
- Amount and `transaction_id` are required before `addPayment()` is called.
- Environment switching uses `debug_mode` for both API URL and frontend Snap script host.

## Project-specific conventions

- Duplicate eligibility checks (`pay()` + `canUseGateway()`) are intentional for UX + backend safety.
- Use Paymenter helpers (`ExtensionHelper::error`, `ExtensionHelper::addPayment`) instead of custom DB writes.
- Preserve structured Laravel logging (`\Log::info|warning|error(..., [...])`) for operational debugging.
- Keep public gateway API methods stable unless Paymenter requires a signature change (`boot`, `getMetadata`, `getConfig`, `pay`, `webhook`, `canUseGateway`).

## Developer workflow (practical)

- No local build/test pipeline is defined in this repo; validate by installing into a running Paymenter instance.
- Install path convention: `app/Extensions/Gateways/Midtrans`.
- Manual verification loop:
  1. Configure `server_key`, `merchant_id`, `client_key`, `debug_mode`.
  2. Create an `IDR` invoice with total `>= 5000`.
  3. Confirm Snap popup loads and redirects back to invoice page.
  4. Send/receive webhook and verify payment is added exactly once.

## Documentation/source-of-truth notes

- Route source of truth is [routes/web.php](../routes/web.php): webhook path is `/extensions/gateways/midtrans/webhook`.
- If setup docs drift (for example README callback URL text), align docs with code in the same change.
- Prefer minimal, extension-scoped edits in [Midtrans.php](../Midtrans.php), [routes/web.php](../routes/web.php), and [resources/views](../resources/views).
