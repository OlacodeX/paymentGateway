# TRADEOFFS

## Architecture

The gateway is a thin Laravel API in front of the mock bank. Mutating work lives in `PaymentController`. Bank calls are isolated behind `App\Services\Payments\Payment` and `MockBankProvider`, so retries, timeouts, and header wiring stay out of the controller (thin controller design). Payment lifecycle rules sit on the model via `HasPaymentState` trait while idempotency is handled/enforced by middleware (`EnsureIdempotency`) so every authorize/capture/void/refund path gets the same contract without copy-paste.

## State management

The `payments` row is the source of truth FicMart queries, not the bank. Status is a backed enum (`PaymentStatus`) with allowed transitions enforced in `HasPaymentState`. Invalid moves fail in the gateway with `409` even if a buggy client or bank quirk would allow them. Timestamps (`authorized_at`, `captured_at`, etc.) are set on transition so receipts stay auditable.

## Failure handling

Toward the bank, only transient failures are retried: HTTP 5xx and connection errors, with backoff. Client/validation-style failures (e.g. insufficient funds, expired auth) are not retried. After retries are exhausted, the controller maps bank 5xx and connection failures (`status === 0`) to gateway `502` (Bad Gateway). Other bank statuses are passed through with the bank’s error code and message.

Authorize is bank-first, then save. That means a crash after the bank succeeds can leave a bank auth without a local receipt; recovery closes that window on retry instead of inventing two-phase commit.

## Idempotency

There are two layers. FicMart sends `Idempotency-Key` to the gateway; the middleware stores method/path, recovery point, and the final status/body. A finished key replays the stored response (`X-Idempotent-Replayed: true`) and does not hit the bank again. A `STARTED` key is treated as resume: the handler runs again so we can heal after a crash mid-request. The same client key is forwarded to the bank so our own bank retries stay safe under the bank’s idempotency.

On authorize recovery, the receipt is found or created by `bank_authorization_id` so a retry after “bank ok / DB miss” (or after save but before marking the key finished) does not double-insert. Capture/void/refund short-circuit if the payment is already in the target status.

## What I'd do differently

Three gaps I’d close first if this left a portfolio exercise.

**Idempotency contract.** DB Unique on `(key, method, path)` lets the same key mean two different operations. I’d unique on the key (per merchant), store the request body hash, and return `409` when the body doesn’t match—closer to Stripe/Brandur and safer against client bugs.

**Crash window.** Bank-first + save is simple, but a process death after the bank approves still depends on the client retrying. I’d persist an “intent” row before the bank call (or use an outbox) so a worker can finish receipts without waiting for FicMart.
