<?php
namespace IPMS\Utils;

class EmailNotifier {
    private $smtpConfig;
    
    public function __construct() {
        $this->smtpConfig = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-specific-password',
            'from' => 'noreply@ipmsystem.com',
            'from_name' => 'IPM System'
        ];
    }
    
    private function sendEmail($to, $subject, $body) {
        try {
            // Create PHPMailer instance
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['username'];
            $mail->Password = $this->smtpConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpConfig['port'];
            
            // Recipients
            $mail->setFrom($this->smtpConfig['from'], $this->smtpConfig['from_name']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendWelcomeEmail($email, $username, $role) {
        $subject = "Welcome to IPM System - Account Created";
        $body = "<h2>Welcome to IPM System!</h2>
                 <p>Dear $username,</p>
                 <p>Your account has been successfully created in the IPM System.</p>
                 <p>Your role: $role</p>
                 <p>Please keep your credentials safe and secure.</p>
                 <p>Best regards,<br>IPM System Team</p>";
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    public function sendDeletionNotification($email) {
        $subject = "IPM System - Account Status Update";
        $body = "<h2>Account Status Update</h2>
                 <p>Your account in the IPM System has been deactivated.</p>
                 <p>If you believe this was a mistake, please contact our support team.</p>
                 <p>Best regards,<br>IPM System Team</p>";
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    public function sendPasswordResetEmail($email, $resetLink) {
        $subject = "IPM System - Password Reset";
        $body = "<h2>Password Reset Request</h2>
                 <p>A password reset request has been initiated for your account.</p>
                 <p>Click the link below to reset your password:</p>
                 <p><a href="$resetLink">$resetLink</a></p>
                 <p>If you did not request this change, please ignore this email.</p>
                 <p>Best regards,<br>IPM System Team</p>";
        
        return $this->sendEmail($email, $subject, $body);
    }
}
