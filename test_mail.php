<?php
$to = "info@softedigi.com";
$subject = "Test Email";
$message = "This is a test email to verify that PHP mail() function is working.";
$headers = "From: test@example.com\r\n";

if(mail($to, $subject, $message, $headers)) {
    echo "Test email sent successfully";
} else {
    echo "Failed to send test email";
    error_log("Mail error: " . error_get_last()['message']);
}
?> 