<?php
echo "NGROK TEST SUCCESS!";
echo "<br>Server Time: " . date('Y-m-d H:i:s');
echo "<br>PHP Version: " . phpversion();
echo "<br>Document Root: " . $_SERVER['DOCUMENT_ROOT'];
echo "<br>Script Name: " . $_SERVER['SCRIPT_NAME'];
?>