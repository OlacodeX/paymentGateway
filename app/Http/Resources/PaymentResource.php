<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => [
                'value' => $this->status->value,
                'title' => $this->status->title(), 
                'description' => $this->status->description(),
            ],
            'authorized_at' => $this->authorized_at,
            'captured_at' => $this->captured_at,
            'voided_at' => $this->voided_at,
            'refunded_at' => $this->refunded_at,
        ];
    }
}
