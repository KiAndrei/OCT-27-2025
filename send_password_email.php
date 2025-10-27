<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'config.php';

function getPasswordEmailTemplate($name, $email, $password, $user_type) {
    $user_type_display = ucfirst($user_type);
    
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Created - Opiña Law Office</title>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body style="margin: 0; padding: 0; font-family: \'Poppins\', Arial, sans-serif; background-color: #f8f9fa; line-height: 1.6;">
        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8f9fa; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden;">
                        
                        <!-- Header with Law Firm Branding -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #5D0E26, #8B1538); padding: 40px 30px; text-align: center;">
                                <div style="display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                                    <div style="width: 50px; height: 50px; background: #ffffff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 15px;">
                                        <div style="font-size: 24px; color: #5D0E26; font-weight: bold;">⚖</div>
                                    </div>
                                    <h1 style="color: #ffffff; font-family: \'Playfair Display\', serif; font-size: 32px; font-weight: 800; margin: 0; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);">
                                        Opiña Law Office
                                    </h1>
                                </div>
                                <p style="color: rgba(255, 255, 255, 0.9); font-size: 16px; margin: 0; font-weight: 300;">
                                    Professional Legal Services
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 50px 40px;">
                                <div style="text-align: center; margin-bottom: 40px;">
                                    <h2 style="color: #5D0E26; font-size: 28px; font-weight: 600; margin: 0 0 15px 0; font-family: \'Playfair Display\', serif;">
                                        Welcome to Opiña Law Office!
                                    </h2>
                                    <p style="color: #666; font-size: 16px; margin: 0; line-height: 1.5;">
                                        Dear <strong>' . htmlspecialchars($name) . '</strong>, your account has been successfully created as a <strong>' . $user_type_display . '</strong>.
                                    </p>
                                </div>
                                
                                <!-- Account Details Section -->
                                <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border: 2px solid #5D0E26; border-radius: 12px; padding: 30px; text-align: center; margin: 30px 0;">
                                    <h3 style="color: #5D0E26; font-size: 20px; font-weight: 600; margin: 0 0 20px 0; font-family: \'Playfair Display\', serif;">
                                        Your Account Information
                                    </h3>
                                    <div style="background: #ffffff; border: 2px solid #5D0E26; border-radius: 8px; padding: 25px; margin: 20px 0; text-align: left;">
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #5D0E26;">Email:</strong> 
                                            <span style="color: #666; font-family: \'Courier New\', monospace;">' . htmlspecialchars($email) . '</span>
                                        </div>
                                        <div style="margin-bottom: 15px;">
                                            <strong style="color: #5D0E26;">User Type:</strong> 
                                            <span style="color: #666; font-weight: 500;">' . $user_type_display . '</span>
                                        </div>
                                        <div style="margin-bottom: 0;">
                                            <strong style="color: #5D0E26;">Temporary Password:</strong> 
                                            <span style="color: #666; font-family: \'Courier New\', monospace; font-weight: 600; background: #fff3cd; padding: 5px 10px; border-radius: 4px; border: 1px solid #ffc107;">' . htmlspecialchars($password) . '</span>
                                        </div>
                                    </div>
                                    <p style="color: #666; font-size: 14px; margin: 15px 0 0 0;">
                                        Please use these credentials to log in to your account
                                    </p>
                                </div>
                                
                                <!-- Login Instructions -->
                                <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 20px; border-radius: 0 8px 8px 0; margin: 30px 0;">
                                    <h3 style="color: #0c5460; font-size: 18px; font-weight: 600; margin: 0 0 10px 0;">
                                        🔑 How to Access Your Account
                                    </h3>
                                    <ol style="color: #0c5460; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Go to the Opiña Law Office login page</li>
                                        <li>Enter your email address: <strong>' . htmlspecialchars($email) . '</strong></li>
                                        <li>Enter your temporary password: <strong>' . htmlspecialchars($password) . '</strong></li>
                                        <li>Click "Login" to access your dashboard</li>
                                        <li><strong>Important:</strong> Change your password after first login for security</li>
                                    </ol>
                                </div>
                                
                                <!-- Security Note -->
                                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 0 8px 8px 0; margin: 30px 0;">
                                    <h3 style="color: #856404; font-size: 18px; font-weight: 600; margin: 0 0 10px 0;">
                                        🔒 Security & Privacy
                                    </h3>
                                    <p style="color: #856404; font-size: 14px; margin: 0; line-height: 1.6;">
                                        At Opiña Law Office, we take your privacy and security seriously. This email contains sensitive account information. Please keep your credentials secure and do not share them with anyone. Your personal information is protected under attorney-client privilege and professional confidentiality standards.
                                    </p>
                                </div>
                                
                                <!-- Next Steps -->
                                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 0 8px 8px 0; margin: 30px 0;">
                                    <h3 style="color: #155724; font-size: 18px; font-weight: 600; margin: 0 0 10px 0;">
                                        📋 Next Steps
                                    </h3>
                                    <ul style="color: #155724; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Log in to your account using the credentials above</li>
                                        <li>Complete your profile information</li>
                                        <li>Change your temporary password to a secure one</li>
                                        <li>Familiarize yourself with your dashboard features</li>
                                        <li>Contact the administrator if you need assistance</li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8f9fa; padding: 30px 40px; border-top: 1px solid #e9ecef;">
                                <div style="text-align: center;">
                                    <p style="color: #5D0E26; font-size: 18px; font-weight: 600; margin: 0 0 10px 0; font-family: \'Playfair Display\', serif;">
                                        Opiña Law Office
                                    </p>
                                    <p style="color: #666; font-size: 14px; margin: 0 0 15px 0;">
                                        Professional Legal Services | Trusted Legal Solutions
                                    </p>
                                    <div style="border-top: 2px solid #5D0E26; width: 100px; margin: 20px auto;"></div>
                                    <p style="color: #999; font-size: 12px; margin: 0; line-height: 1.4;">
                                        This is an automated message from Opiña Law Office account management system.<br>
                                        Please do not reply to this email. If you need assistance, please contact the administrator.<br>
                                        <strong>Confidential Communication</strong> - This email contains privileged account information.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Professional Footer Bar -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #5D0E26, #8B1538); padding: 15px; text-align: center;">
                                <p style="color: rgba(255, 255, 255, 0.8); font-size: 12px; margin: 0; font-weight: 300;">
                                    © ' . date('Y') . ' Opiña Law Office. All rights reserved. | Professional Legal Services
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

function send_password_email($to, $name, $password, $user_type) {
    $mail = new PHPMailer(true);
    try {
        // Enable SMTP debugging for troubleshooting
        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            $mail->SMTPDebug = 2; // Enable verbose debug output
        }
        
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Set timeout values to prevent hanging
        $mail->Timeout = 30; // 30 seconds timeout
        $mail->SMTPKeepAlive = true;
        
        // Set the envelope sender (Return-Path) to match the From address
        if (defined('MAIL_FROM')) {
            $mail->Sender = MAIL_FROM;
        }
        
        // Add Reply-To to improve trust and deliverability
        if (defined('MAIL_FROM') && defined('MAIL_FROM_NAME')) {
            $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        }
        
        // Use UTF-8 and provide a plain-text alternative
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Account Created - Opiña Law Office ' . ucfirst($user_type) . ' Account';
        $mail->Body = getPasswordEmailTemplate($name, $to, $password, $user_type);
        $mail->AltBody = "Welcome to Opiña Law Office!\n\nYour account has been created as a $user_type.\n\nEmail: $to\nTemporary Password: $password\n\nPlease log in and change your password immediately.\n\nBest regards,\nOpiña Law Office Team";
        
        // Send the email
        $result = $mail->send();
        
        // Disable debug output after successful sending to prevent header issues
        if ($result) {
            $mail->SMTPDebug = 0;
            // Log successful email sending
            error_log("Password email sent successfully to: $to for user: $name ($user_type)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        // Disable debug output on error as well
        $mail->SMTPDebug = 0;
        
        // Log detailed error information
        $error_msg = "Mailer Error (Password Email): " . $mail->ErrorInfo;
        $error_msg .= "\nTo: $to";
        $error_msg .= "\nUser: $name ($user_type)";
        $error_msg .= "\nException: " . $e->getMessage();
        error_log($error_msg);
        
        // For debugging: you can temporarily uncomment this line
        // error_log("Full PHPMailer error: " . print_r($e, true));
        
        return false;
    }
}
?>
