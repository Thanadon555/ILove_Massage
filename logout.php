<?php
// PHP Version Check - Must be first
require_once 'config/php_version_check.php';

session_start();
session_destroy();
header('Location: index.php');
exit();
?>