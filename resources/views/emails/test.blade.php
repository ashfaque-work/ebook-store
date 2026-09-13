<x-mail::message>
# Email is working

This is a test from **{{ $storeName }}**.

If you are reading it, receipts and password resets will reach your customers too.

- Sent at {{ $sentAt }}
- Delivered through the `{{ $transport }}` mailer

If it landed in spam, add SPF and DKIM records for your sending domain — that is
usually the whole fix.

Thanks,<br>
{{ $storeName }}
</x-mail::message>
