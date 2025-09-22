<?php
$dbuser="root";
$dbpass="";
$host="localhost";
$db="OnlineCarBooking";
$mysqli=new mysqli($host,$dbuser, $dbpass, $db);

if (!defined('APP_ROOT_URL')) {
  // ✅ Set this to the URL where index.php lives
  define('APP_ROOT_URL', 'http://localhost/flt-web/clean-flt/');
}
?>