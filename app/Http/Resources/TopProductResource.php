<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id'     => $this->product_id,
            'name'           => $this->name,
            'sku'            => $this->sku,
            'total_quantity' => (int) $this->total_quantity,
            'total_revenue'  => $this->total_revenue,
        ];
    }
}
