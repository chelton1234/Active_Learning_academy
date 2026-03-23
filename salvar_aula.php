<?php
// salvar_aula.php
// Ativar exibição de erros (apenas DEV)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validação de sessão / tipo
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo_usuario'] !== 'professor') {
    header("Location: login.php");
    exit;
}

$professor_id = (int) $_SESSION['usuario_id'];

// Validar POST
if (!isset($_POST['ficha_id'], $_POST['data_hora'], $_POST['conteudo'])) {
    die("Dados incompletos.");
}

$ficha_id = intval($_POST['ficha_id']); // fichas.id
$data_hora_raw = trim($_POST['data_hora']);
$conteudo = trim($_POST['conteudo']);

if ($ficha_id <= 0 || $data_hora_raw === '' || $conteudo === '') {
    die("Todos os campos são obrigatórios.");
}

// Normalizar data/hora (datetime-local => "YYYY-MM-DDTHH:MM")
if (strpos($data_hora_raw, 'T') !== false) {
    $data_hora_raw = str_replace('T', ' ', $data_hora_raw);
}
try {
    $dt = new DateTime($data_hora_raw);
    $data_hora = $dt->format('Y-m-d H:i:s');
} catch (Exception $e) {
    die("Formato de data/hora inválido.");
}

// Conexão
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Verificar se a ficha existe
$sql_check = "SELECT id, usuario_id FROM fichas WHERE id = ? LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    die("Erro preparação verificação: " . $conn->error);
}
$stmt_check->bind_param("i", $ficha_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
if ($res_check->num_rows === 0) {
    $stmt_check->close();
    $conn->close();
    die("Ficha não encontrada.");
}
$ficha = $res_check->fetch_assoc();
$stmt_check->close();

// Inserir (usando ficha_id direto)
$sql = "INSERT INTO agendamentos_aulas (aluno_id, professor_id, data_hora, conteudo, status)
        VALUES (?, ?, ?, ?, 'agendado')";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro preparação insert: " . $conn->error);
}
$stmt->bind_param("iiss", $ficha_id, $professor_id, $data_hora, $conteudo);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: dashboard_professor.php?success=1");
    exit;
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    echo "Erro ao agendar a aula: " . htmlspecialchars($err);
}
?>
