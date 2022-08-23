<?php

/**
 * Encrypt raw text using AES-128-CBC encryption algorithm
 * Decrypt back encrypted data to readable format
 * @copyright Ekene Ezeasor (c) 2021
 * @author Ekene Ezeasor ezeasorekene@unizik.edu.ng
 * @package CIRMSCryptor
 */

 namespace Security\Encryption;

 class AESCryptor
 {

   /**
    * Encrypt raw text using a custom key or random key
    * @param string $raw_text The raw text to encrypt
    * @param string $key The key to encrypt the raw text. Leave empty to use a random key
    * @return string $ciphertext The encrypted text
    */
   public static function encrypt($raw_text,$key=null)
   {
     empty($key) ? $key = openssl_random_pseudo_bytes() : $key = $key;
     $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
     $iv = openssl_random_pseudo_bytes($ivlen);
     $ciphertext_raw = openssl_encrypt($raw_text, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
     $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
     $ciphertext = base64_encode( $iv.$hmac.$ciphertext_raw );
     return $ciphertext;
   }

   /**
    * Decrypt encrypted text using a custom key or default key
    * @param string $encrypted_text The encrypted text to decrypt
    * @param string $key The key to encrypt the raw text. Leave empty to use a random key
    * @return string $original_plaintext The decrypted plain text
    */
   public static function decrypt($encrypted_text, $key=null)
   {
     empty($key) ? $key = null : $key = $key;
     $c = base64_decode($encrypted_text);
     $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
     $iv = substr($c, 0, $ivlen);
     $hmac = substr($c, $ivlen, $sha2len=32);
     $ciphertext_raw = substr($c, $ivlen+$sha2len);
     $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
     $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
     if (hash_equals($hmac, $calcmac))// timing attack safe comparison
     {
       return trim($original_plaintext."\n");
     } else {
       return false;
     }
   }

 }
