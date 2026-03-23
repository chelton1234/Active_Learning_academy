<?php
// config.php - APENAS ISSO!

$host = 'localhost';
$dbname = 'sistema_login';
$user = 'root';
$pass = '';

try {
    // Conexão usando PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>