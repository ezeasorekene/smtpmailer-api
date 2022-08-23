<?php
//CIRS Autoload function
spl_autoload_register(function($className) {
  $file = 'src\\' .$className . '.php';
  $file = str_replace('\\',DIRECTORY_SEPARATOR,$file);
  // echo $file;
  if (file_exists($file)) {
    include $file;
  }
});


if ( !defined('APIPATH') )
    define('APIPATH', dirname(__FILE__) . '/');