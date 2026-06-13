<x-mail::message>
# Thank you for your purchase!

Hi {{ $order->user->name }},

Your order **{{ $order->order_number }}** is confirmed and your books are ready to download.

<x-mail::table>
| Title | Price |
|:----- | -----:|
@foreach ($order->items as $item)
| {{ $item->title }} | ${{ number_format($item->price, 2) }} |
@endforeach
| **Total** | **${{ number_format($order->total, 2) }}** |
</x-mail::table>

<x-mail::button :url="route('library.index')">
Go to My Library
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
