<?php
/**
  * Security Policy
**/
namespace Security;

use DBConnect;
use CIRMS;

class SecurityPolicies {
  public $user_id;
  public $ipaddress;
  public $type;
  public $iplockinterval; //Default is 30 days
  public $loginattemptcheckinterval; //Default is 3 minutes
  public $loginattempts; //Default is 3 trials
  protected $dbconnect;

  public function __construct()
  {
    $db = new DBConnect;
    $this->dbconnect = $db->db_connect();
    $this->iplockinterval = 30;
    $this->loginattemptcheckinterval = 3;
    $this->loginattempts = 3;
  }

  /*
   * PURPOSE : Sets the user id
   *  PARAMS : $user_id
   * RETURNS : $this->user_id
   */
  public function setUser($user_id)
  {
    $this->user_id = CIRMS::sanitize($user_id);
  }

  /*
   * PURPOSE : Sets the user ip address
   *  PARAMS : $ipaddress
   * RETURNS : $this->ipaddress
   */
  public function setIP($ipaddress)
  {
    $this->ipaddress = CIRMS::sanitize($ipaddress);
  }

  /*
   * PURPOSE : Sets the Policy Details
   *  PARAMS : $iplockinterval, $loginattemptcheckinterval, $loginattempts
   * RETURNS : $this->iplockinterval,
               $this->loginattemptcheckinterval
               $this->loginattempts
   */
  public function setPolicyDetails($iplockinterval=null,$loginattemptcheckinterval=null,$loginattempts=null)
  {
    if (isset($iplockinterval)) {
      $this->iplockinterval = $iplockinterval;
    }
    if (isset($loginattemptcheckinterval)) {
      $this->loginattemptcheckinterval = $loginattemptcheckinterval;
    }
    if (!empty($loginattempts)) {
      $this->loginattempts = $loginattempts;
    }
  }

  /*
  * PURPOSE : Apply Login Policy Conditions
  *  PARAMS : $iplockinterval, $loginattemptcheckinterval, $loginattempts
  * RETURNS : $this->iplockinterval,
              $this->loginattemptcheckinterval
              $this->loginattempts
    NOTES : 1. When a valid user enters a wrong password more than n times in x minutes
               Action - Blacklist the user until validated by a password reset through the sso password reset
            2. When an invalid user tries to login more than n times
               Action - Blacklist the IP until x number of days
  */
  public function applyBlacklistPolicy()
  {
    //Blacklist users that enter wrong password more than stipulated times
    if ($this->countValidLoginFailed($this->user_id)===TRUE) {
      if ($this->updateBlacklistTable('user','Your account has been blacklisted due to mulitple incorrect passwords. Use the button below to reset your password.')) {
        $GLOBALS['success_error'] = '
          <div class="m-portlet__padding-x">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Your account has been blacklisted due to multiple incorrect passwords.
                Reset your password through our password reset portal or contact administrator.
            </div>
          </div>
        ';
        galaxy_log_activities('User Blacklist', 'Blaklisted account due to multiple incorrect passwords.',$this->user_id);
      }
    } elseif ($this->countInValidUserLogin($this->ipaddress)===TRUE) {
      if ($this->updateBlacklistTable('ip','Multiple incorrect username')) {
        $GLOBALS['success_error'] = '
          <div class="m-portlet__padding-x">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Your IP has been blacklisted for security reasons.
            </div>
          </div>
        ';
        galaxy_log_activities('IP Blacklist', "Blaklisted IP - {$this->ipaddress} due to multiple invalid usernames.");
      }
    }
  }

  /*
   * PURPOSE : Checks the Blacklisted Users and IPs
   *  PARAMS : $iplockinterval, $loginattemptcheckinterval, $loginattempts
   * RETURNS : USER
               IP
               FALSE
   */
  public function checkBlacklistPolicy($type)
  {
    $user_query = "SELECT user_id FROM iems_blacklisted_users WHERE user_id='$this->user_id'";
    $ip_query = "SELECT ip_address FROM iems_blacklisted_ips WHERE ip_address='$this->ipaddress'";
    $user_result = $this->dbconnect->query($user_query);
    $ip_result = $this->dbconnect->query($ip_query);
    if ($type=="user") {
      if ($user_result->num_rows > 0) {
        return "USER";
        $db->db_close();
      }
    } elseif ($type=="ip") {
      if ($ip_result->num_rows > 0) {
        return "IP";
        $db->db_close();
      }
    } else {
      return FALSE;
    }
  }

  private function countValidLoginFailed($user_id)
  {
    $limit = $this->loginattempts + 2;
    $timecheck = $this->convertNumberToTimeHms($this->loginattemptcheckinterval);
    $query = "SELECT al_alid FROM iems_activitylog
              WHERE al_action='Login Failed' AND user_id = '$this->user_id'
              AND (TIMEDIFF(NOW(),timing) < '$timecheck')
              ORDER BY al_alid DESC LIMIT $limit";
    $run_query = $this->dbconnect->query($query);
    if ($run_query->num_rows >= $this->loginattempts+1) {
      return TRUE;
      $db->db_close();
    } else {
      return FALSE;
    }
  }

  private function countInValidUserLogin($ipaddress)
  {
    $limit = $this->loginattempts + 2;
    $timecheck = $this->convertNumberToTimeHms($this->loginattemptcheckinterval);
    $query = "SELECT al_alid FROM iems_activitylog
              WHERE user_id='Anonymous' AND al_action='Login Failed' AND al_ip = '$this->ipaddress'
              AND (TIMEDIFF(NOW(),timing) < '$timecheck')
              ORDER BY al_alid DESC LIMIT $limit";
    $run_query = $this->dbconnect->query($query);
    if ($run_query->num_rows >= $this->loginattempts+1) {
      return TRUE;
      $db->db_close();
    } else {
      return FALSE;
    }
  }

  private function convertNumberToTimeHms($number)
  {
    if (is_int($number)) {
      $number = $number * 60;
      $number = gmdate("H:i:s",$number);
      return $number;
    } else {
      return FALSE;
    }
  }

  private function updateBlacklistTable($theType,$reason)
  {
    switch ($theType) {
      case 'user':
        $from = date('Y-m-d');
        $to = $this->addDate($from,30);
        $query = "INSERT INTO iems_blacklisted_users
                  (user_id,banned_from,banned_to,banned_reason)
                  VALUES ('$this->user_id','$from','$to','$reason')";
        $result = $this->dbconnect->query($query);
        if ($result) {
          if (iems_getUser('user_email',$this->user_id)) {
            galaxy_email_sender(iems_getUser('user_email',$this->user_id),'Account Blacklisted!',$reason,array('hi'=>$this->user_id,'button'=>'Reset My Password','buttonurl'=>'https://cirs.unizik.edu.ng/sso-password-reset'));
          }
          return TRUE;
        } else {
          return FALSE;
        }
        break;

      case 'ip':
        $from = date('Y-m-d');
        $to = $this->addDate($from,30);
        $query = "INSERT INTO iems_blacklisted_ips
                  (ip_address,banned_from,banned_to,banned_reason)
                  VALUES ('$this->ipaddress','$from','$to','$reason')";
        $result = $this->dbconnect->query($query);
        if ($result) {
          return TRUE;
          $db->db_close();
        } else {
          return FALSE;
        }
        break;

      default:
        return FALSE;
        break;
    }
  }

  private function addDate($date,$daystoadd)
  {
    $date = date_create($date);
    $date = date_add($date,date_interval_create_from_date_string("{$daystoadd} days"));
    return date_format($date,"Y-m-d");
  }

}
