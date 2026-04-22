<?php
/**
 * processar_cancelamento_professor.php
 * Processa cancelamentos de aulas APENAS para PROFESSORES (corrigido)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nome_professor = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Professor';

// Verificar se é professor
$is_professor = false;
if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'professor') $is_professor = true;
elseif (isset($_SESSION['professor']) && $_SESSION['professor'] === true) $is_professor = true;
elseif (isset($_SESSION['usuario_tipo']) && strtolower($_SESSION['usuario_tipo']) === 'professor') $is_professor = true;
elseif (isset($_SESSION['usuario_tipo']) && strtolower($_SESSION['usuario_tipo']) === 'docente') $is_professor = true;

if (!$is_professor) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas professores podem cancelar aulas.']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão.']);
    exit;
}
$conn->set_charset("utf8mb4");

require_once 'notificacoes.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['acao_aula']) || $input['acao_aula'] !== 'cancelar_antecipado') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

$aula_id = isset($input['aula_id']) ? (int)$input['aula_id'] : null;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : '';

if (!$aula_id || empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$conn->begin_transaction();

try {
    // Buscar aula verificando pelo nome do professor (campo professor_atribuido na ficha)
    $sql = "SELECT a.id, a.aluno_id, a.data_hora, a.status, a.professor_id,
                   f.id as ficha_id, f.nome as aluno_nome, f.usuario_id as aluno_usuario_id
            FROM agendamentos_aulas a
            JOIN fichas f ON f.id = a.aluno_id
            WHERE a.id = ? AND f.professor_atribuido = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erro na preparação.");
    $stmt->bind_param("is", $aula_id, $nome_professor);
    $stmt->execute();
    $result = $stmt->get_result();
    $aula = $result->fetch_assoc();
    $stmt->close();

    if (!$aula) {
        throw new Exception("Aula não encontrada ou não pertence a este professor.");
    }

    if ($aula['status'] !== 'agendado') {
        throw new Exception("Esta aula já foi " . ($aula['status'] === 'realizado' ? 'realizada' : 'cancelada'));
    }

    $data_aula = new DateTime($aula['data_hora']);
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    if ((clone $data_aula)->setTime(0, 0, 0) < $hoje) {
        throw new Exception("Não é possível cancelar uma aula que já passou.");
    }

    $ficha_id = $aula['aluno_id'];
    $aluno_usuario_id = $aula['aluno_usuario_id'];
    $data_formatada = $data_aula->format('d/m/Y H:i');

    $observacao = "🔴 Cancelada pelo professor ($nome_professor) com antecedência. Motivo: $motivo. Data original: $data_formatada";

    // Atualizar status
    $sql_update = "UPDATE agendamentos_aulas SET status = 'cancelado_professor', observacoes_professor = CONCAT(observacoes_professor, '\n', ?) WHERE id = ? AND status = 'agendado'";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("si", $observacao, $aula_id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        throw new Exception("Não foi possível cancelar a aula.");
    }
    $stmt->close();

    // Notificar aluno
    $notificacao_criada = false;
    if ($aluno_usuario_id) {
        $titulo = "Aula cancelada pelo professor";
        $mensagem = "O professor $nome_professor cancelou a aula do dia $data_formatada. Motivo: $motivo. Um crédito de reposição foi gerado.";
        $link = "dashboard.php?mes=" . $data_aula->format('m') . "&ano=" . $data_aula->format('Y');
        $notificacao_criada = criarNotificacao($conn, $aluno_usuario_id, 'aluno', $titulo, $mensagem, $link);
        error_log("Notificação para aluno ID $aluno_usuario_id: " . ($notificacao_criada ? 'OK' : 'FALHOU'));
    }

    if (function_exists('consumirAula')) {
        @consumirAula($conn, $ficha_id, $aula_id, 'professor');
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Aula cancelada com sucesso! O aluno será notificado.',
        'notificacao_enviada' => $notificacao_criada
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("ERRO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>