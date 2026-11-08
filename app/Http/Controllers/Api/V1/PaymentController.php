<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Exceptions\BankRequestException;
use App\Exceptions\InvalidPaymentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorizePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment as PaymentModel;
use App\Services\Payments\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ], $request->header('Idempotency-Key'));
        } catch (BankRequestException $exception) {
            return $this->bankErrorResponse($exception);
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
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function capture(Request $request, PaymentModel $payment): JsonResponse
    {
        if (! $payment->canTransitionTo(PaymentStatus::CAPTURED)) {
            return $this->invalidTransitionResponse($payment, PaymentStatus::CAPTURED);
        }

        $paymentObj = new Payment(config('services.bank.provider'));

        try {
            $bankCapture = $paymentObj->capture([
                'authorization_id' => $payment->bank_authorization_id,
                'amount' => $payment->amount,
            ], $request->header('Idempotency-Key'));
        } catch (BankRequestException $exception) {
            return $this->bankErrorResponse($exception);
        }

        $payment->bank_capture_id = $bankCapture['capture_id'];
        $payment->transitionTo(PaymentStatus::CAPTURED);
        $payment->save();

        return response()->json([
            'message' => 'Payment captured successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function void(Request $request, PaymentModel $payment): JsonResponse
    {
        if (! $payment->canTransitionTo(PaymentStatus::VOIDED)) {
            return $this->invalidTransitionResponse($payment, PaymentStatus::VOIDED);
        }

        $paymentObj = new Payment(config('services.bank.provider'));

        try {
            $bankVoid = $paymentObj->void([
                'authorization_id' => $payment->bank_authorization_id,
            ], $request->header('Idempotency-Key'));
        } catch (BankRequestException $exception) {
            return $this->bankErrorResponse($exception);
        }

        $payment->bank_void_id = $bankVoid['void_id'];
        $payment->transitionTo(PaymentStatus::VOIDED);
        $payment->save();

        return response()->json([
            'message' => 'Payment voided successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function refund(Request $request, PaymentModel $payment): JsonResponse
    {
        if (! $payment->canTransitionTo(PaymentStatus::REFUNDED)) {
            return $this->invalidTransitionResponse($payment, PaymentStatus::REFUNDED);
        }

        $paymentObj = new Payment(config('services.bank.provider'));

        try {
            $bankRefund = $paymentObj->refund([
                'capture_id' => $payment->bank_capture_id,
                'amount' => $payment->amount,
            ], $request->header('Idempotency-Key'));
        } catch (BankRequestException $exception) {
            return $this->bankErrorResponse($exception);
        }

        $payment->bank_refund_id = $bankRefund['refund_id'];
        $payment->transitionTo(PaymentStatus::REFUNDED);
        $payment->save();

        return response()->json([
            'message' => 'Payment refunded successfully',
            'data' => new PaymentResource($payment),
        ]);
    }

    protected function bankErrorResponse(BankRequestException $exception): JsonResponse
    {
        $status = $exception->status >= 500 || $exception->status === 0
            ? 502
            : $exception->status;

        return response()->json([
            'message' => $exception->getMessage(),
            'error' => $exception->errorCode,
        ], $status);
    }

    protected function invalidTransitionResponse(PaymentModel $payment, PaymentStatus $to): JsonResponse
    {
        $exception = new InvalidPaymentTransitionException($payment->status, $to);

        return response()->json([
            'message' => $exception->getMessage(),
            'error' => 'invalid_transition',
            'data' => [
                'id' => $payment->id,
                'status' => $payment->status->value,
            ],
        ], 409);
    }
}
