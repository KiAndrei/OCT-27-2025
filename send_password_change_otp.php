<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email template for password change OTP
function getPasswordChangeOTPEmailTemplate($otp) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Change Verification</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f8f9fa;
                line-height: 1.6;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            .header {
                background: linear-gradient(135deg, #5D0E26, #8B1538);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
            }
            .otp-container {
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                border: 2px solid #8B1538;
                border-radius: 12px;
                padding: 30px;
                text-align: center;
                margin: 30px 0;
            }
            .otp-code {
                font-size: 36px;
                font-weight: bold;
                color: #8B1538;
                letter-spacing: 8px;
                margin: 20px 0;
                font-family: "Courier New", monospace;
            }
            .security-notice {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .security-notice h3 {
                color: #856404;
                margin: 0 0 10px 0;
                font-size: 18px;
            }
            .security-notice p {
                color: #856404;
                margin: 0;
                font-size: 14px;
            }
            .footer {
                background: linear-gradient(135deg, #5D0E26, #8B1538);
                color: rgba(255, 255, 255, 0.8);
                padding: 20px;
                text-align: center;
                font-size: 12px;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #8B1538, #5D0E26);
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🔐 Password Change Verification</h1>
                <p>Opiña Law Office Security System</p>
            </div>
            
            <div class="content">
                <h2 style="color: #8B1538; margin-top: 0;">Password Change Request</h2>
                <p>You have requested to change your password. To complete this process, please use the verification code below:</p>
                
                <div class="otp-container">
                    <h3 style="color: #8B1538; margin: 0 0 15px 0;">Your Verification Code</h3>
                    <div class="otp-code">' . $otp . '</div>
                    <p style="margin: 0; color: #666; font-size: 14px;">This code will expire in 1 minute</p>
                </div>
                
                <div class="security-notice">
                    <h3>🛡️ Security Notice</h3>
                    <p><strong>Important:</strong> If you did not request this password change, please ignore this email and contact our support team immediately. Your account security is our priority.</p>
                </div>
                
                <h3 style="color: #8B1538;">Next Steps:</h3>
                <ol style="color: #333;">
                    <li>Return to the password change form</li>
                    <li>Enter the verification code above</li>
                    <li>Create your new secure password</li>
                    <li>Complete the password change process</li>
                </ol>
                
                <p style="color: #666; font-size: 14px; margin-top: 30px;">
                    <strong>Note:</strong> This verification code is valid for 1 minute only. If you need a new code, please request another one from the password change form.
                </p>
            </div>
            
            <div class="footer">
                <p style="margin: 0;">
                    © ' . date('Y') . ' Opiña Law Office. All rights reserved. | Secure Password Change System
                </p>
                <p style="margin: 5px 0 0 0; font-size: 11px;">
                    This is an automated security message. Please do not reply to this email.
                </p>
            </div>
        </div>
    </body>
    </html>';
}

function send_password_change_otp($to, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Password Change Verification - Opiña Law Office';
        $mail->Body = getPasswordChangeOTPEmailTemplate($otp);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Password change OTP email error: ' . $mail->ErrorInfo);
        return false;
    }
}
?>
