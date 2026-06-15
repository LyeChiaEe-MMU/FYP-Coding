<?php
/*
 * ═══════════════════════════════════════════════════════
 *  APEX STORE — Gmail SMTP Configuration
 * ═══════════════════════════════════════════════════════
 *
 *  STEP 1 — Enable OpenSSL in XAMPP:
 *    Open: C:\xampp\php\php.ini
 *    Find:  ;extension=openssl
 *    Change to: extension=openssl   (remove the semicolon)
 *    Restart Apache in XAMPP Control Panel.
 *
 *  STEP 2 — Turn on 2-Step Verification on your Google account:
 *    Go to: https://myaccount.google.com/security
 *    Click "2-Step Verification" and follow the steps.
 *
 *  STEP 3 — Generate a Gmail App Password:
 *    Go to: https://myaccount.google.com/apppasswords
 *    App name: type "Apex Store" → click Create
 *    Google shows a 16-character password like: abcd efgh ijkl mnop
 *    Copy it (you won't see it again).
 *
 *  STEP 4 — Fill in the values below and save this file.
 * ═══════════════════════════════════════════════════════
 */
return [
    'from' => 'yourgmail@gmail.com',    // ← replace with your Gmail address
    'user' => 'yourgmail@gmail.com',    // ← same Gmail address
    'pass' => 'xxxx xxxx xxxx xxxx',    // ← 16-char App Password (spaces are fine)
];
