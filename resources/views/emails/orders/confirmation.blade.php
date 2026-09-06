<x-mail::message>
# Thank you for your purchase

Hi {{ $order->user->name }},

Your order **{{ $order->order_number }}** is confirmed and your books are in your library now.

<x-mail::table>
| Title | Price |
|:----- | -----:|
@foreach ($order->items as $item)
| {{ $item->title }} | {{ $item->price()->inr() }} |
@endforeach
@if ($order->tax_paise > 0)
| Subtotal | {{ $order->subtotal()->inr() }} |
@if ($order->tax_type === \App\Models\Order::TAX_IGST)
| IGST | {{ $order->tax()->inr() }} |
@else
| CGST | {{ $order->halfTax()->inr() }} |
| SGST | {{ \App\Support\Money::fromPaise($order->tax_paise - $order->halfTax()->paise)->inr() }} |
@endif
@endif
| **Total** | **{{ $order->total()->inr() }}** |
</x-mail::table>

@if ($order->invoice_number)
Invoice number: **{{ $order->invoice_number }}**
@endif

<x-mail::button :url="route('library.index')">
Go to my library
</x-mail::button>

Keep this email — it is your receipt. If anything looks wrong, reply to it and we will fix it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
