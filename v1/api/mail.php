<?php
if ( ! defined( 'APIPATH' ) ) {
        exit; // Exit if accessed directly
    }

/** __How to Use__
  * When you send an email with clone_recipients as true,
  * the API will ignore any cc or bcc set and send to all emails
  * specified under recipients uniquely as individual recipients.
  * However, with the clone_recipients set to false, the API will function as normal,
  * by sending to all receipients, including all cc and bcc
  */

//Set required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

use PHPMailer\Mail;

switch ($_SERVER['REQUEST_METHOD']) {
  case 'POST':
    $data = json_decode(file_get_contents("php://input"));
    $site_id = API\API::getheader('site-id');

    // Check if there is a recipient
    if (!empty($data->recipient->email)) {

      isset($data->debug) ? $debug = $data->debug : $debug = false;

      $mail = new Mail($data->smtp->user,$data->smtp->pass,$data->smtp->host,$data->smtp->port,$debug,$data->response_email);

      //Set Subject, HTML and Plain contents if available
      isset($data->subject) ? $mail->setSubject($data->subject) : $mail->setSubject("***No Subject***");
      isset($data->message->html) ? $mail->setHTMLMessage($data->message->html) : NULL;
      isset($data->message->plain) ? $mail->setPlainMessage($data->message->plain) : NULL;

      // Set sender details
      $mail->setMailFrom($data->sender->email,$data->sender->name);

      // Attach files if available
      if (!empty($data->attachment)) {
        foreach ($data->attachment as $attachment) {
          if (!empty($attachment->attachment)) {
            !$attachment->remote ? $attachment->remote = FALSE : $attachment->remote = TRUE;
            $mail->uploadAttachment($attachment->attachment,$attachment->filename,$attachment->remote);
          }
        }
      }

      // Assign replyto if available
      if (!empty($data->replyto)) {
        foreach ($data->replyto as $replyto) {
          if (!empty($replyto->email)) {
            $mail->setReplyto($replyto->email,$replyto->name);
          }
        }
      }

      if($mail->verifySMTP())
        $smtp_pass = true;
      else
        $smtp_pass = false;

      // Check if multiple cloned recipients is enabled
      if ($data->clone_recipients === true && count($data->recipient) > 1) {
        // Add recipient section only
        $mails_sent = 0;
        foreach ($data->recipient as $queued_email) {
    			$mail2 = clone $mail;
          $mail2->checkMX($queued_email->email);
    			$mail2->setRecipient($queued_email->email,$queued_email->name);
          $details = count($data->recipient) ." uniquely combined email(s) supplied.";
          if ($mail2->sendMail($details)){
            $mails_sent++;
          }
    		}

      } else {
        // Add recipient
        if (count($data->recipient) > 1) {
          foreach ($data->recipient as $recipient) {
            $mail->setRecipient($recipient->email,$recipient->name);
          }
        } else {
          $mail->setRecipient($data->recipient->email,$data->recipient->name);
        }

        // Assign cc if available
        if (!empty($data->cc)) {
          foreach ($data->cc as $cc) {
            if (!empty($cc->email)) {
              $mail->setCc($cc->email,$cc->name);
            }
          }
        }

        // Assign bcc if available
        if (!empty($data->bcc)) {
          foreach ($data->bcc as $bcc) {
            if (!empty($bcc->email)) {
              $mail->setBcc($bcc->email,$bcc->name);
            }
          }
        }
      }

      // Verify if SMTP connection is successfull
      if ($smtp_pass) {
        if ($data->clone_recipients === true && count($data->recipient) > 1) {
          if($mails_sent >= 1){
            $mail_sent = true;
          }
        } else {
          $countcc = empty($data->cc) ? 0 : count($data->cc);
          $countbcc = empty($data->bcc) ? 0 : count($data->bcc);
          $details = count($data->recipient)+$countcc+$countbcc." email(s) supplied including CCs and BCCs.";
          if($mail->sendMail($details)){
            $mail_sent = true;
          }
        }
        if($mail_sent){
          $options = array (
            'service' => '/mail',
            'endpoint' => $_SERVER['QUERY_STRING'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'status' => 'success',
          );
          API\API::logapiaccess($site_id,$options);
          header('HTTP/1.0 200 Ok');
          echo json_encode(array("code" => 200,"status" => "success","message" => "Mail sent successfully"));
          exit;
        } else {
          $options = array (
            'service' => '/mail',
            'endpoint' => $_SERVER['QUERY_STRING'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'status' => 'failed',
          );
          API\API::logapiaccess($site_id,$options);
          header('HTTP/1.0 424 Failed Dependency');
          echo json_encode(array("code" => 424,"status" => "failed","message" => "Mail could not be sent"));
          exit;
        }
      } else {
        $options = array (
          'service' => '/mail',
          'endpoint' => $_SERVER['QUERY_STRING'],
          'method' => $_SERVER['REQUEST_METHOD'],
          'status' => 'failed',
        );
        API\API::logapiaccess($site_id,$options);
        header('HTTP/1.0 424 Failed Dependency');
        echo json_encode(array("code" => 424,"status" => "failed","message" => "SMTP verification failed"));
        exit;
      }
    } else {
      $options = array (
        'service' => '/mail',
        'endpoint' => $_SERVER['QUERY_STRING'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'status' => 'failed',
      );
      API\API::logapiaccess($contractorID,$options);
      header('HTTP/1.0 412 Precondition Failed');
      echo json_encode(array("code" => 412,"status" => "failed","message" => "Missing the user email"));
      exit;
    }
  break;

  default:
    $options = array (
      'service' => '/mail',
      'endpoint' => $_SERVER['QUERY_STRING'],
      'method' => $_SERVER['REQUEST_METHOD'],
      'status' => 'failed',
    );
    API\API::logapiaccess($contractorID,$options);
    // Throw an error
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(array("code" => 405,"status" => "failed","message" => "Method Not Allowed"));
    exit;
  break;
}
