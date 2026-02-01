<?php 
session_start(); 
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') { 
    header('Location: /'); 
    exit(); 
} 
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; 
header('Location: /enhanced-admin-form-detail.php' . $qs); 
exit(); 
?>
