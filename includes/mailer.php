<?php
declare(strict_types=1);

/**
 * Send an email through Gmail SMTP using PHPMailer.
 * Falls back to writing the message into logs/mail.log when SMTP
 * credentials are not configured (e.g. local development).
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $cfg  = config()['smtp'];
    $log  = __DIR__ . '/../logs/mail.log';
    $entry = sprintf(
        "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $textBody !== '' ? $textBody : strip_tags($htmlBody),
        str_repeat('-', 60)
    );
    @file_put_contents($log, $entry, FILE_APPEND);

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if ($cfg['username'] === '' || $cfg['password'] === '' || !is_file($autoload)) {
        error_log('TradeHub mail: SMTP not configured, message written to logs/mail.log.');
        return false;
    }

    require_once $autoload;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'] === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('TradeHub mail failed: ' . $e->getMessage());
        return false;
    }
}

/** Order confirmation email for a completed checkout. */
function send_order_confirmation(array $user, string $reference, array $items, float $total, string $paymentMethod): bool
{
    $rowsHtml = '';
    $rowsText = '';
    foreach ($items as $item) {
        $line = (float) $item['unit_price'] * (int) $item['quantity'];
        $rowsHtml .= '<tr>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee">' . e($item['product_name'])
            . ' × ' . (int) $item['quantity'] . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right">' . format_price($line) . '</td>'
            . '</tr>';
        $rowsText .= sprintf("%s x%d — %s\n", $item['product_name'], $item['quantity'], format_price($line));
    }

    $site = setting('site_name', 'TradeHub');
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2933">'
        . '<h2 style="color:#0f766e">Order confirmed</h2>'
        . '<p>Hi ' . e($user['full_name']) . ', thanks for supporting local sellers.</p>'
        . '<p>Your order <strong>' . e($reference) . '</strong> has been received.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0">' . $rowsHtml
        . '<tr><td style="padding:12px 0;font-weight:bold">Total</td>'
        . '<td style="padding:12px 0;text-align:right;font-weight:bold">' . format_price($total) . '</td></tr>'
        . '</table>'
        . '<p><strong>Payment method:</strong> ' . e(strtoupper($paymentMethod)) . '<br>'
        . '<strong>Deliver to:</strong> ' . e($user['address'] ?: '—') . '</p>'
        . '<p style="color:#52606d;font-size:13px">We will notify you once your order is on the way. — ' . e($site) . '</p>'
        . '</div>';

    $text = "Order confirmed\n\nHi {$user['full_name']}, your order {$reference} has been received.\n\n"
        . $rowsText . "\nTotal: " . format_price($total) . "\nPayment: " . strtoupper($paymentMethod) . "\n";

    return send_mail($user['email'], "{$site} order confirmation {$reference}", $html, $text);
}
