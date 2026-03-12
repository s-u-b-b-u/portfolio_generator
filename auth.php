<?php
/**
 * Authentication helper script
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

/**
 * Register a new user
 * Returns true on success, or an error message string on failure
 */
function signup($name, $email, $password) {
    global $pdo;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation (Duplicate entry)
            return "This email is already registered.";
        }
        return "Database error: " . $e->getMessage();
    }
}

/**
 * Log in a user
 */
function login($email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        return true;
    }
    return false;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
?>
