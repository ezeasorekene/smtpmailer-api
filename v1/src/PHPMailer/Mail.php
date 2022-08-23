<?php

/**
 * Title: Send Email
 * Description: This class manages users including registration,
 * login, profile update, etc.
 * File: Users.php
 *
 *
 * @author: Ekene Ezeasor
 * Last Modified: 21st January, 2022
 * @version 1.21.6
 *
 * @copyright 2021 Ekene Ezeasor
 *
 *
 */

 namespace PHPMailer;

class Mail extends PHPMailer
{
  public $Username;
  public $Password;
  public $Host;
  public $Port;
  public $AltBody;
  public $Body;
  public $From;
  public $FromName;
  public $Subject;
  private $ResponseEmail = "dominicpeterz@gmail.com";
  private $Invalid_Emails=[];

  function __construct($smtp_user,$smtp_pass,$smtp_host,$smtp_port,$debug=false,$response_email="",$exceptions=null)
  {
    parent::__construct($exceptions);

    // Initialize values
    $this->Username = $smtp_user;
    $this->Password = $smtp_pass;
    $this->Host = $smtp_host;
    $this->Port = $smtp_port;
    $this->SMTPSecure = "ssl";
    $this->CharSet = "UTF-8";

    if( !empty($response_email) && filter_var( $response_email, FILTER_VALIDATE_EMAIL ) ) {
      $this->setResponseEmail($response_email);
    }

    // Enable or Disable SMTP debugging
    $this->debugSMTP($debug);

    //Set PHPMailer to use SMTP.
    $this->isSMTP();
    //Set this to true if SMTP host requires authentication to send email
    $this->SMTPAuth = true;

  }

  /**
   * Destructor.
   */
  public function __destruct()
  {
    parent::__destruct();
  }


  /**
   * Send the email
   * @return bool Returns true if the email was sent out successfully
   */
  public function sendMail($details="")
  {
    // Send the email
    if (!$this->send()){
      return false;
    } else {
      $this->setResponse($this->ResponseEmail,$details);
      return true;
    }
  }

  /**
   * Set the sender email address
   * @param string $email Email address
   * @param string $name Optional name
   * @return null
   */
  public function setMailFrom( $email, $name )
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $email = $email;
    } else {
      $this->Invalid_Emails[] = $email." (Sender Email)";
      $email = $this->Username;
    }
    $this->From = $email;
    $this->FromName = $name;
  }

  /**
   * Set the recipient email address
   * @param string $email Email address
   * @param string $name Optional name
   * @return null
   */
  public function setRecipient( $email, $name = '' )
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $this->addAddress($email, $name = '');
    } else {
      $this->Invalid_Emails[] = $email." (Recipient Email)";
    }
  }

  /**
   * Set the carbon copy email address
   * @param string $email Email address
   * @param string $name Optional name
   * @return null
   */
  public function setCc( $email, $name = '' )
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $this->addCC($email, $name = '');
    } else {
      $this->Invalid_Emails[] = $email." (CC Email)";
    }
  }

  /**
   * Set the blank carbon copy email address
   * @param string $email Email address
   * @param string $name Optional name
   * @return null
   */
  public function setBcc( $email, $name = '' )
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $this->addBCC($email, $name = '');
    } else {
      $this->Invalid_Emails[] = $email." (BCC Email)";
    }
  }

  /**
   * Set the repty-to copy email address
   * @param string $email Email address
   * @param string $name Optional name
   * @return null
   */
  public function setReplyto( $email, $name = '' )
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $this->addReplyTo($email, $name = '');
    } else {
      $this->Invalid_Emails[] = $email." (ReplyTo Email)";
    }
  }

  /**
   * SCheck if an email address has a valid MX Records
   * @param string $email Email address
   * @return bool Returns false if it contains a vaild MX record
   */
  public function checkMX($email, int $return=0)
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($email);
    if($return===1){
      if(!empty($url) && checkdnsrr($url, "MX")){
        return true;
      } else{
        return false;
      }
    } else {
      if(!empty($url) && !checkdnsrr($url, "MX")){
        $this->Invalid_Emails[] = $email;
      }
    }
  }

  /**
   * Set the response recipient email address
   * @param string $email Email address
   * @return null
   */
  public function setResponseEmail($response_email)
  {
    // Check if the email is a valid email with valid MX records
    $url = self::getDomain($response_email);
    if(!empty($url) && checkdnsrr($url, "MX")){
      $this->ResponseEmail = $response_email;
    } else {
      $this->Invalid_Emails[] = $email." (Response Email)";
    }
    return $this->ResponseEmail;
  }

  /**
   * Set the plain non-html email body
   * @param string $emailplain The non-html email body
   * @return null
   */
  public function setPlainMessage( $emailplain )
  {
    $this->AltBody = $emailplain;
  }

  /**
   * Set the html email body
   * @param string $emailhtml The html email body
   * @return null
   */
  public function setHTMLMessage( $emailhtml )
  {
    $this->isHTML();
    $this->Body = $emailhtml;
  }

  /**
   * Set the response body email to the sender
   * @param string $sender The sender of the email
   * @param string $message The message to the sender
   * @return null
   */
  public function setResponse( $sender,$details="")
  {
    $mail2 = new \PHPMailer\Mail($this->Username,$this->Password,$this->Host,$this->Port,false);
    $mail2->setRecipient($sender);
    $mail2->setSubject("Email Summary");
    $response = "Hello,<br><br>This is an automated email to give you the summary of your email with subject <b>$this->Subject</b> sent by <b>$this->From</b>";
    isset($details)?$response .="<br><br>".$details:$response .="";
    // $response .= $details;
    $response .= "<br><br>";
    if (!empty($this->Invalid_Emails)) {
      $x = 1;
      $response .= "<b><u>INVALID EMAILS:</u></b><br>";
      foreach ($this->Invalid_Emails as $Invalid_Emails) {
        $response .= "<br>".$x." ".$Invalid_Emails."<br>";
        $x++;
      }
    }
    $response .= "<br><br>Best regards";
    $mail2->setHTMLMessage($response);
    $mail2->setMailFrom($this->Username,"Mail Response");
    $mail2->send();
  }

  /**
   * Set the email body subject
   * @param string $subject The email subject
   * @return null
   */
  public function setSubject( $subject )
  {
    $this->Subject = $subject;
  }

  public function uploadAttachment( $attachment, $filename='', bool $remote = false )
  {
    if ($remote && isset($filename)) {
      $url = self::getDomain($attachment);
      if(filter_var("http://".$url, FILTER_VALIDATE_URL) && checkdnsrr($url, "A")){
        $attachment = file_get_contents($attachment);
      }
      self::addStringAttachment($attachment,$filename);
    } elseif (file_exists($attachment)) {
      isset($filename) ? $filename = $filename : $filename = '';
      self::addAttachment($attachment, $filename);
    }
  }

  public function verifySMTP()
  {
    return $this->smtpConnect();
  }

  private function debugSMTP(bool $debug = false)
  {
    if ($debug) {
      //Enable SMTP debugging.
      $this->SMTPDebug = 2;
    } else {
      //Disable SMTP debugging.
      $this->SMTPDebug = 0;
    }
  }

  public static function getDomain($url)
  {
    if( filter_var( $url, FILTER_VALIDATE_EMAIL ) ) {
      // split on @ and return last value of array (the domain)
      $domain = array_pop(explode('@', $url));
      // output domain
      return $domain;
    } else {
      $pieces = parse_url($url);
      $domain = isset($pieces['host']) ? $pieces['host'] : '';
      if(preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)){
        return $regs['domain'];
      }
    }
    return FALSE;
  }

  public static function getMailTemplate(array $data)
  {
    $template =
    '
    <html>
       <body style="background-color: #222533; padding: 20px; font-family: font-size: 14px; line-height: 1.43; font-family: &quot;Helvetica Neue&quot;, &quot;Segoe UI&quot;, Helvetica, Arial, sans-serif;">
          <div style="max-width: 600px; margin: 0px auto; background-color: #fff; box-shadow: 0px 20px 50px rgba(0,0,0,0.05);">
             <table style="width: 100%;">
                <tr>
                   <td style="background-color: #fff;"><img alt="" src="'.$data['logo'].'" style="width: 70px; padding: 20px"></td>
                   <td style="padding-left: 50px; text-align: right; padding-right: 20px;"><a href="'.$data['app_url'].'" style="color: #261D1D; text-decoration: underline; font-size: 14px; letter-spacing: 1px;">Sign In</a><a href="'.$data['app_url'].'/investment.invest" style="color: #7C2121; text-decoration: underline; font-size: 14px; margin-left: 20px; letter-spacing: 1px;">Invest Now</a></td>
                </tr>
             </table>
             <div style="padding: 60px 70px; border-top: 1px solid rgba(0,0,0,0.05);">
                <h1 style="margin-top: 0px;">Hi '.$data['hi'].',</h1>
                <div style="color: #636363; font-size: 14px;">
                   <p>'.$data['body'].'</p>
                </div>
                <h4 style="margin-bottom: 10px;">Need Help?</h4>
                <div style="color: #A5A5A5; font-size: 12px;">
                   <p>If you have any questions you can simply reply to this email or you can contact us at <a href="mailto:'.$data['support_email'].'" style="text-decoration: underline; color: #4B72FA;">'.$data['support_email'].'</a></p>
                </div>
                <br><br>
                <h3 style="margin-bottom: 10px;">'.$data['signature'].'</h3>
             </div>
             <div style="background-color: #F5F5F5; padding: 40px; text-align: center;">
                <div style="margin-bottom: 20px;"><a href="'.$data['domain'].'" style="text-decoration: underline; font-size: 14px; letter-spacing: 1px; margin: 0px 15px; color: #261D1D;">Visit our Website</a> | <a href="'.$data['domain'].'/plans.php" style="text-decoration: underline; font-size: 14px; letter-spacing: 1px; margin: 0px 15px; color: #261D1D;">Investment Plans</a></div>
                <div style="color: #A5A5A5; font-size: 12px; margin-bottom: 20px; padding: 0px 50px;">This e-mail, any attachments thereto and response string is intended solely for the attention and use of the addressee(s) named herein and may contain legally privileged and/or confidential information. In the event that you are not the intended recipient(s) of this e-mail and any attachments thereto, be notified that any dissemination, distribution or copying of this e-mail and any attachments thereto, is strictly prohibited. If you have received or otherwise encountered this e-mail in error, please immediately notify the sender and permanently delete the e-mail, any attachments and response string as well as any copy printout in connection therewith.</div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(0,0,0,0.05);">
                   <div style="color: #A5A5A5; font-size: 10px;">Copyright '.date('Y').' '.$data['site_name'].'. All rights reserved.</div>
                </div>
             </div>
          </div>
       </body>
    </html>
    ';
    return $template;
  }

}
