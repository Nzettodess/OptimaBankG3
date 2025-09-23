<?php
session_start();

// ✅ Clear all session variables
session_unset();

// ✅ Destroy the session
session_destroy();

// ✅ Redirect safely back to login page
// Adjust folder name if your project folder is different
header("Location: /OptimaBankG3-PHP-clean-main/login.php");
exit();
