# FicMart Payment Gateway

Laravel payment gateway for [FicMart](https://github.com/benx421/payment-gateway): authorize, capture, void, and refund against the mock bank on `:8787`. The gateway owns payment receipts as source of truth, enforces the payment state machine, and applies idempotency on mutating APIs.

## Requirements

- PHP 8.3+
- Composer
- Mock bank API on `http://localhost:8787` (from the challenge repo)
- SQLite by default (or any DB Laravel supports)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Bank settings in `.env`:

```env
BANK_PROVIDER=mock_bank
BANK_BASE_URL=http://localhost:8787
BANK_CONNECT_TIMEOUT=3
BANK_TIMEOUT=15
BANK_RETRY_TIMES=3
BANK_RETRY_SLEEP_MS=200
```

Start the app (example):

```bash
php artisan serve
```

API base: `http://localhost:8000/api/v1`

## Mock bank

Run the challenge mock bank separately so it listens on port **8787**. See the [payment-gateway](https://github.com/benx421/payment-gateway) bank / docker instructions.

Happy-path test card: `4111111111111111` / CVV `123` / expiry `12/2030`.

## API

All mutating `POST` routes require an `Idempotency-Key` header. Replays return the stored response with `X-Idempotent-Replayed: true`.

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/v1/payments/authorize` | Authorize (reserve funds) |
| `POST` | `/api/v1/payments/{payment}/capture` | Capture |
| `POST` | `/api/v1/payments/{payment}/void` | Void authorization |
| `POST` | `/api/v1/payments/{payment}/refund` | Refund capture |
| `GET` | `/api/v1/payments/by-order/{orderId}` | Receipt by order |
| `GET` | `/api/v1/payments/by-customer/{customerId}` | Payment history for a customer |

### Authorize example

```bash
curl -X POST http://localhost:8000/api/v1/payments/authorize \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "order_id": "ord_100",
    "customer_id": "cus_200",
    "amount": 5000,
    "card_number": "4111111111111111",
    "cvv": "123",
    "expiry_month": 12,
    "expiry_year": 2030
  }'
```

Amounts are integer **cents**. Currency defaults to `USD`.

### State machine

```text
PENDING → AUTHORIZED → CAPTURED → REFUNDED
              ↓
           VOIDED
```

Illegal transitions return `409` with `error: invalid_transition`. Upstream bank failures map to the bank status, or `502` for bank 5xx / connection errors after retries.

## Architecture (short)

| Concern | Location |
|---------|----------|
| HTTP API | `app/Http/Controllers/Api/V1/PaymentController.php` |
| Validation | `app/Http/Requests/AuthorizePaymentRequest.php` |
| API shape | `app/Http/Resources/PaymentResource.php` |
| Gateway idempotency | `app/Http/Middleware/EnsureIdempotency.php` + `IdempotencyKey` |
| Payment state | `app/Traits/HasPaymentState.php` + `PaymentStatus` |
| Bank client | `app/Services/Payments/` (`Payment` + `MockBankProvider`) |
| Receipts | `payments` table / `App\Models\Payment` |

## Tests

```bash
php artisan test --compact tests/Feature/Api/Payments
php artisan test --compact tests/Unit/Requests
```

## Tradeoffs

See [TRADEOFFS.md](TRADEOFFS.md).
