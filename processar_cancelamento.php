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

// ========== VERIFICAR SE É ALUNO (VÁRIAS POSSIBILIDADES) ==========
$is_aluno = false;

// Verificar diferentes formas de identificar aluno na sessão
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
    echo json_encode(['success' => false, 'message' => 'Dados inválidos. Certifique-se de enviar JSON.']);
    exit;
}

// ========== VERIFICAR AÇÃO ==========
if (!isset($input['acao']) || $input['acao'] !== 'cancelar') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

// ========== OBTER DADOS DO CANCELAMENTO ==========
$aula_id = isset($input['aula_id']) ? (int)$input['aula_id'] : null;
$motivo = $input['motivo'] ?? '';

if (!$aula_id) {
    echo json_encode(['success' => false, 'message' => 'ID da aula não fornecido.']);
    exit;
}

if (empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, informe o motivo do cancelamento.']);
    exit;
}

// ========== PROCESSAR CANCELAMENTO ==========
$conn->begin_transaction();

try {
    // Buscar dados completos da aula e verificar se pertence ao aluno
    $sql = "SELECT a.id, a.aluno_id, a.data_hora, a.status, a.professor_id, 
                   f.id as ficha_id, f.nome as aluno_nome
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
        throw new Exception("Aula não encontrada ou não pertence a este aluno.");
    }

    // Verificar se a aula pode ser cancelada
    if ($aula['status'] !== 'agendado') {
        $status_msg = $aula['status'] === 'realizado' ? 'realizada' : 'cancelada';
        throw new Exception("Esta aula já foi $status_msg e não pode ser cancelada.");
    }

    // Verificar se a aula é futura
    $data_aula = new DateTime($aula['data_hora']);
    $agora = new DateTime();
    
    if ($data_aula <= $agora) {
        throw new Exception("Não é possível cancelar uma aula que já passou. Entre em contato com o professor.");
    }

    $ficha_id = $aula['aluno_id'];
    $data_formatada = $data_aula->format('d/m/Y H:i');

    // Construir observação
    $observacao = "❌ Cancelada pelo aluno ($usuario_nome). Motivo: $motivo. Data original: $data_formatada";

    // ATUALIZAR STATUS DA AULA
    $sql_update = "UPDATE agendamentos_aulas SET 
                   status = 'cancelado_aluno', 
                   observacoes_professor = CONCAT(observacoes_professor, '\n', ?)
                   WHERE id = ? AND status = 'agendado'";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("si", $observacao, $aula_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    error_log("🟡 DURANTE: Linhas afetadas: $affected");

    if ($affected === 0) {
        throw new Exception("Não foi possível cancelar a aula. Ela pode já ter sido modificada.");
    }

    // ===== CRIAR NOTIFICAÇÃO PARA O PROFESSOR =====
    $professor_id = null;
    $notificacao_criada = false;

    // Tentar obter o ID do professor
    if (isset($aula['professor_id']) && $aula['professor_id'] > 0) {
        $professor_id = $aula['professor_id'];
        error_log("📢 Professor ID encontrado na aula: " . $professor_id);
    } else {
        // Buscar professor da tabela agendamentos_aulas
        $sql_prof = "SELECT professor_id FROM agendamentos_aulas WHERE id = ?";
        $stmt_prof = $conn->prepare($sql_prof);
        $stmt_prof->bind_param("i", $aula_id);
        $stmt_prof->execute();
        $result_prof = $stmt_prof->get_result();
        $prof = $result_prof->fetch_assoc();
        $stmt_prof->close();
        
        if ($prof && isset($prof['professor_id']) && $prof['professor_id'] > 0) {
            $professor_id = $prof['professor_id'];
            error_log("📢 Professor ID encontrado na tabela: " . $professor_id);
        }
    }

    // Se encontrou o professor, criar notificação
    if ($professor_id) {
        $titulo = "Aula cancelada pelo aluno";
        $mensagem_notificacao = "O aluno $usuario_nome cancelou a aula do dia $data_formatada. Motivo: $motivo";
        $link = "dashboard_professor.php?mes=" . $data_aula->format('m') . "&ano=" . $data_aula->format('Y');
        
        $notificacao_criada = criarNotificacao($conn, $professor_id, 'professor', $titulo, $mensagem_notificacao, $link);
        
        if ($notificacao_criada) {
            error_log("✅ Notificação criada para o professor ID: " . $professor_id);
        } else {
            error_log("❌ Falha ao criar notificação para o professor ID: " . $professor_id);
        }
    } else {
        error_log("⚠️ Não foi possível encontrar o ID do professor para a notificação");
    }

    // Processar consumo (para aluno, não consome aula)
    if (function_exists('consumirAula')) {
        @consumirAula($conn, $ficha_id, $aula_id, 'aluno');
    }

    $conn->commit();

    // Mensagem de sucesso
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