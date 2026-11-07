<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Exceptions\BankRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizePaymentRequest;
use App\Models\Payment as PaymentModel;
use App\Services\Payments\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function authorizePayment(AuthorizePaymentRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $paymentObj = new Payment(config('services.bank.provider'));

        try {
            $bankAuthorization = $paymentObj->authorize([
                'card_number' => $payload['card_number'],
                'cvv' => $payload['cvv'],
                'expiry_month' => $payload['expiry_month'],
                'expiry_year' => $payload['expiry_year'],
                'amount' => $payload['amount'],
            ], Str::uuid());
        } catch (BankRequestException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->errorCode,
            ], $this->statusFromBankException($exception));
        }

        $payment = new PaymentModel([
            'order_id' => $payload['order_id'],
            'customer_id' => $payload['customer_id'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'] ?? 'USD',
            'card_last_four' => substr($payload['card_number'], -4),
            'bank_authorization_id' => $bankAuthorization['authorization_id'],
        ]);
        $payment->transitionTo(PaymentStatus::AUTHORIZED);
        $payment->save();

        return response()->json([
            'message' => 'Payment authorized successfully',
            'data' => [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'customer_id' => $payment->customer_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status->value,
                'authorized_at' => $payment->authorized_at,
            ],
        ], 201);
    }

    protected function statusFromBankException(BankRequestException $exception): int
    {
        if ($exception->status >= 500 || $exception->status === 0) {
            return 502;
        }

        return $exception->status;
    }
}
