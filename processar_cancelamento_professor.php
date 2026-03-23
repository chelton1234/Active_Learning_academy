<?php
/**
 * processar_cancelamento_professor.php
 * Processa cancelamentos de aulas APENAS para PROFESSORES
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
$nome_professor = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Professor';

// ========== VERIFICAR SE É PROFESSOR ==========
$is_professor = false;

if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'professor') {
    $is_professor = true;
} elseif (isset($_SESSION['professor']) && $_SESSION['professor'] === true) {
    $is_professor = true;
} elseif (isset($_SESSION['usuario_tipo']) && strtolower($_SESSION['usuario_tipo']) === 'professor') {
    $is_professor = true;
} elseif (isset($_SESSION['usuario_tipo']) && strtolower($_SESSION['usuario_tipo']) === 'docente') {
    $is_professor = true;
}

if (!$is_professor) {
    error_log("❌ Acesso negado - Sessão: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas professores podem cancelar aulas.']);
    exit;
}

// ========== CONEXÃO COM O BANCO ==========
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados.']);
    exit;
}

$conn->set_charset("utf8mb4");

// ========== INCLUIR FUNÇÕES DE NOTIFICAÇÕES ==========
require_once 'notificacoes.php';

// ========== OBTER DADOS DA REQUISIÇÃO ==========
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos. Certifique-se de enviar JSON.']);
    exit;
}

error_log("📥 Dados recebidos: " . print_r($input, true));

// ========== VERIFICAR AÇÃO ==========
if (!isset($input['acao_aula']) || $input['acao_aula'] !== 'cancelar_antecipado') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
}

// ========== OBTER DADOS DO CANCELAMENTO ==========
$aula_id = isset($input['aula_id']) ? (int)$input['aula_id'] : null;
$motivo = isset($input['motivo']) ? trim($input['motivo']) : '';

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
    // Buscar dados completos da aula e verificar se pertence ao professor
    $sql = "SELECT a.id, a.aluno_id, a.data_hora, a.status, a.professor_id,
                   f.id as ficha_id, f.nome as aluno_nome, f.usuario_id as aluno_usuario_id
            FROM agendamentos_aulas a
            JOIN fichas f ON f.id = a.aluno_id
            WHERE a.id = ? AND a.professor_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro na preparação da consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $aula_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $aula = $result->fetch_assoc();
    $stmt->close();

    if (!$aula) {
        throw new Exception("Aula não encontrada ou não pertence a este professor.");
    }

    error_log("📋 Dados da aula encontrada: " . print_r($aula, true));

    // Verificar se a aula pode ser cancelada
    if ($aula['status'] !== 'agendado') {
        $status_msg = $aula['status'] === 'realizado' ? 'realizada' : 'cancelada';
        throw new Exception("Esta aula já foi $status_msg e não pode ser cancelada.");
    }

    // Verificar se a aula é futura (pode cancelar com antecedência)
    $data_aula = new DateTime($aula['data_hora']);
    $agora = new DateTime();
    
    // Professores podem cancelar até o momento da aula (incluindo hoje antes do horário)
    $hoje = new DateTime();
    $hoje->setTime(0, 0, 0);
    
    $data_aula_inicio = clone $data_aula;
    $data_aula_inicio->setTime(0, 0, 0);
    
    if ($data_aula_inicio < $hoje) {
        throw new Exception("Não é possível cancelar uma aula que já passou.");
    }

    $ficha_id = $aula['aluno_id'];
    $aluno_usuario_id = $aula['aluno_usuario_id'];
    $data_formatada = $data_aula->format('d/m/Y H:i');

    // Construir observação
    $observacao = "🔴 Cancelada pelo professor ($nome_professor) com antecedência. Motivo: $motivo. Data original: $data_formatada";

    // Buscar observações atuais
    $sql_obs = "SELECT observacoes_professor FROM agendamentos_aulas WHERE id = ?";
    $stmt_obs = $conn->prepare($sql_obs);
    $stmt_obs->bind_param("i", $aula_id);
    $stmt_obs->execute();
    $result_obs = $stmt_obs->get_result();
    $obs_atual = $result_obs->fetch_assoc();
    $stmt_obs->close();
    
    $nova_observacao = $obs_atual['observacoes_professor'] 
        ? $obs_atual['observacoes_professor'] . "\n" . $observacao 
        : $observacao;

    // ATUALIZAR STATUS DA AULA
    $sql_update = "UPDATE agendamentos_aulas SET 
                   status = 'cancelado_professor', 
                   observacoes_professor = ?
                   WHERE id = ? AND status = 'agendado'";
    $stmt = $conn->prepare($sql_update);
    if (!$stmt) {
        throw new Exception("Erro na preparação do update: " . $conn->error);
    }
    
    $stmt->bind_param("si", $nova_observacao, $aula_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    error_log("🟡 Linhas afetadas pelo update: $affected");

    if ($affected === 0) {
        throw new Exception("Não foi possível cancelar a aula. Ela pode já ter sido modificada.");
    }

    // Verificar se o status foi realmente atualizado
    $sql_check = "SELECT status FROM agendamentos_aulas WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $aula_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $status_atual = $result_check->fetch_assoc();
    $stmt_check->close();
    
    error_log("🟢 Status após update: " . $status_atual['status']);

    // ===== CRIAR NOTIFICAÇÃO PARA O ALUNO =====
    $notificacao_criada = false;
    
    if ($aluno_usuario_id) {
        $titulo = "Aula cancelada pelo professor";
        $mensagem_notificacao = "O professor $nome_professor cancelou a aula do dia $data_formatada. Motivo: $motivo. Um crédito de reposição foi gerado.";
        $link = "dashboard.php?mes=" . $data_aula->format('m') . "&ano=" . $data_aula->format('Y');
        
        $notificacao_criada = criarNotificacao($conn, $aluno_usuario_id, 'aluno', $titulo, $mensagem_notificacao, $link);
        
        error_log("📢 Notificação para aluno ID $aluno_usuario_id: " . ($notificacao_criada ? 'Sucesso' : 'Falha'));
    } else {
        error_log("⚠️ Aluno sem usuario_id: " . print_r($aula, true));
    }

    // Processar consumo (para professor, gera crédito de reposição)
    if (function_exists('consumirAula')) {
        @consumirAula($conn, $ficha_id, $aula_id, 'professor');
        error_log("📊 Função consumirAula executada para aula $aula_id");
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Aula cancelada com sucesso! O aluno será notificado.',
        'notificacao_enviada' => $notificacao_criada
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("❌ ERRO no cancelamento do professor: " . $e->getMessage());
    error_log("❌ Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>