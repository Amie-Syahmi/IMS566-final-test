<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'crud_app';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireGuest() {
    if (isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}
?>