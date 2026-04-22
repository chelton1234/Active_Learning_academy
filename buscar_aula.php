<?php
// buscar_aula.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!isset($_GET['aula_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID da aula não fornecido']);
    exit;
}

$aula_id = intval($_GET['aula_id']);
if ($aula_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

// Buscar dados da aula e professor
$sql = "SELECT a.*, u.nome as professor_nome 
        FROM agendamentos_aulas a
        LEFT JOIN usuarios u ON u.id = a.professor_id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro na preparação: ' . $conn->error]);
    exit;
}
$stmt->bind_param("i", $aula_id);
$stmt->execute();
$result = $stmt->get_result();
$aula = $result->fetch_assoc();
$stmt->close();

if (!$aula) {
    echo json_encode(['success' => false, 'message' => 'Aula não encontrada']);
    exit;
}

// Buscar itens da aula (disciplinas)
$sql_itens = "SELECT * FROM aula_itens WHERE aula_id = ?";
$stmt_itens = $conn->prepare($sql_itens);
if ($stmt_itens) {
    $stmt_itens->bind_param("i", $aula_id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    $itens = $result_itens->fetch_all(MYSQLI_ASSOC);
    $stmt_itens->close();
} else {
    $itens = [];
}

$aula['itens'] = $itens;

// Formatar a data/hora para evitar problemas de formatação no JS
$aula['data_hora'] = date('Y-m-d H:i:s', strtotime($aula['data_hora']));

echo json_encode(['success' => true, 'data' => $aula]);

$conn->close();
?>