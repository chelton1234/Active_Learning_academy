<?php
// detalhes_pedido.php - Retorna JSON com os dados de um pedido público
session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin'])) {
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['erro' => 'Erro de conexão']);
    exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}
$result = $conn->query("SELECT * FROM pedidos_explicadores WHERE id = $id");
if ($result->num_rows == 0) {
    echo json_encode(['erro' => 'Pedido não encontrado']);
    exit;
}
echo json_encode($result->fetch_assoc());
?>