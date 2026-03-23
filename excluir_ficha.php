<?php
// Ativar erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Verifica se o usuário é admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    die("Acesso negado - não autenticado como admin.");
}

// Verifica se o ID da ficha foi enviado
if (!isset($_POST['ficha_id'])) {
    http_response_code(400);
    die("ID da ficha não fornecido.");
}

$ficha_id = (int)$_POST['ficha_id'];

// Conexão BD
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    http_response_code(500);
    die("Erro de conexão: " . $conn->connect_error);
}

// Primeiro excluímos os pagamentos relacionados à ficha
$stmtPag = $conn->prepare("DELETE FROM pagamentos WHERE ficha_id = ?");
if (!$stmtPag) {
    http_response_code(500);
    die("Erro ao preparar exclusão de pagamentos: " . $conn->error);
}
$stmtPag->bind_param("i", $ficha_id);
$stmtPag->execute();
$stmtPag->close();

// Agora excluímos a ficha
$stmt = $conn->prepare("DELETE FROM fichas WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    die("Erro ao preparar exclusão da ficha: " . $conn->error);
}
$stmt->bind_param("i", $ficha_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "ok"; // esperado pelo AJAX
    } else {
        echo "Nenhuma ficha encontrada com esse ID ($ficha_id).";
    }
} else {
    http_response_code(500);
    echo "Erro ao excluir ficha: " . $stmt->error;
}

$stmt->close();
$conn->close();
