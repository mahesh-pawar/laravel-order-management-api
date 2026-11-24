<x-mail::message>
# Thank you for your order!

Order **#{{ $order->id }}** has been placed. Status: **{{ ucfirst($order->status->value) }}**.

<x-mail::table>
| Product | Qty | Unit price | Line total |
|:--------|:---:|-----------:|-----------:|
@foreach ($order->items as $item)
| {{ $item->product->name }} | {{ $item->quantity }} | {{ $item->unit_price }} | {{ $item->line_total }} |
@endforeach
| **Total** | | | **{{ $order->total_amount }}** |
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>