<?php
// 0️⃣ Ativar exibição de erros (somente em desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 1️⃣ Validação de login - CORRIGIDA para aceitar 'professor' OU 'docente'
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar se é professor (aceita professor OU docente)
$tipo_usuario = strtolower($_SESSION['usuario_tipo'] ?? '');
$is_professor = ($tipo_usuario === 'professor' || $tipo_usuario === 'docente');

if (!$is_professor) {
    // Se não for professor, redireciona para o dashboard apropriado
    if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
        header("Location: dashboard_admin.php");
    } elseif (isset($_SESSION['aluno']) && $_SESSION['aluno'] === true) {
        header("Location: dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nome_professor = $_SESSION['usuario_nome'] ?? 'Professor';

// 2️⃣ Conexão com o banco
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// 3️⃣ INCLUIR FUNÇÕES DE PACOTE E NOTIFICAÇÕES
require_once 'funcoes_pacotes.php';
require_once 'notificacoes.php';

// 4️⃣ VERIFICAR E CRIAR TABELA aula_itens SE NÃO EXISTIR
$sql_check_table = "SHOW TABLES LIKE 'aula_itens'";
$result_check = $conn->query($sql_check_table);
if ($result_check->num_rows == 0) {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS aula_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aula_id INT NOT NULL,
        disciplina VARCHAR(100) NOT NULL,
        conteudo_abordado TEXT,
        dificuldades_identificadas TEXT,
        observacoes_professor TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (aula_id) REFERENCES agendamentos_aulas(id) ON DELETE CASCADE
    )";
    $conn->query($sql_create_table);
    $conn->query("CREATE INDEX idx_aula_id ON aula_itens(aula_id)");
}

// 5️⃣ PROCESSAR CANCELAMENTOS AUTOMÁTICOS (aulas passadas não registradas)
$sql_aulas_passadas = "SELECT a.id, a.aluno_id, a.data_hora, f.nome as aluno_nome
                       FROM agendamentos_aulas a
                       JOIN fichas f ON f.id = a.aluno_id
                       WHERE a.status = 'agendado' 
                       AND a.data_hora < NOW()
                       AND DATE(a.data_hora) <= CURDATE()";
$result_passadas = $conn->query($sql_aulas_passadas);

if ($result_passadas->num_rows > 0) {
    while ($aula_passada = $result_passadas->fetch_assoc()) {
        $aula_id = $aula_passada['id'];
        $ficha_id = $aula_passada['aluno_id'];
        $aluno_nome = $aula_passada['aluno_nome'];
        $data_aula = date('d/m/Y H:i', strtotime($aula_passada['data_hora']));
        
        $sql_update = "UPDATE agendamentos_aulas SET 
                       status = 'cancelado_aluno', 
                       observacoes_professor = CONCAT('❌ Aula cancelada automaticamente por falta de registro. Data: ', ?)
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $data_aula, $aula_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        @consumirAula($conn, $ficha_id, $aula_id, 'aluno');
    }
}

// 6️⃣ Obter mês e ano para navegação
$mes_atual = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_atual = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Validar mês e ano
if ($mes_atual < 1) $mes_atual = 1;
if ($mes_atual > 12) $mes_atual = 12;
if ($ano_atual < 2020) $ano_atual = 2020;
if ($ano_atual > 2030) $ano_atual = 2030;

// Mês anterior e próximo
$mes_anterior = $mes_atual - 1;
$ano_anterior = $ano_atual;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $ano_anterior--;
}

$mes_proximo = $mes_atual + 1;
$ano_proximo = $ano_atual;
if ($mes_proximo > 12) {
    $mes_proximo = 1;
    $ano_proximo++;
}

// Nomes dos meses em português
$meses_portugues = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// 7️⃣ Listar fichas/alunos atribuídos a este professor
$sql = "SELECT * FROM fichas WHERE professor_atribuido = ? ORDER BY data_submissao DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nome_professor);
$stmt->execute();
$result_alunos = $stmt->get_result();

$alunos_com_horarios = [];

if ($result_alunos->num_rows > 0) {
    $result_alunos->data_seek(0);
    while($aluno = $result_alunos->fetch_assoc()) {
        $ficha_id = $aluno['id'];
        
        $sql_horarios = "SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ? ORDER BY 
                        FIELD(dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')";
        $stmt_horarios = $conn->prepare($sql_horarios);
        $stmt_horarios->bind_param("i", $ficha_id);
        $stmt_horarios->execute();
        $result_horarios = $stmt_horarios->get_result();
        
        $horarios_array = [];
        while ($horario = $result_horarios->fetch_assoc()) {
            $horarios_array[] = $horario;
        }
        $stmt_horarios->close();
        
        $aluno['horarios'] = $horarios_array;
        
        // Buscar aulas do mês selecionado para este aluno
        $sql_aulas_mes = "SELECT DATE(data_hora) as data, status, id, TIME(data_hora) as hora, observacoes_professor
                          FROM agendamentos_aulas 
                          WHERE aluno_id = ? 
                          AND MONTH(data_hora) = ? 
                          AND YEAR(data_hora) = ?";
        $stmt_aulas_mes = $conn->prepare($sql_aulas_mes);
        $stmt_aulas_mes->bind_param("iii", $ficha_id, $mes_atual, $ano_atual);
        $stmt_aulas_mes->execute();
        $result_aulas_mes = $stmt_aulas_mes->get_result();
        
        $aulas_mes = [];
        while ($aula = $result_aulas_mes->fetch_assoc()) {
            $aulas_mes[$aula['data']] = [
                'status' => $aula['status'],
                'id' => $aula['id'],
                'hora' => substr($aula['hora'], 0, 5),
                'observacoes' => $aula['observacoes_professor'] ?? ''
            ];
            
            error_log("📚 Aula carregada: Data {$aula['data']}, ID {$aula['id']}, Status {$aula['status']}");
        }
        $stmt_aulas_mes->close();
        
        $aluno['aulas_mes'] = $aulas_mes;
        $alunos_com_horarios[] = $aluno;
    }
}

// 8️⃣ Buscar aulas do professor COM SEUS ITENS
$sql_aulas = "SELECT a.id, a.data_hora, a.status, a.observacoes_professor,
                     f.id AS ficha_id, f.nome AS aluno_nome,
                     f.classe AS aluno_classe, f.escola AS aluno_escola,
                     (SELECT COUNT(*) FROM aula_itens WHERE aula_id = a.id) as total_disciplinas
              FROM agendamentos_aulas a
              JOIN fichas f ON f.id = a.aluno_id
              WHERE a.professor_id = ?
              ORDER BY a.data_hora DESC";
$stmt_aulas = $conn->prepare($sql_aulas);
$stmt_aulas->bind_param("i", $usuario_id);
$stmt_aulas->execute();
$result_aulas = $stmt_aulas->get_result();

// 9️⃣ CONTAR AULAS CANCELADAS AUTOMATICAMENTE NO MÊS ATUAL
$sql_canceladas_auto = "SELECT COUNT(*) as total 
                        FROM agendamentos_aulas a
                        JOIN fichas f ON f.id = a.aluno_id
                        WHERE a.status = 'cancelado_aluno' 
                        AND a.observacoes_professor LIKE '%Cancelado automaticamente%'
                        AND MONTH(a.data_hora) = ? 
                        AND YEAR(a.data_hora) = ?
                        AND f.professor_atribuido = ?";
$stmt_canceladas = $conn->prepare($sql_canceladas_auto);
$stmt_canceladas->bind_param("iis", $mes_atual, $ano_atual, $nome_professor);
$stmt_canceladas->execute();
$result_canceladas = $stmt_canceladas->get_result();
$total_canceladas_auto = $result_canceladas->fetch_assoc()['total'] ?? 0;
$stmt_canceladas->close();

// 1️⃣0️⃣ DADOS DAS NOTIFICAÇÕES
$notificacoes_nao_lidas = contarNotificacoesNaoLidas($conn, $usuario_id, 'professor');
$notificacoes = buscarNotificacoes($conn, $usuario_id, 'professor', true, 10);

// 1️⃣1️⃣ Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && isset($input['acao_aula'])) {
        $acao = $input['acao_aula'];
        
        switch ($acao) {
            case 'registrar_multiplas':
                $ficha_id = $input['ficha_id'] ?? null;
                $data_hora = $input['data_hora'] ?? '';
                $disciplinas = $input['disciplinas'] ?? [];
                $observacoes_gerais = $input['observacoes_gerais'] ?? '';
                
                if (!$ficha_id || !$data_hora || empty($disciplinas)) {
                    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                    exit;
                }
                
                // Verificar se pode registrar
                $data_hora_obj = new DateTime($data_hora);
                $agora = new DateTime();
                $hoje = new DateTime();
                $hoje->setTime(0, 0, 0);
                
                $data_aula = new DateTime($data_hora);
                $data_aula->setTime(0, 0, 0);
                
                $hora_aula = $data_hora_obj->format('H:i');
                
                $pode_registrar = false;
                $mensagem_erro = '';
                
                if ($data_aula < $hoje) {
                    $pode_registrar = true;
                } elseif ($data_aula == $hoje) {
                    if ($data_hora_obj <= $agora) {
                        $pode_registrar = true;
                    } else {
                        $pode_registrar = false;
                        $mensagem_erro = "Não é possível registrar uma aula antes do horário. Aguarde até as $hora_aula.";
                    }
                } else {
                    $pode_registrar = false;
                    $mensagem_erro = "Não é possível registrar uma aula futura. A data informada é posterior a hoje.";
                }
                
                if (!$pode_registrar) {
                    echo json_encode(['success' => false, 'message' => $mensagem_erro]);
                    exit;
                }
                
                $conn->begin_transaction();
                
                try {
                    $sql_aula = "INSERT INTO agendamentos_aulas 
                               (aluno_id, professor_id, data_hora, status, observacoes_professor) 
                               VALUES (?, ?, ?, 'realizado', ?)";
                    $stmt_aula = $conn->prepare($sql_aula);
                    $stmt_aula->bind_param("iiss", $ficha_id, $usuario_id, $data_hora, $observacoes_gerais);
                    $stmt_aula->execute();
                    $aula_id = $conn->insert_id;
                    $stmt_aula->close();
                    
                    $sql_item = "INSERT INTO aula_itens 
                               (aula_id, disciplina, conteudo_abordado, dificuldades_identificadas, observacoes_professor) 
                               VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item);
                    
                    foreach ($disciplinas as $disciplina) {
                        $stmt_item->bind_param("issss", 
                            $aula_id,
                            $disciplina['materia'],
                            $disciplina['conteudo'],
                            $disciplina['dificuldades'],
                            $disciplina['observacoes']
                        );
                        $stmt_item->execute();
                    }
                    $stmt_item->close();
                    
                    @consumirAula($conn, $ficha_id, $aula_id, 'realizado');
                    
                    $conn->commit();
                    
                    echo json_encode(['success' => true, 'message' => 'Aula registrada com ' . count($disciplinas) . ' disciplinas']);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
                break;
                
            case 'cancelar_antecipado':
                $aula_id = $input['aula_id'] ?? null;
                $motivo = $input['motivo'] ?? '';
                
                if (!$aula_id) {
                    echo json_encode(['success' => false, 'message' => 'ID da aula não fornecido']);
                    exit;
                }
                
                if (empty($motivo)) {
                    echo json_encode(['success' => false, 'message' => 'Por favor, informe o motivo do cancelamento']);
                    exit;
                }
                
                $conn->begin_transaction();
                
                try {
                    $sql_busca = "SELECT aluno_id, data_hora, status, professor_id FROM agendamentos_aulas WHERE id = ? AND professor_id = ?";
                    $stmt = $conn->prepare($sql_busca);
                    $stmt->bind_param("ii", $aula_id, $usuario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $aula = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$aula) {
                        throw new Exception("Aula não encontrada ou não pertence a este professor");
                    }
                    
                    if ($aula['status'] !== 'agendado') {
                        throw new Exception("Esta aula já foi " . ($aula['status'] === 'realizado' ? 'realizada' : 'cancelada'));
                    }
                    
                    $ficha_id = $aula['aluno_id'];
                    $data_formatada = date('d/m/Y H:i', strtotime($aula['data_hora']));
                    
                    $observacao = "🔴 Aula CANCELADA pelo professor com antecedência. Motivo: $motivo. Data original: $data_formatada";
                    
                    $sql_update = "UPDATE agendamentos_aulas SET 
                                   status = 'cancelado_professor', 
                                   observacoes_professor = ?
                                   WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $observacao, $aula_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    @consumirAula($conn, $ficha_id, $aula_id, 'professor');
                    
                    // ===== CRIAR NOTIFICAÇÃO PARA O ALUNO =====
                    // Buscar ID do aluno na tabela usuarios
                    $sql_aluno_user = "SELECT usuario_id FROM fichas WHERE id = ?";
                    $stmt_aluno = $conn->prepare($sql_aluno_user);
                    $stmt_aluno->bind_param("i", $ficha_id);
                    $stmt_aluno->execute();
                    $result_aluno = $stmt_aluno->get_result();
                    $aluno_user = $result_aluno->fetch_assoc();
                    
                    if ($aluno_user && $aluno_user['usuario_id']) {
                        $titulo = "Aula cancelada pelo professor";
                        $mensagem_notificacao = "O professor $nome_professor cancelou a aula do dia $data_formatada. Motivo: $motivo";
                        $link = "dashboard.php?mes=" . date('m', strtotime($aula['data_hora'])) . 
                                "&ano=" . date('Y', strtotime($aula['data_hora']));
                        
                        criarNotificacao($conn, $aluno_user['usuario_id'], 'aluno', $titulo, $mensagem_notificacao, $link);
                        
                        error_log("📢 Notificação criada para o aluno ID: " . $aluno_user['usuario_id']);
                    }
                    
                    $conn->commit();
                    
                    echo json_encode(['success' => true, 'message' => 'Aula cancelada com sucesso! O aluno será notificado.']);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
                break;
                
            case 'concluir':
                $aula_id = $input['aula_id'] ?? null;
                $disciplinas = $input['disciplinas'] ?? [];
                $observacoes_gerais = $input['observacoes_gerais'] ?? '';
                
                if (empty($disciplinas)) {
                    echo json_encode(['success' => false, 'message' => 'Nenhuma disciplina registrada']);
                    exit;
                }
                
                $conn->begin_transaction();
                
                try {
                    $sql_busca = "SELECT aluno_id, status FROM agendamentos_aulas WHERE id = ? AND professor_id = ?";
                    $stmt = $conn->prepare($sql_busca);
                    $stmt->bind_param("ii", $aula_id, $usuario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $aula = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$aula) {
                        throw new Exception("Aula não encontrada ou não pertence a este professor");
                    }
                    
                    if ($aula['status'] === 'realizado') {
                        throw new Exception("Esta aula já foi realizada");
                    }
                    
                    $ficha_id = $aula['aluno_id'];
                    
                    $sql_update = "UPDATE agendamentos_aulas SET 
                                   status = 'realizado', 
                                   observacoes_professor = ?,
                                   data_conclusao = NOW()
                                   WHERE id = ? AND professor_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sii", $observacoes_gerais, $aula_id, $usuario_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    $sql_item = "INSERT INTO aula_itens 
                               (aula_id, disciplina, conteudo_abordado, dificuldades_identificadas, observacoes_professor) 
                               VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item);
                    
                    foreach ($disciplinas as $disciplina) {
                        $stmt_item->bind_param("issss", 
                            $aula_id,
                            $disciplina['materia'],
                            $disciplina['conteudo'],
                            $disciplina['dificuldades'],
                            $disciplina['observacoes']
                        );
                        $stmt_item->execute();
                    }
                    $stmt_item->close();
                    
                    @consumirAula($conn, $ficha_id, $aula_id, 'realizado');
                    
                    // ===== NOVO: CRIAR NOTIFICAÇÃO PARA O ALUNO (AULA REALIZADA) =====
                    $sql_aluno_user = "SELECT usuario_id FROM fichas WHERE id = ?";
                    $stmt_aluno = $conn->prepare($sql_aluno_user);
                    $stmt_aluno->bind_param("i", $ficha_id);
                    $stmt_aluno->execute();
                    $result_aluno = $stmt_aluno->get_result();
                    $aluno_user = $result_aluno->fetch_assoc();
                    
                    if ($aluno_user && $aluno_user['usuario_id']) {
                        $data_aula = new DateTime($aula['data_hora']);
                        $data_formatada = $data_aula->format('d/m/Y H:i');
                        $titulo = "✅ Aula registada";
                        $mensagem_notificacao = "O professor registou a aula do dia $data_formatada.";
                        $link = "dashboard.php?mes=" . $data_aula->format('m') . 
                                "&ano=" . $data_aula->format('Y');
                        
                        criarNotificacao($conn, $aluno_user['usuario_id'], 'aluno', $titulo, $mensagem_notificacao, $link);
                        
                        error_log("📢 Notificação de aula realizada criada para o aluno ID: " . $aluno_user['usuario_id']);
                    }
                    
                    $conn->commit();
                    
                    echo json_encode(['success' => true, 'message' => 'Aula concluída com ' . count($disciplinas) . ' disciplinas']);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
                break;
        }
    }
}

// ===== FUNÇÃO PARA GERAR CALENDÁRIO =====
function gerarCalendario($aluno_id, $horarios, $aulas_mes, $mes, $ano) {
    $primeiro_dia = mktime(0, 0, 0, $mes, 1, $ano);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);
    
    $dias_semana_portugues = [
        'sunday' => 'domingo', 'monday' => 'segunda', 'tuesday' => 'terca',
        'wednesday' => 'quarta', 'thursday' => 'quinta', 'friday' => 'sexta',
        'saturday' => 'sabado'
    ];
    
    $dias_aula = [];
    $horario_padrao = '';
    foreach ($horarios as $h) {
        $dias_aula[] = $h['dia_semana'];
        if (empty($horario_padrao)) {
            $horario_padrao = $h['horario'];
        }
    }
    
    $html = '<div class="mini-calendario">';
    $html .= '<div class="calendario-dias-semana">';
    $html .= '<span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>';
    $html .= '</div>';
    
    $html .= '<div class="calendario-grid">';
    
    // Dias vazios no início
    for ($i = 0; $i < $dia_semana_inicio; $i++) {
        $html .= '<div class="calendario-dia vazio"></div>';
    }
    
    // Dias do mês
    $hoje = date('Y-m-d');
    $agora = new DateTime();
    
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_atual = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
        $timestamp = mktime(0, 0, 0, $mes, $dia, $ano);
        $dia_semana_ingles = strtolower(date('l', $timestamp));
        $dia_semana = $dias_semana_portugues[$dia_semana_ingles] ?? '';
        
        $tem_aula = in_array($dia_semana, $dias_aula);
        $hoje_flag = ($data_atual == $hoje);
        
        // Verificar se é futuro em relação ao horário da aula
        $data_hora_aula = null;
        if ($tem_aula && !empty($horario_padrao)) {
            $data_hora_aula = new DateTime($data_atual . ' ' . $horario_padrao);
        }
        
        $pode_registrar = false;
        $ja_passou = false;
        
        if ($tem_aula && $data_hora_aula) {
            $ja_passou = ($data_hora_aula <= $agora);
            $pode_registrar = $ja_passou;
        }
        
        // Verificar se existe aula nesta data
        $aula_existente = isset($aulas_mes[$data_atual]) ? $aulas_mes[$data_atual] : null;
        $status_aula = $aula_existente ? $aula_existente['status'] : null;
        $aula_id = $aula_existente ? $aula_existente['id'] : null;
        $hora_aula = $aula_existente ? $aula_existente['hora'] : $horario_padrao;
        $observacoes_aula = $aula_existente ? $aula_existente['observacoes'] : '';
        
        // Calcular se é futuro
        $data_atual_obj = new DateTime($data_atual);
        $is_futuro = ($data_atual_obj > new DateTime('today'));
        
        $classes = ['calendario-dia'];
        if ($tem_aula) $classes[] = 'dia-aula';
        if ($hoje_flag) $classes[] = 'hoje';
        if ($status_aula === 'realizado') $classes[] = 'aula-realizada';
        if ($status_aula === 'cancelado_aluno') $classes[] = 'aula-cancelada-aluno';
        if ($status_aula === 'cancelado_professor') $classes[] = 'aula-cancelada-professor';
        if ($status_aula === 'pendente_professor') $classes[] = 'aula-pendente-professor';
        if ($aula_existente) $classes[] = 'tem-aula';
        
        // Se já passou e não tem registro, considerar como cancelada automaticamente
        if ($tem_aula && $ja_passou && !$aula_existente) {
            $classes[] = 'aula-cancelada-aluno';
            $classes[] = 'cancelada-auto';
        }
        
        // ===== ATRIBUTOS DATA PARA O CALENDÁRIO DO PROFESSOR =====
        $data_attributes = "data-aluno-id='$aluno_id' data-dia='$dia' data-data='$data_atual' data-horario='$hora_aula'";
        
        if ($aula_id) {
            $data_attributes .= " data-aula-id='$aula_id'";
        }
        
        if ($status_aula) {
            $data_attributes .= " data-status='$status_aula'";
        }
        
        // REGRAS PARA O PROFESSOR:
        if ($pode_registrar && !$aula_existente) {
            $data_attributes .= " data-pode-registrar='true'";
        }
        
        if ($aula_id && $status_aula === 'agendado' && ($is_futuro || ($hoje_flag && !$ja_passou))) {
            $data_attributes .= " data-pode-cancelar='true'";
        }
        
        if ($tem_aula && $ja_passou && !$aula_existente) {
            $data_attributes .= " data-cancelada-auto='true'";
        }
        
        $html .= "<div class='" . implode(' ', $classes) . "' $data_attributes>";
        $html .= "<span class='dia-numero'>$dia</span>";
        
        if ($status_aula === 'realizado') {
            $html .= "<span class='icone-status realizado' title='Aula realizada'>✓</span>";
        } elseif ($status_aula === 'cancelado_aluno') {
            $html .= "<span class='icone-status cancelado-aluno' title='$observacoes_aula'>✕</span>";
        } elseif ($status_aula === 'cancelado_professor') {
            $html .= "<span class='icone-status cancelado-professor' title='$observacoes_aula'>⌧</span>";
        } elseif ($status_aula === 'pendente_professor') {
            $html .= "<span class='icone-status pendente' title='Aula pendente - Professor deve reposição'>⏰</span>";
        } elseif ($status_aula === 'agendado') {
            $html .= "<span class='icone-status agendado' title='Aula agendada para $hora_aula'>⏳</span>";
        } elseif ($tem_aula && !$aula_existente) {
            if ($ja_passou) {
                $html .= "<span class='icone-status cancelado-aluno' title='Aula não registrada - Cancelada automaticamente'>✕</span>";
            } else {
                $html .= "<span class='icone-status agendado' title='Aula prevista para $hora_aula'>⏳</span>";
            }
        }
        
        $html .= "</div>";
    }
    
    $html .= '</div>';
    
    // Horário padrão
    if (!empty($horario_padrao)) {
        $html .= '<div class="horario-padrao"><i class="fas fa-clock"></i> Horário: ' . $horario_padrao . '</div>';
    }
    
    // Legenda
    $html .= '<div class="calendario-legenda">';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-agendada"></span> Prevista</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-realizada"></span> Realizada</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-cancelada-aluno"></span> Cancelada (não registrada)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-cancelada-prof"></span> Cancelada (professor)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-pendente-prof"></span> Pendente (professor)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor hoje-legend"></span> Hoje</span>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Dashboard do Professor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/professor.css">
    
    <!-- CSS ADICIONAL PARA O MENU MOBILE -->
    <style>
        /* Botão menu hamburguer - só aparece em mobile */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px 15px;
            cursor: pointer;
            font-size: 1.2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }
        
        .menu-toggle:hover {
            background: #2980b9;
        }
        
        .menu-close {
            display: none;
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.3s;
            z-index: 1002;
        }
        
        .menu-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* ESTILOS CORRETOS PARA DESKTOP E MOBILE */
        /* Desktop - sidebar sempre visível */
        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0) !important;
            }
            
            .menu-toggle {
                display: none !important;
            }
            
            .menu-close {
                display: none !important;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* Mobile - sidebar escondida por padrão, abre com classe active */
        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
            }
            
            .menu-close {
                display: flex;
            }
            
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            
            .sidebar.active {
                transform: translateX(0) !important;
            }
            
            .content {
                margin-left: 0;
                padding-top: 70px;
            }
        }
    </style>
    
    <!-- Passar dados do PHP para o JavaScript -->
    <script>
        window.alunosData = <?php 
            $alunos_json = [];
            foreach($alunos_com_horarios as $aluno){
                $alunos_json[$aluno['id']] = $aluno['nome'];
            }
            echo json_encode($alunos_json); 
        ?>;
        window.totalCanceladasAuto = <?= $total_canceladas_auto ?>;
        window.mesAtual = <?= $mes_atual ?>;
        window.anoAtual = <?= $ano_atual ?>;
        window.notificacoesNaoLidas = <?= $notificacoes_nao_lidas ?>;
        
        console.log('📦 Dados PHP carregados:', {
            alunos: <?= count($alunos_json) ?>,
            canceladas: <?= $total_canceladas_auto ?>,
            notificacoes: <?= $notificacoes_nao_lidas ?>
        });
    </script>
</head>
<body>

<!-- Botão menu hamburguer mobile -->
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay para fechar menu -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <button class="menu-close" id="menuClose">
        <i class="fas fa-times"></i>
    </button>
    
    <h2>Painel do Professor</h2>
    <ul>
        <li><a href="#" onclick="mostrarSecao('boas_vindas', event)" class="active">
            <i class="fas fa-home"></i> Início
        </a></li>
        <li><a href="#" onclick="mostrarSecao('meus_alunos', event)">
            <i class="fas fa-users"></i> Meus Alunos
        </a></li>
        <li><a href="#" onclick="mostrarSecao('perfil', event)">
            <i class="fas fa-user"></i> Perfil
        </a></li>
        <li><a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a></li>
    </ul>
</aside>

<main class="content">

    <!-- Boas-vindas -->
    <section id="boas_vindas" class="dashboard-section">
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Bem-vindo ao Painel do Professor</h2>
                
                <!-- Ícone de notificações -->
                <div class="notificacoes-container">
                    <button class="notificacoes-btn" onclick="toggleNotificacoes()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificacoes_nao_lidas > 0): ?>
                            <span class="notificacoes-badge"><?= $notificacoes_nao_lidas ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notificacoes-dropdown" id="notificacoesDropdown" style="display: none;">
                        <div class="notificacoes-header">
                            <h4>Notificações</h4>
                            <?php if ($notificacoes_nao_lidas > 0): ?>
                                <button onclick="marcarTodasComoLidas()" class="btn-marcar-lidas">
                                    <i class="fas fa-check-double"></i> Marcar todas
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notificacoes-lista" id="notificacoes-lista">
                            <?php if (empty($notificacoes)): ?>
                                <div class="notificacao-vazia">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>Nenhuma notificação</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                    <div class="notificacao-item <?= $notif['lida'] ? 'lida' : 'nao-lida' ?>" 
                                         data-id="<?= $notif['id'] ?>">
                                        <div class="notificacao-titulo"><?= htmlspecialchars($notif['titulo']) ?></div>
                                        <div class="notificacao-mensagem"><?= htmlspecialchars($notif['mensagem']) ?></div>
                                        <div class="notificacao-data"><?= date('d/m/Y H:i', strtotime($notif['data_criacao'])) ?></div>
                                        <?php if ($notif['link']): ?>
                                            <a href="<?= htmlspecialchars($notif['link']) ?>" class="notificacao-link">Ver detalhes</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <p>👋 Olá, <strong><?= htmlspecialchars($nome_professor) ?></strong>!</p>
            <p>Aqui você pode gerenciar seus alunos e registrar aulas de reforço.</p>
        </div>
        
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Aula registrada com sucesso!
        </div>
        <?php endif; ?>
        
        <!-- Notificação de aulas canceladas automaticamente -->
        <?php if ($total_canceladas_auto > 0): ?>
        <div class="alert alert-warning" style="margin-top: 20px;" id="notificacao-canceladas-php">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Atenção:</strong> <?= $total_canceladas_auto ?> aula(s) foram canceladas automaticamente neste mês por falta de registro.
            <button onclick="verAulasCanceladas()" class="btn btn-sm btn-warning" style="margin-left: 10px;">
                <i class="fas fa-eye"></i> Ver aulas
            </button>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($alunos_com_horarios) ?></div>
                <div class="stat-label">Total de Alunos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $confirmados = 0;
                    foreach($alunos_com_horarios as $aluno){
                        if(isset($aluno['pacote_confirmado']) && $aluno['pacote_confirmado']) $confirmados++;
                    }
                    echo $confirmados;
                    ?>
                </div>
                <div class="stat-label">Pacotes Confirmados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $result_aulas->num_rows ?></div>
                <div class="stat-label">Aulas Registradas</div>
            </div>
        </div>
        
        <?php if (empty($alunos_com_horarios)): ?>
        <div class="dashboard-card no-alunos">
            <i class="fas fa-user-graduate"></i>
            <h3>Nenhum aluno atribuído</h3>
            <p>Você ainda não tem alunos atribuídos. Aguarde a atribuição pelo administrador.</p>
        </div>
        <?php endif; ?>
    </section>

    <!-- Meus Alunos -->
    <section id="meus_alunos" class="hidden">
        <h2 class="section-title">Meus Alunos</h2>
        
        <!-- Navegação do Calendário -->
        <div class="navegacao-mes">
            <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>" class="btn-mes">
                <i class="fas fa-chevron-left"></i> <?= $meses_portugues[$mes_anterior] ?>
            </a>
            <h3><i class="fas fa-calendar-alt"></i> <?= $meses_portugues[$mes_atual] ?> <?= $ano_atual ?></h3>
            <a href="?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?>" class="btn-mes">
                <?= $meses_portugues[$mes_proximo] ?> <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <?php if (empty($alunos_com_horarios)): ?>
        <div class="dashboard-card no-alunos">
            <i class="fas fa-user-graduate"></i>
            <h3>Nenhum aluno atribuído</h3>
            <p>Você ainda não tem alunos atribuídos. Aguarde a atribuição pelo administrador.</p>
        </div>
        <?php else: ?>
        
        <div class="dashboard-card">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Você tem <strong><?= count($alunos_com_horarios) ?></strong> aluno(s) atribuído(s)
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Nome do Aluno</th>
                        <th>Calendário de Aulas (<?= $meses_portugues[$mes_atual] ?>)</th>
                        <th>Regime</th>
                        <th>Dificuldades</th>
                       <!-- <th>Ações</th>-->
                    </td>
                </thead>
                <tbody>
                <?php foreach($alunos_com_horarios as $aluno): 
                    $dias_portugues = [
                        'segunda' => 'Segunda',
                        'terca' => 'Terça',
                        'quarta' => 'Quarta',
                        'quinta' => 'Quinta',
                        'sexta' => 'Sexta',
                        'sabado' => 'Sábado',
                        'domingo' => 'Domingo'
                    ];
                    
                    $pacote_display = '';
                    $dias_pacote = '';
                    switch ($aluno['pacote'] ?? '') {
                        case 'basico': 
                            $pacote_display = 'Básico (2x/semana)';
                            $dias_pacote = '2 dias/semana';
                            break;
                        case 'intermedio': 
                            $pacote_display = 'Intermediário (3x/semana)';
                            $dias_pacote = '3 dias/semana';
                            break;
                        case 'premium': 
                            $pacote_display = 'Premium (4x/semana)';
                            $dias_pacote = '4 dias/semana';
                            break;
                        default: 
                            $pacote_display = htmlspecialchars($aluno['pacote'] ?? '');
                            $dias_pacote = '';
                    }
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($aluno['nome']) ?></strong><br>
                            <small style="color: #7f8c8d;">
                                Classe: <?= htmlspecialchars($aluno['classe']) ?> | 
                                <?= htmlspecialchars($aluno['escola']) ?>
                            </small>
                            <div class="info-pacote">
                                <span class="badge-disciplinas"><?= $pacote_display ?></span>
                                <?php if (($aluno['permite_finsemana'] ?? 0)): ?>
                                    <br><small style="color: #27ae60;">✓ Inclui fins de semana</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?= gerarCalendario($aluno['id'], $aluno['horarios'], $aluno['aulas_mes'], $mes_atual, $ano_atual) ?>
                        </td>
                        <td>
                            <div>
                                <?php if ($aluno['regime_presencial'] ?? 0): ?>
                                    <span class="regime-tag">Presencial</span>
                                <?php endif; ?>
                                <?php if ($aluno['regime_online'] ?? 0): ?>
                                    <span class="regime-tag">Online</span>
                                <?php endif; ?>
                                <?php if ($aluno['regime_domicilio'] ?? 0): ?>
                                    <span class="regime-tag">Domicílio</span>
                                <?php endif; ?>
                                <?php if ($aluno['regime_hibrido'] ?? 0): ?>
                                    <span class="regime-tag">Híbrido</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="dificuldade-box">
                                <?= htmlspecialchars($aluno['dificuldade']) ?>
                            </div>
                        </td>
                        <td>
                           <! <div class="aluno-actions">
                               <!-- <button onclick="abrirModalRegistro(<?= $aluno['id'] ?>, '<?= htmlspecialchars(addslashes($aluno['nome'])) ?>', null, '<?= $aluno['horarios'][0]['horario'] ?? '' ?>')" 
                                        class="btn btn-registrar btn-sm btn-completo">
                                    <i class="fas fa-plus-circle"></i> Registrar Aula
                                </button>
                                <button onclick="verAulasAluno(<?= $aluno['id'] ?>, '<?= htmlspecialchars(addslashes($aluno['nome'])) ?>')" 
                                        class="btn btn-info btn-sm btn-completo">
                                    <i class="fas fa-calendar-alt"></i> Ver Aulas
                                </button>-->
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- Perfil -->
    <section id="perfil" class="hidden">
        <h2 class="section-title">Perfil</h2>
        <div class="dashboard-card">
            <p>Em breve será possível visualizar e atualizar seus dados.</p>
        </div>
    </section>

</main>

<!-- ===== MODAL DE DETALHES DA AULA ===== -->
<div id="modalDetalhes" class="modal">
    <div class="modal-content" style="max-width: 750px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalhes da Aula</h3>
            <button class="close" onclick="fecharModal('modalDetalhes')">&times;</button>
        </div>
        <div class="modal-body" id="modal-body-conteudo">
            <!-- Conteúdo preenchido via JavaScript -->
        </div>
    </div>
</div>

<!-- Modal para marcar aula como realizada -->
<div id="modalDada" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> Registrar Aula como Realizada</h3>
            <button class="close" onclick="fecharModal('modalDada')">&times;</button>
        </div>
        <form id="formConcluirAula">
            <input type="hidden" name="aula_id" id="dada_aula_id">
            <input type="hidden" name="acao_aula" value="concluir">
            
            <div class="aula-info">
                <p><strong>Aluno:</strong> <span id="dada_aluno_nome"></span></p>
                <p><strong>Data/Hora:</strong> <span id="dada_data_hora"></span></p>
            </div>
            
            <div class="field-group">
                <h4><i class="fas fa-bolt"></i> Seleção Rápida de Disciplinas</h4>
                <div class="disciplinas-rapidas" id="dada-disciplinas-rapidas">
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Matemática')">
                        <i class="fas fa-calculator"></i> Matemática
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Português')">
                        <i class="fas fa-book"></i> Português
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Inglês')">
                        <i class="fas fa-language"></i> Inglês
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Física')">
                        <i class="fas fa-atom"></i> Física
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Química')">
                        <i class="fas fa-flask"></i> Química
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'História')">
                        <i class="fas fa-landmark"></i> História
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Geografia')">
                        <i class="fas fa-globe-americas"></i> Geografia
                    </span>
                    <span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Biologia')">
                        <i class="fas fa-dna"></i> Biologia
                    </span>
                </div>
            </div>
            
            <div class="field-group">
                <h4><i class="fas fa-book"></i> Disciplinas</h4>
                <div id="dada-disciplinas-container">
                    <!-- Disciplinas aparecerão aqui -->
                </div>
            </div>
            
            <div class="field-group">
                <h4><i class="fas fa-clipboard"></i> Observações</h4>
                <div class="form-group">
                    <textarea id="dada_observacoes" rows="3" 
                              placeholder="Observações sobre a aula..."></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-danger" onclick="fecharModal('modalDada')">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="concluirAula()">
                    <i class="fas fa-check"></i> Concluir Aula
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para registrar aula -->
<div id="modalRegistro" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Registrar Nova Aula</h3>
            <button class="close" onclick="fecharModal('modalRegistro')">&times;</button>
        </div>
        
        <div id="registro-loading" style="display: none; text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: #3498db;"></i>
            <p style="margin-top: 20px; color: #3498db;">Registrando aula...</p>
        </div>
        
        <div id="registro-form-container">
            <form id="formRegistroAula">
                <input type="hidden" name="acao_aula" value="registrar_multiplas">
                <input type="hidden" name="ficha_id" id="registro_ficha_id">
                <input type="hidden" name="data_hora" id="registro_data_hora">
                
                <div class="aula-info">
                    <p><strong>Aluno:</strong> <span id="nome_aluno_registro"></span></p>
                    <p><strong>Data/Hora:</strong> <span id="data_hora_display"></span></p>
                    <p><strong>Horário padrão:</strong> <span id="horario_padrao_display"></span></p>
                </div>
                
                <div class="field-group">
                    <h4><i class="fas fa-bolt"></i> Seleção Rápida de Disciplinas</h4>
                    <div class="disciplinas-rapidas">
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Matemática')">
                            <i class="fas fa-calculator"></i> Matemática
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Português')">
                            <i class="fas fa-book"></i> Português
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Inglês')">
                            <i class="fas fa-language"></i> Inglês
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Física')">
                            <i class="fas fa-atom"></i> Física
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Química')">
                            <i class="fas fa-flask"></i> Química
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'História')">
                            <i class="fas fa-landmark"></i> História
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Geografia')">
                            <i class="fas fa-globe-americas"></i> Geografia
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Biologia')">
                            <i class="fas fa-dna"></i> Biologia
                        </span>
                        <span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Francês')">
                            <i class="fas fa-language"></i> Francês
                        </span>
                    </div>
                </div>
                
                <div class="field-group">
                    <h4><i class="fas fa-book"></i> Disciplinas Selecionadas</h4>
                    <div id="disciplinas-selecionadas-container">
                        <!-- Disciplinas selecionadas aparecerão aqui -->
                    </div>
                </div>
                
                <div class="field-group">
                    <h4><i class="fas fa-clipboard"></i> Observações Gerais</h4>
                    <div class="form-group">
                        <textarea id="observacoes_gerais" rows="3" 
                                  placeholder="Observações sobre o comportamento, pontualidade, material necessário..."></textarea>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-danger" onclick="fecharModal('modalRegistro')">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="registrarAula()">
                        <i class="fas fa-save"></i> Registrar Aula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cancelar aula com antecedência -->
<div id="modalCancelarAntecipado" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle"></i> Cancelar Aula com Antecedência</h3>
            <button class="close" onclick="fecharModal('modalCancelarAntecipado')">&times;</button>
        </div>
        
        <div class="aula-info" id="cancelar-info">
            <p><strong>Aluno:</strong> <span id="cancelar_aluno_nome"></span></p>
            <p><strong>Data/Hora:</strong> <span id="cancelar_data_hora"></span></p>
            <input type="hidden" id="cancelar_aula_id">
        </div>
        
        <div class="field-group">
            <h4><i class="fas fa-comment"></i> Motivo do Cancelamento</h4>
            <div class="form-group">
                <select id="motivo_cancelamento" class="form-control" style="width: 100%; padding: 10px; margin-bottom: 10px;">
                    <option value="">Selecione um motivo...</option>
                    <option value="Problemas de saúde">Problemas de saúde</option>
                    <option value="Compromisso pessoal">Compromisso pessoal</option>
                    <option value="Falha técnica">Falha técnica (internet/energia)</option>
                    <option value="Emergência familiar">Emergência familiar</option>
                    <option value="Outro">Outro (especifique)</option>
                </select>
                <textarea id="motivo_outro" rows="3" style="width: 100%; padding: 10px; display: none;" 
                          placeholder="Descreva o motivo do cancelamento..."></textarea>
            </div>
        </div>
        
        <div class="alert alert-warning" style="margin: 15px 0;">
            <i class="fas fa-info-circle"></i> 
            <strong>Nota:</strong> O aluno será notificado imediatamente sobre este cancelamento. Um crédito de reposição será gerado.
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalCancelarAntecipado')">Voltar</button>
            <button type="button" class="btn btn-danger" onclick="confirmarCancelamentoAntecipado()">
                <i class="fas fa-times"></i> Confirmar Cancelamento
            </button>
        </div>
    </div>
</div>

<!-- Modal para ver aulas do aluno -->
<div id="modalAulasAluno" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt"></i> Aulas do Aluno</h3>
            <button class="close" onclick="fecharModal('modalAulasAluno')">&times;</button>
        </div>
        
        <div class="aula-info">
            <p><strong>Aluno:</strong> <span id="nome_aluno_aulas"></span></p>
            <p>Lista de todas as aulas registradas para este aluno.</p>
        </div>
        
        <div id="conteudo_aulas_aluno">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-2x" style="color: #3498db;"></i>
                <p>Carregando aulas...</p>
            </div>
        </div>
        
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAulasAluno')">Fechar</button>
        </div>
    </div>
</div>

<!-- CSS para notificações (apenas estilos, sem JavaScript) -->
<style>
.notificacoes-container {
    position: relative;
    display: inline-block;
    margin-right: 10px;
}

.notificacoes-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    position: relative;
    color: #2c3e50;
    padding: 8px;
    transition: color 0.2s;
}

.notificacoes-btn:hover {
    color: #3498db;
}

.notificacoes-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 0.7rem;
    min-width: 18px;
    text-align: center;
    font-weight: bold;
}

.notificacoes-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    z-index: 1000;
    margin-top: 10px;
    border: 1px solid #eef2f6;
    display: none;
}

.notificacoes-dropdown.active {
    display: block;
}

.notificacoes-header {
    padding: 15px;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafd;
    border-radius: 12px 12px 0 0;
}

.notificacoes-header h4 {
    margin: 0;
    font-size: 1rem;
    color: #2c3e50;
    font-weight: 600;
}

.btn-marcar-lidas {
    background: none;
    border: none;
    color: #3498db;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
}

.btn-marcar-lidas:hover {
    background: #e1f0fa;
}

.notificacoes-lista {
    max-height: 350px;
    overflow-y: auto;
}

.notificacao-item {
    padding: 15px;
    border-bottom: 1px solid #eef2f6;
    cursor: pointer;
    transition: background 0.2s;
    position: relative;
}

.notificacao-item:hover {
    background: #f8f9fa;
}

.notificacao-item.nao-lida {
    background: #ebf5ff;
    border-left: 3px solid #3498db;
}

.notificacao-titulo {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 0.95rem;
}

.notificacao-mensagem {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-bottom: 8px;
    line-height: 1.4;
}

.notificacao-data {
    font-size: 0.7rem;
    color: #95a5a6;
    display: flex;
    align-items: center;
    gap: 5px;
}

.notificacao-link {
    display: inline-block;
    margin-top: 8px;
    font-size: 0.8rem;
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.notificacao-link:hover {
    text-decoration: underline;
}

.notificacao-vazia {
    padding: 40px 20px;
    text-align: center;
    color: #95a5a6;
}

.notificacao-vazia i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    color: #d0d9e0;
}

.notificacao-vazia p {
    font-size: 0.9rem;
    margin: 0;
}
</style>

<!-- APENAS UM ÚNICO ARQUIVO JAVASCRIPT -->
<script src="JavaScript/professor.js"></script>

</body>
</html>

<?php
if (isset($stmt_aulas)) $stmt_aulas->close();
$conn->close();
?>