<?php
/**
 * notificacoes.php
 * Sistema completo de notificações
 * Inclui funções e endpoints para gerenciar notificações
 */

// ============================================
// FUNÇÕES PRINCIPAIS
// ============================================

/**
 * Cria uma nova notificação
 * @param mysqli $conn Conexão com o banco
 * @param int $usuario_id ID do usuário que receberá a notificação
 * @param string $tipo_usuario 'aluno' ou 'professor'
 * @param string $titulo Título da notificação
 * @param string $mensagem Conteúdo da notificação
 * @param string|null $link Link opcional para redirecionamento
 * @return bool|int False se falhou, ID da notificação se sucesso
 */
function criarNotificacao($conn, $usuario_id, $tipo_usuario, $titulo, $mensagem, $link = null) {
    // Verificar se o usuário existe
    if (!$usuario_id || $usuario_id <= 0) {
        error_log("❌ criarNotificacao: ID de usuário inválido: " . $usuario_id);
        return false;
    }
    
    $sql = "INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, link, data_criacao, lida) 
            VALUES (?, ?, ?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("❌ Erro ao preparar notificação: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issss", $usuario_id, $tipo_usuario, $titulo, $mensagem, $link);
    $resultado = $stmt->execute();
    
    if ($resultado) {
        $id = $stmt->insert_id;
        $stmt->close();
        error_log("✅ Notificação criada com sucesso. ID: $id para usuário $usuario_id");
        return $id;
    } else {
        error_log("❌ Erro ao executar notificação: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Marca uma notificação como lida
 * @param mysqli $conn Conexão com o banco
 * @param int $notificacao_id ID da notificação
 * @param int $usuario_id ID do usuário (para segurança)
 * @return bool True se sucesso
 */
function marcarNotificacaoComoLida($conn, $notificacao_id, $usuario_id) {
    $sql = "UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("❌ Erro ao preparar marcar lida: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ii", $notificacao_id, $usuario_id);
    $resultado = $stmt->execute();
    $stmt->close();
    return $resultado;
}

/**
 * Marca todas as notificações de um usuário como lidas
 * @param mysqli $conn Conexão com o banco
 * @param int $usuario_id ID do usuário
 * @param string $tipo_usuario Tipo do usuário
 * @return bool True se sucesso
 */
function marcarTodasNotificacoesComoLidas($conn, $usuario_id, $tipo_usuario) {
    $sql = "UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND tipo_usuario = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("❌ Erro ao preparar marcar todas: " . $conn->error);
        return false;
    }
    $stmt->bind_param("is", $usuario_id, $tipo_usuario);
    $resultado = $stmt->execute();
    $stmt->close();
    return $resultado;
}

/**
 * Busca notificações de um usuário
 * @param mysqli $conn Conexão com o banco
 * @param int $usuario_id ID do usuário
 * @param string $tipo_usuario Tipo do usuário
 * @param bool $apenas_nao_lidas Se true, retorna apenas não lidas
 * @param int $limite Número máximo de notificações
 * @return array Array de notificações
 */
function buscarNotificacoes($conn, $usuario_id, $tipo_usuario, $apenas_nao_lidas = false, $limite = 20) {
    $sql = "SELECT * FROM notificacoes 
            WHERE usuario_id = ? AND tipo_usuario = ?";
    if ($apenas_nao_lidas) {
        $sql .= " AND lida = 0";
    }
    $sql .= " ORDER BY data_criacao DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("❌ Erro ao preparar buscar: " . $conn->error);
        return [];
    }
    $stmt->bind_param("isi", $usuario_id, $tipo_usuario, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $notificacoes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notificacoes;
}

/**
 * Conta notificações não lidas de um usuário
 * @param mysqli $conn Conexão com o banco
 * @param int $usuario_id ID do usuário
 * @param string $tipo_usuario Tipo do usuário
 * @return int Número de notificações não lidas
 */
function contarNotificacoesNaoLidas($conn, $usuario_id, $tipo_usuario) {
    $sql = "SELECT COUNT(*) as total FROM notificacoes 
            WHERE usuario_id = ? AND tipo_usuario = ? AND lida = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("❌ Erro ao preparar contar: " . $conn->error);
        return 0;
    }
    $stmt->bind_param("is", $usuario_id, $tipo_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] ?? 0;
}

/**
 * Cria notificação para o professor quando aluno cancela aula
 * @param mysqli $conn Conexão com o banco
 * @param int $aula_id ID da aula cancelada
 * @param string $aluno_nome Nome do aluno
 * @param string $motivo Motivo do cancelamento
 * @return bool True se sucesso
 */
function notificarProfessorSobreCancelamento($conn, $aula_id, $aluno_nome, $motivo) {
    // Buscar dados da aula para obter professor_id
    $sql = "SELECT a.professor_id, a.data_hora, a.aluno_id 
            FROM agendamentos_aulas a 
            WHERE a.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $aula_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $aula = $result->fetch_assoc();
    $stmt->close();
    
    if (!$aula || !$aula['professor_id']) {
        error_log("❌ Não foi possível encontrar professor para a aula $aula_id");
        return false;
    }
    
    $professor_id = $aula['professor_id'];
    $data_formatada = date('d/m/Y H:i', strtotime($aula['data_hora']));
    
    $titulo = "Aula cancelada pelo aluno";
    $mensagem = "O aluno $aluno_nome cancelou a aula do dia $data_formatada. Motivo: $motivo";
    $link = "dashboard_professor.php?mes=" . date('m', strtotime($aula['data_hora'])) . 
            "&ano=" . date('Y', strtotime($aula['data_hora']));
    
    error_log("📢 Enviando notificação para professor ID: $professor_id");
    return criarNotificacao($conn, $professor_id, 'professor', $titulo, $mensagem, $link);
}

/**
 * Cria notificação para o aluno quando professor cancela aula
 * @param mysqli $conn Conexão com o banco
 * @param int $aula_id ID da aula cancelada
 * @param string $professor_nome Nome do professor
 * @param string $motivo Motivo do cancelamento
 * @return bool True se sucesso
 */
function notificarAlunoSobreCancelamento($conn, $aula_id, $professor_nome, $motivo) {
    // Buscar dados da aula para obter aluno_id
    $sql = "SELECT a.aluno_id, a.data_hora, f.usuario_id 
            FROM agendamentos_aulas a 
            JOIN fichas f ON f.id = a.aluno_id
            WHERE a.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $aula_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $aula = $result->fetch_assoc();
    $stmt->close();
    
    if (!$aula || !$aula['usuario_id']) {
        error_log("❌ Não foi possível encontrar aluno para a aula $aula_id");
        return false;
    }
    
    $aluno_usuario_id = $aula['usuario_id'];
    $data_formatada = date('d/m/Y H:i', strtotime($aula['data_hora']));
    
    $titulo = "Aula cancelada pelo professor";
    $mensagem = "O professor $professor_nome cancelou a aula do dia $data_formatada. Motivo: $motivo. Um crédito de reposição foi gerado.";
    $link = "dashboard.php?mes=" . date('m', strtotime($aula['data_hora'])) . 
            "&ano=" . date('Y', strtotime($aula['data_hora']));
    
    error_log("📢 Enviando notificação para aluno ID: $aluno_usuario_id");
    return criarNotificacao($conn, $aluno_usuario_id, 'aluno', $titulo, $mensagem, $link);
}

/**
 * Verificar se a tabela de notificações existe e criá-la se necessário
 * @param mysqli $conn Conexão com o banco
 * @return bool True se sucesso
 */
function verificarTabelaNotificacoes($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notificacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_usuario ENUM('aluno', 'professor', 'admin') NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        link VARCHAR(500),
        data_criacao DATETIME NOT NULL,
        lida TINYINT(1) DEFAULT 0,
        INDEX idx_usuario (usuario_id, tipo_usuario, lida),
        INDEX idx_data (data_criacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $resultado = $conn->query($sql);
    if ($resultado) {
        error_log("✅ Tabela notificacoes verificada/criada com sucesso");
    } else {
        error_log("❌ Erro ao criar tabela notificacoes: " . $conn->error);
    }
    return $resultado;
}

// ============================================
// ENDPOINTS PARA REQUISIÇÕES AJAX
// ============================================

// Se este ficheiro for chamado diretamente com parâmetros
if (basename($_SERVER['PHP_SELF']) == 'notificacoes.php' && isset($_GET['acao'])) {
    session_start();
    
    // Conexão com o banco
    $conn = new mysqli("localhost", "root", "", "sistema_login");
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erro de conexão']);
        exit;
    }
    
    // Configurar charset
    $conn->set_charset("utf8mb4");
    
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    $usuario_id = $_SESSION['usuario_id'];
    
    // Determinar tipo de usuário
    $tipo_usuario = 'aluno'; // padrão
    if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'professor') {
        $tipo_usuario = 'professor';
    } elseif (isset($_SESSION['professor']) && $_SESSION['professor'] === true) {
        $tipo_usuario = 'professor';
    } elseif (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
        $tipo_usuario = 'admin';
    } elseif (isset($_SESSION['usuario_tipo'])) {
        $tipo_usuario = strtolower($_SESSION['usuario_tipo']);
    }
    
    $acao = $_GET['acao'];
    
    switch ($acao) {
        case 'listar':
            $apenas_nao_lidas = isset($_GET['nao_lidas']) && $_GET['nao_lidas'] == 1;
            $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 20;
            
            $notificacoes = buscarNotificacoes($conn, $usuario_id, $tipo_usuario, $apenas_nao_lidas, $limite);
            $nao_lidas = contarNotificacoesNaoLidas($conn, $usuario_id, $tipo_usuario);
            
            echo json_encode([
                'success' => true,
                'notificacoes' => $notificacoes,
                'nao_lidas' => $nao_lidas
            ]);
            break;
            
        case 'marcar_lida':
            $input = json_decode(file_get_contents('php://input'), true);
            $notificacao_id = $input['id'] ?? 0;
            
            if ($notificacao_id) {
                $resultado = marcarNotificacaoComoLida($conn, $notificacao_id, $usuario_id);
                echo json_encode(['success' => $resultado]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            }
            break;
            
        case 'marcar_todas_lidas':
            $resultado = marcarTodasNotificacoesComoLidas($conn, $usuario_id, $tipo_usuario);
            echo json_encode(['success' => $resultado]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
    
    $conn->close();
    exit;
}

// Verificar tabela ao incluir o arquivo (se houver conexão disponível)
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    verificarTabelaNotificacoes($conn);
}
?>