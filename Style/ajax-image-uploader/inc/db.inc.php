<?php

$username = "hezejiayuan";
$password = "23101988a";
$hostname = "localhost";	
$database = "tuoguancj";

mysql_connect($hostname, $username, $password) or die(mysql_error());
mysql_select_db($database) or die(mysql_error()); 

?>