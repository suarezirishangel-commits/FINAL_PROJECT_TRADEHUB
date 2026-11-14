<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2933">
  <h2 style="color:#0f766e">Order confirmed</h2>
  <p>Hi {{ $user->full_name }}, thanks for supporting local sellers.</p>
  <p>Your order <strong>{{ $reference }}</strong> has been received.</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0">
    @foreach ($items as $item)
      <tr>
        <td style="padding:8px 0;border-bottom:1px solid #eee">{{ $item['product_name'] }} × {{ $item['quantity'] }}</td>
        <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right">₱{{ number_format($item['total_amount'], 2) }}</td>
      </tr>
    @endforeach
    <tr>
      <td style="padding:12px 0;font-weight:bold">Total</td>
      <td style="padding:12px 0;text-align:right;font-weight:bold">₱{{ number_format($total, 2) }}</td>
    </tr>
  </table>
  <p><strong>Payment method:</strong> {{ strtoupper($paymentMethod) }}<br>
    <strong>Deliver to:</strong> {{ $user->address ?: '—' }}</p>
  <p style="color:#52606d;font-size:13px">We will notify you once your order is on the way. — {{ $siteName }}</p>
</body>
</html>
