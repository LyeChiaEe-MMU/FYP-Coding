<?php
/*
 * Gmail SMTP mailer — works on XAMPP Windows (SSL port 465, no PHPMailer needed)
 * Returns ['ok'=>true] on success or ['ok'=>false,'error'=>'reason'] on failure.
 *
 * REQUIREMENTS (check php.ini):
 *   extension=openssl      ← must be un-commented
 *
 * SETUP: fill in admin/mail_config.php with your Gmail + App Password.
 */
function apex_send_mail(string $to_email, string $to_name, string $subject, string $html_body): array {
    $cfg = @include __DIR__ . '/mail_config.php';
    if(!is_array($cfg) || empty($cfg['from']) || $cfg['from'] === 'yourgmail@gmail.com'){
        return ['ok'=>false,'error'=>'Email not configured. Open admin/mail_config.php and fill in your Gmail address and App Password.'];
    }

    $from      = trim($cfg['from']);
    $user      = trim($cfg['user']);
    $pass      = str_replace(' ', '', $cfg['pass']); // strip spaces — App Passwords have spaces in groups of 4
    $from_name = 'Apex Store';

    // stream_socket_client with verify_peer=false fixes the XAMPP certificate issue
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if(!$socket){
        return ['ok'=>false,'error'=>"Cannot connect to Gmail: $errstr ($errno). Make sure 'extension=openssl' is enabled in php.ini (XAMPP → php.ini, remove the semicolon)."];
    }
    stream_set_timeout($socket, 15);

    // Read one (possibly multi-line) SMTP response
    $read = function() use ($socket): string {
        $out = '';
        while($line = @fgets($socket, 515)){
            $out .= $line;
            if(strlen($line) >= 4 && $line[3] === ' ') break; // last line of response
        }
        return $out;
    };

    $read(); // server greeting (220 ...)

    fwrite($socket, "EHLO smtp.gmail.com\r\n"); $read();
    fwrite($socket, "AUTH LOGIN\r\n");           $read();
    fwrite($socket, base64_encode($user)."\r\n"); $read();
    fwrite($socket, base64_encode($pass)."\r\n");
    $auth = $read();

    if(substr(trim($auth), 0, 3) !== '235'){
        fclose($socket);
        $hint = str_contains($auth, '535') ? ' — Wrong App Password or 2FA not enabled on Google account.' : '';
        return ['ok'=>false,'error'=>'Gmail login failed'.$hint.' Server said: '.trim(substr($auth,4,80))];
    }

    fwrite($socket, "MAIL FROM: <$from>\r\n");   $read();
    fwrite($socket, "RCPT TO: <$to_email>\r\n"); $read();
    fwrite($socket, "DATA\r\n");                  $read();

    // Encode subject so non-ASCII / special chars survive email clients
    $encoded_subject = '=?UTF-8?B?'.base64_encode($subject).'?=';

    $body  = "From: $from_name <$from>\r\n";
    $body .= "To: $to_name <$to_email>\r\n";
    $body .= "Subject: $encoded_subject\r\n";
    $body .= "MIME-Version: 1.0\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "X-Mailer: Apex Store Admin\r\n\r\n";
    $body .= $html_body."\r\n.\r\n";

    fwrite($socket, $body);
    $data_res = $read();

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if(substr(trim($data_res), 0, 3) !== '250'){
        return ['ok'=>false,'error'=>'Gmail rejected the message: '.trim($data_res)];
    }
    return ['ok'=>true];
}

/**
 * Wrap plain-text message body in a branded HTML email template.
 */
function apex_mail_html(string $body_text): string {
    $body_html = nl2br(htmlspecialchars($body_text, ENT_QUOTES, 'UTF-8'));
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:Arial,sans-serif;">
  <div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #ddd;">
    <div style="background:#C8543C;padding:20px 28px;">
      <div style="font-family:'Georgia',serif;font-size:1.6rem;font-weight:700;color:#fff;letter-spacing:3px;">APE<span style="color:#ffcdb4;">X</span></div>
      <div style="font-size:.75rem;color:rgba(255,255,255,.75);letter-spacing:2px;text-transform:uppercase;margin-top:2px;">Store Notification</div>
    </div>
    <div style="padding:28px;color:#2d2d2d;font-size:.95rem;line-height:1.8;">
      $body_html
    </div>
    <div style="padding:16px 28px;background:#f9f7f4;font-size:.72rem;color:#888;border-top:1px solid #eee;">
      This email was sent by Apex Store. Please do not reply directly — contact support if you have questions.
    </div>
  </div>
</body>
</html>
HTML;
}
