<?php

/**
 *
 */
use Security\Encryption\AESCryptor;

namespace API;

class APIAuth
{

  public $siteID;
  public $apiKey;
  protected $apiSecret;
  protected $dbconnect;

  function __construct($siteID,$apiKey)
  {
    $db = new \DB;
    $this->dbconnect = $db->db_connect();
    $this->setsiteID($siteID);
    $this->setAPIKey($apiKey);
    $decrypt_secret = \Security\Encryption\AESCryptor::decrypt($this->fetchapiUserData('apiSecret'),$this->siteID);
    $this->setAPISecret($decrypt_secret);
  }

  public function verifyToken($apiToken)
  {
    $apiToken = $apiToken;
    $decrypt_apikey = \Security\Encryption\AESCryptor::decrypt($this->fetchapiUserData('apiKey'),$this->siteID);
    $expected_hash = hash('SHA512',$this->siteID.$this->apiKey.$this->apiSecret);
    // Check if the expected hash equals submitted hash
    if (hash_equals($expected_hash,$apiToken) && $decrypt_apikey===$this->apiKey) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Set the API key. The key starts with nau_pk_
   * @param string $apiKey
   * @return null
   */
  private function setAPIKey($apiKey)
  {
    $this->apiKey = $apiKey;
  }

  /**
   * Set the API secret. The secret starts with nau_sk_
   * @param string $apiSecret
   * @return null
   */
  private function setAPISecret($apiSecret)
  {
    $this->apiSecret = $apiSecret;
  }

  /**
   * Set the Contractor ID
   * @param string $siteID
   * @return null
   */
  private function setsiteID($siteID)
  {
    $this->siteID = self::sanitize($siteID);
  }

  /**
    * Sanitize strings for use with the database
    * @param $data The string you intend to sanitize
    * @return string Returns the sanitized string
    */
  public static function sanitize($data)
  {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = addslashes($data);
    return $data;
  }

  protected function verifyapiUser($value)
  {
    $value = self::sanitize($value);
    $sql = "SELECT $value FROM api_users WHERE site_id='$this->siteID'";
    $result = $this->dbconnect->query($sql);
    if ($this->dbconnect->affected_rows > 0) {
      return true;
    } else {
      return false;
    }
    $this->dbconnect->db_close();
  }

  public function fetchapiUserData($value)
  {
    $value = self::sanitize($value);
    $sql = "SELECT $value FROM api_users WHERE site_id='$this->siteID'";
    $result = $this->dbconnect->query($sql) or die($this->dbconnect->error);
    $row = $result->fetch_array();
    if ($this->dbconnect->affected_rows > 0) {
      return $row[$value];
    } else {
      return false;
    }
    $this->dbconnect->db_close();
  }

}
