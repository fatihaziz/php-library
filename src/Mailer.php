<?php

/**
 *
 * Mailer Helper
 * Easy Gmail SMTP Send Mail
 *
 * Author: Fatih Aziz
 * date: 26 Jan 2020
 * last update: 26 Jan 2020
 * repo: https://github.com/fatih-aziz/php-plugins
 * licence: GNU General Public License v3.0
 */

require_once __DIR__.'/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    protected $mail;
    protected $smtp;
    
    private static $instance;

    public static function _()
    {
      if ( is_null( self::$instance ) )
      {
        self::$instance = new self();
      }
      return self::$instance;
    }
    
    function __construct()
    {
        $this->mail = new PHPMailer(true);
        
        //Server settings
        // Send using SMTP
        $this->mail->isSMTP();
        // Set the SMTP server to send through
        $this->mail->Host       = 'smtp.gmail.com';
        // Enable SMTP authentication
        $this->mail->SMTPAuth   = true;
        // SMTP username
        $this->mail->Username   = 'yourmail@email.com';
        // Enable SMTP authentication
        $this->mail->SenderName = 'yourmail@email.com';
        // SMTP password
        $this->mail->Password   = 'yourpass';
        // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // Set SMTP Server Port
        $this->mail->Port       = 587;                 
        // Return value? yes
        $this->mail->HandleResult = true;
    }
    
    public function sendMail($from="",$to,$subject,$body,$headers=[])
    {
        try{
            $mail = $this->mail;
            //Recipients
            $mail->setFrom($from?:$this->mail->Username, '');
            // Add a recipient
            $mail->addAddress($to);

            // Content
            // Set email format to HTML
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            $mail->send();
            if($this->mail->HandleResult)
                return $mail;
            echo 'Message has been sent';
        }
        catch(Exception $e)
        {
            if($this->mail->HandleResult)
                return $e;
            echo "Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}";
        }
    }
}
