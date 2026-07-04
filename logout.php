<?php
// Section for logging the customer out
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit;
