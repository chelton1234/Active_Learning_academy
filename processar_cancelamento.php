<?php
/**
 * processar_cancelamento.php
 * Processa cancelamentos de aulas APENAS para ALUNOS
 * Versão completa com notificações integradas
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// ========== VERIFICAR AUTENTICAÇÃO ==========
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado. Faça login primeiro.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// ========== VERIFICAR SE É ALUNO ==========
$is_aluno = false;
if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'aluno') {
    $is_aluno = true;
} elseif (isset($_SESSION['aluno']) && $_SESSION['aluno'] === true) {
    $is_aluno = true;
} elseif (isset($_SESSION['usuario_tipo']) && strtolower($_SESSION['usuario_tipo']) === 'aluno') {
    $is_aluno = true;
}

if (!$is_aluno) {
    error_log("❌ Acesso negado - Sessão atual: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas alunos podem cancelar aulas.']);
    exit;
}

$usuario_nome = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Aluno';

// ========== CONEXÃO COM O BANCO ==========
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
    exit;
}

// ========== INCLUIR FUNÇÕES DE NOTIFICAÇÕES ==========
require_once 'notificacoes.php';

// ========== OBTER DADOS DA REQUISIÇÃO ==========
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

if (!isset($input['acao']) || $input['acao'] !== 'cancelar') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

$aula_id = isset($input['aula_id']) ? (int)$input['aula_id'] : null;
$motivo = $input['motivo'] ?? '';

if (!$aula_id) {
    echo json_encode(['success' => false, 'message' => 'ID da aula não fornecido.']);
    exit;
}

if (empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Informe o motivo do cancelamento.']);
    exit;
}

$conn->begin_transaction();

try {
    // Buscar dados da aula e também o professor associado ao aluno (via fichas)
    $sql = "SELECT a.id, a.aluno_id, a.data_hora, a.status, a.professor_id, 
                   f.id as ficha_id, f.nome as aluno_nome, f.professor_atribuido
            FROM agendamentos_aulas a
            JOIN fichas f ON f.id = a.aluno_id
            WHERE a.id = ? AND f.usuario_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $aula_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $aula = $result->fetch_assoc();
    $stmt->close();

    error_log("🔍 Dados da aula: " . print_r($aula, true));

    if (!$aula) {
        throw new Exception("Aula não encontrada.");
    }

    if ($aula['status'] !== 'agendado') {
        $status_msg = $aula['status'] === 'realizado' ? 'realizada' : 'cancelada';
        throw new Exception("Esta aula já foi $status_msg.");
    }

    $data_aula = new DateTime($aula['data_hora']);
    $agora = new DateTime();
    if ($data_aula <= $agora) {
        throw new Exception("Não é possível cancelar uma aula que já passou.");
    }

    $ficha_id = $aula['aluno_id'];
    $data_formatada = $data_aula->format('d/m/Y H:i');

    $observacao = "❌ Cancelada pelo aluno ($usuario_nome). Motivo: $motivo. Data original: $data_formatada";

    $sql_update = "UPDATE agendamentos_aulas SET 
                   status = 'cancelado_aluno', 
                   observacoes_professor = CONCAT(observacoes_professor, '\n', ?)
                   WHERE id = ? AND status = 'agendado'";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("si", $observacao, $aula_id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        throw new Exception("Não foi possível cancelar a aula.");
    }
    $stmt->close();

    // ===== NOTIFICAR O PROFESSOR =====
    $professor_id = null;
    $notificacao_criada = false;

    // Primeira tentativa: usar professor_id da própria aula
    if (!empty($aula['professor_id'])) {
        $professor_id = $aula['professor_id'];
    }

    // Segunda tentativa: buscar pelo nome do professor atribuído na ficha
    if (!$professor_id && !empty($aula['professor_atribuido'])) {
        $stmt_prof = $conn->prepare("SELECT id FROM usuarios WHERE nome = ? AND (tipo = 'professor' OR tipo = 'docente')");
        $stmt_prof->bind_param("s", $aula['professor_atribuido']);
        $stmt_prof->execute();
        $res = $stmt_prof->get_result();
        if ($row = $res->fetch_assoc()) {
            $professor_id = $row['id'];
        }
        $stmt_prof->close();
    }

    if ($professor_id) {
        $titulo = "Aula cancelada pelo aluno";
        $mensagem_notificacao = "O aluno $usuario_nome cancelou a aula do dia $data_formatada. Motivo: $motivo";
        $link = "dashboard_professor.php?mes=" . $data_aula->format('m') . "&ano=" . $data_aula->format('Y');
        $notificacao_criada = criarNotificacao($conn, $professor_id, 'professor', $titulo, $mensagem_notificacao, $link);
        error_log($notificacao_criada ? "✅ Notificação criada para professor ID $professor_id" : "❌ Falha ao criar notificação");
    } else {
        error_log("⚠️ Não foi possível encontrar o professor para notificação. professor_atribuido: " . ($aula['professor_atribuido'] ?? 'null'));
    }

    if (function_exists('consumirAula')) {
        @consumirAula($conn, $ficha_id, $aula_id, 'aluno');
    }

    $conn->commit();

    $mensagem = $notificacao_criada 
        ? 'Aula cancelada com sucesso! O professor será notificado.'
        : 'Aula cancelada com sucesso! (Não foi possível notificar o professor)';

    echo json_encode([
        'success' => true,
        'message' => $mensagem,
        'status' => 'cancelado_aluno',
        'notificacao_enviada' => $notificacao_criada
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("❌ ERRO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>