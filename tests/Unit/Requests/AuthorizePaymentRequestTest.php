<?php

use App\Http\Requests\AuthorizePaymentRequest;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

it('authorizes the request', function () {
    expect((new AuthorizePaymentRequest)->authorize())->toBeTrue();
});

it('accepts a valid payload', function () {
    $validator = Validator::make([
        'order_id' => 'ord_100',
        'customer_id' => 'cus_200',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], (new AuthorizePaymentRequest)->rules());

    expect($validator->fails())->toBeFalse();
});

it('rejects a missing order id', function () {
    $validator = Validator::make([
        'customer_id' => 'cus_200',
        'amount' => 5000,
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], (new AuthorizePaymentRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->toArray())->toHaveKey('order_id');
}); 

it('rejects a non integer amount', function () {
    $validator = Validator::make([
        'order_id' => 'ord_100',
        'customer_id' => 'cus_200',
        'amount' => 'fifty',
        'card_number' => '4111111111111111',
        'cvv' => '123',
        'expiry_month' => 12,
        'expiry_year' => 2030,
    ], (new AuthorizePaymentRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->toArray())->toHaveKey('amount');
});
