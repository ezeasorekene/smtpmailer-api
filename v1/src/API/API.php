<?php

/**
 *
 */

namespace API;

class API
{

  public static function auth()
  {

  }

  /**
   * Get the name from the URL
   * @param int $key The required key index
   * @return string $name Returns the value of the key
   */
  public static function logapiaccess($site_id,array $options)
  {
    $db = new \DB;
    $dbconnect = $db->db_connect();
    $site_id = self::sanitize($site_id);
    $options['method'] ? $method = self::sanitize($options['method']) : $method = 'undefined';
    $options['service'] ? $service = self::sanitize($options['service']) : $service = 'undefined';
    $options['endpoint'] ? $endpoint = self::sanitize($options['endpoint']) : $endpoint = 'undefined';
    $options['status'] ? $status = self::sanitize($options['status']) : $status = 'undefined';
    $ip = $_SERVER['HTTP_REFERER'];
    $sql = "INSERT INTO api_usage (site_id,endpoint,method,status,ip,service) VALUES ('$site_id','$endpoint','$method','$status','$ip','$service')";
    $result = $dbconnect->query($sql); //or die($dbconnect->error);
    if ($dbconnect->affected_rows > 0) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Get the value of a specific header in a call
   * @param string $header_key The expected header key
   * @return mixed $header_value Returns the value of the header key
   */
   public static function getheader(string $header_key)
  {
    if(!function_exists('apache_request_headers')) {
      $headers = self::nginx_request_headers();
    } else {
      $headers = apache_request_headers();
    }
    $headers = array_change_key_case($headers,CASE_LOWER);
    $header_value = $headers[$header_key];
    if (isset($header_key)) {
      return $header_value;
    } else {
      return false;
    }
  }

  /**
   * Get the name from the URL
   * @param int $key The required key index
   * @return string $name Returns the value of the key
   */
  public static function getroutekey(int $key=0)
  {
    $query = $_SERVER['QUERY_STRING'];
    $query = trim(strip_tags($query),"/");
    $method = explode('/',$query);
    $name = $method[$key];
    return $name;
  }

  /**
   * Check if the route exists
   * @param string $route The required route
   * @return bool $name Returns true if the route exists
   */
  public static function isRoute($route)
  {
    if (file_exists(APIPATH."api/{$route}.php")) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Get the route if it exists
   * @param string $route The required route
   */
  public static function getRoute($route)
  {
    if (self::isRoute($route)) {
      include(APIPATH."api/{$route}.php");
    } else {
      header('HTTP/1.0 404 Resource Route Not Found');
      echo json_encode(array("code" => 404,"status" => "failed","message" => "Resource route not found"));
    }
  }

  /**
   * Count the number of paths from the URL
   * @return int $count Returns the path total count
   */
  public static function countroutekey()
  {
    $query = $_SERVER['QUERY_STRING'];
    $method = explode('/',$query);
    $count = count($method);
    return $count;
  }

  /**
    * Sanitize strings for use with the database
    * @param  string $data The string you intend to sanitize
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

  private static function nginx_request_headers()
  {
    $arh = array();
    $rx_http = '/\AHTTP_/';
    foreach($_SERVER as $key => $val) {
      if( preg_match($rx_http, $key) ) {
        $arh_key = preg_replace($rx_http, '', $key);
        $rx_matches = array();
        // do some nasty string manipulations to restore the original letter case
        // this should work in most cases
        $rx_matches = explode('_', strtolower($arh_key));
        if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
          foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
          $arh_key = implode('-', $rx_matches);
        }
        $arh[$arh_key] = $val;
      }
    }
    if(isset($_SERVER['CONTENT_TYPE'])) $arh['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    if(isset($_SERVER['CONTENT_LENGTH'])) $arh['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    return( $arh );
  }

}
