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

// 5️⃣ PROCESSAR CANCELAMENTOS AUTOMÁTICOS (apenas aulas de dias anteriores)
$sql_aulas_passadas = "SELECT a.id, a.aluno_id, a.data_hora, f.nome as aluno_nome
                       FROM agendamentos_aulas a
                       JOIN fichas f ON f.id = a.aluno_id
                       WHERE a.status = 'agendado' 
                       AND DATE(a.data_hora) < CURDATE()";
$result_passadas = $conn->query($sql_aulas_passadas);

if ($result_passadas->num_rows > 0) {
    while ($aula_passada = $result_passadas->fetch_assoc()) {
        $aula_id = $aula_passada['id'];
        $ficha_id = $aula_passada['aluno_id'];
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

if ($mes_atual < 1) $mes_atual = 1;
if ($mes_atual > 12) $mes_atual = 12;
if ($ano_atual < 2020) $ano_atual = 2020;
if ($ano_atual > 2030) $ano_atual = 2030;

$mes_anterior = $mes_atual - 1;
$ano_anterior = $ano_atual;
if ($mes_anterior < 1) { $mes_anterior = 12; $ano_anterior--; }
$mes_proximo = $mes_atual + 1;
$ano_proximo = $ano_atual;
if ($mes_proximo > 12) { $mes_proximo = 1; $ano_proximo++; }

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

// 1️⃣1️⃣ Processar ações do formulário (aulas) - mantido original
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['acao_aula'])) {
        $acao = $input['acao_aula'];
        switch ($acao) {
            case 'registrar_multiplas':
                // Código original (não modificado)
                $ficha_id = $input['ficha_id'] ?? null;
                $data_hora = $input['data_hora'] ?? '';
                $disciplinas = $input['disciplinas'] ?? [];
                $observacoes_gerais = $input['observacoes_gerais'] ?? '';
                if (!$ficha_id || !$data_hora || empty($disciplinas)) {
                    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                    exit;
                }
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
                    $sql_aula = "INSERT INTO agendamentos_aulas (aluno_id, professor_id, data_hora, status, observacoes_professor) VALUES (?, ?, ?, 'realizado', ?)";
                    $stmt_aula = $conn->prepare($sql_aula);
                    $stmt_aula->bind_param("iiss", $ficha_id, $usuario_id, $data_hora, $observacoes_gerais);
                    $stmt_aula->execute();
                    $aula_id = $conn->insert_id;
                    $stmt_aula->close();
                    $sql_item = "INSERT INTO aula_itens (aula_id, disciplina, conteudo_abordado, dificuldades_identificadas, observacoes_professor) VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item);
                    foreach ($disciplinas as $disciplina) {
                        $stmt_item->bind_param("issss", $aula_id, $disciplina['materia'], $disciplina['conteudo'], $disciplina['dificuldades'], $disciplina['observacoes']);
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
                // Código original (mantido)
                $aula_id = $input['aula_id'] ?? null;
                $motivo = $input['motivo'] ?? '';
                if (!$aula_id || empty($motivo)) {
                    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                    exit;
                }
                $conn->begin_transaction();
                try {
                    $sql_busca = "SELECT aluno_id, data_hora, status FROM agendamentos_aulas WHERE id = ? AND professor_id = ?";
                    $stmt = $conn->prepare($sql_busca);
                    $stmt->bind_param("ii", $aula_id, $usuario_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $aula = $result->fetch_assoc();
                    $stmt->close();
                    if (!$aula || $aula['status'] !== 'agendado') {
                        throw new Exception("Aula não encontrada ou já realizada/cancelada");
                    }
                    $ficha_id = $aula['aluno_id'];
                    $data_formatada = date('d/m/Y H:i', strtotime($aula['data_hora']));
                    $observacao = "🔴 Aula CANCELADA pelo professor. Motivo: $motivo. Data original: $data_formatada";
                    $sql_update = "UPDATE agendamentos_aulas SET status = 'cancelado_professor', observacoes_professor = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $observacao, $aula_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    @consumirAula($conn, $ficha_id, $aula_id, 'professor');
                    // Notificar aluno
                    $sql_aluno_user = "SELECT usuario_id FROM fichas WHERE id = ?";
                    $stmt_aluno = $conn->prepare($sql_aluno_user);
                    $stmt_aluno->bind_param("i", $ficha_id);
                    $stmt_aluno->execute();
                    $aluno_user = $stmt_aluno->get_result()->fetch_assoc();
                    if ($aluno_user) {
                        criarNotificacao($conn, $aluno_user['usuario_id'], 'aluno', "Aula cancelada", "O professor cancelou a aula do dia $data_formatada. Motivo: $motivo", "dashboard.php?mes=" . date('m', strtotime($aula['data_hora'])) . "&ano=" . date('Y', strtotime($aula['data_hora'])));
                    }
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Aula cancelada com sucesso!']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
                break;
            case 'concluir':
                // Código original (mantido)
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
                    $aula = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$aula || $aula['status'] === 'realizado') {
                        throw new Exception("Aula não encontrada ou já realizada");
                    }
                    $ficha_id = $aula['aluno_id'];
                    $sql_update = "UPDATE agendamentos_aulas SET status = 'realizado', observacoes_professor = ?, data_conclusao = NOW() WHERE id = ? AND professor_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sii", $observacoes_gerais, $aula_id, $usuario_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                    $sql_item = "INSERT INTO aula_itens (aula_id, disciplina, conteudo_abordado, dificuldades_identificadas, observacoes_professor) VALUES (?, ?, ?, ?, ?)";
                    $stmt_item = $conn->prepare($sql_item);
                    foreach ($disciplinas as $disciplina) {
                        $stmt_item->bind_param("issss", $aula_id, $disciplina['materia'], $disciplina['conteudo'], $disciplina['dificuldades'], $disciplina['observacoes']);
                        $stmt_item->execute();
                    }
                    $stmt_item->close();
                    @consumirAula($conn, $ficha_id, $aula_id, 'realizado');
                    // Notificar aluno
                    $sql_aluno_user = "SELECT usuario_id FROM fichas WHERE id = ?";
                    $stmt_aluno = $conn->prepare($sql_aluno_user);
                    $stmt_aluno->bind_param("i", $ficha_id);
                    $stmt_aluno->execute();
                    $aluno_user = $stmt_aluno->get_result()->fetch_assoc();
                    if ($aluno_user) {
                        $data_aula = new DateTime($aula['data_hora']);
                        criarNotificacao($conn, $aluno_user['usuario_id'], 'aluno', "Aula registada", "O professor registou a aula do dia " . $data_aula->format('d/m/Y H:i'), "dashboard.php?mes=" . $data_aula->format('m') . "&ano=" . $data_aula->format('Y'));
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

// ===== FUNÇÕES AUXILIARES (mantidas originais) =====
function extrairHoraInicio($horario) {
    if (preg_match('/(\d{1,2})(?:h|:)/', $horario, $matches)) {
        return sprintf("%02d:00", $matches[1]);
    } elseif (preg_match('/(\d{1,2}):(\d{2})/', $horario, $matches)) {
        return sprintf("%02d:%02d", $matches[1], $matches[2]);
    }
    return '00:00';
}

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
        if (empty($horario_padrao)) $horario_padrao = $h['horario'];
    }
    $hora_inicio_padrao = extrairHoraInicio($horario_padrao);
    $html = '<div class="mini-calendario">';
    $html .= '<div class="calendario-dias-semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>';
    $html .= '<div class="calendario-grid">';
    for ($i = 0; $i < $dia_semana_inicio; $i++) $html .= '<div class="calendario-dia vazio"></div>';
    $hoje = date('Y-m-d');
    $agora = new DateTime();
    for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
        $data_atual = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
        $timestamp = mktime(0, 0, 0, $mes, $dia, $ano);
        $dia_semana_ingles = strtolower(date('l', $timestamp));
        $dia_semana = $dias_semana_portugues[$dia_semana_ingles] ?? '';
        $tem_aula = in_array($dia_semana, $dias_aula);
        $hoje_flag = ($data_atual == $hoje);
        $data_hora_aula = ($tem_aula && !empty($hora_inicio_padrao)) ? new DateTime($data_atual . ' ' . $hora_inicio_padrao) : null;
        $ja_passou = ($data_hora_aula && $data_hora_aula <= $agora);
        $pode_registrar = $ja_passou;
        $aula_existente = isset($aulas_mes[$data_atual]) ? $aulas_mes[$data_atual] : null;
        $status_aula = $aula_existente ? $aula_existente['status'] : null;
        $aula_id = $aula_existente ? $aula_existente['id'] : null;
        $hora_aula = $aula_existente ? $aula_existente['hora'] : $hora_inicio_padrao;
        $observacoes_aula = $aula_existente ? $aula_existente['observacoes'] : '';
        $is_futuro = (new DateTime($data_atual) > new DateTime('today'));
        $classes = ['calendario-dia'];
        if ($tem_aula) $classes[] = 'dia-aula';
        if ($hoje_flag) $classes[] = 'hoje';
        if ($status_aula === 'realizado') $classes[] = 'aula-realizada';
        if ($status_aula === 'cancelado_aluno') $classes[] = 'aula-cancelada-aluno';
        if ($status_aula === 'cancelado_professor') $classes[] = 'aula-cancelada-professor';
        if ($status_aula === 'pendente_professor') $classes[] = 'aula-pendente-professor';
        if ($aula_existente) $classes[] = 'tem-aula';
        if ($tem_aula && $ja_passou && !$aula_existente) $classes[] = 'aula-cancelada-aluno';
        $data_attributes = "data-aluno-id='$aluno_id' data-dia='$dia' data-data='$data_atual' data-horario='$hora_aula'";
        if ($aula_id) $data_attributes .= " data-aula-id='$aula_id'";
        if ($status_aula) $data_attributes .= " data-status='$status_aula'";
        if ($pode_registrar && !$aula_existente) $data_attributes .= " data-pode-registrar='true'";
        if ($aula_id && $status_aula === 'agendado' && ($is_futuro || ($hoje_flag && !$ja_passou))) $data_attributes .= " data-pode-cancelar='true'";
        $html .= "<div class='" . implode(' ', $classes) . "' $data_attributes>";
        $html .= "<span class='dia-numero'>$dia</span>";
        if ($status_aula === 'realizado') $html .= "<span class='icone-status realizado' title='Aula realizada'>✓</span>";
        elseif ($status_aula === 'cancelado_aluno') $html .= "<span class='icone-status cancelado-aluno' title='$observacoes_aula'>✕</span>";
        elseif ($status_aula === 'cancelado_professor') $html .= "<span class='icone-status cancelado-professor' title='$observacoes_aula'>⌧</span>";
        elseif ($status_aula === 'pendente_professor') $html .= "<span class='icone-status pendente' title='Aula pendente'>⏰</span>";
        elseif ($status_aula === 'agendado') $html .= "<span class='icone-status agendado' title='Aula agendada para $hora_aula'>⏳</span>";
        elseif ($tem_aula && !$aula_existente) {
            if ($ja_passou) $html .= "<span class='icone-status cancelado-aluno' title='Aula não registrada'>✕</span>";
            else $html .= "<span class='icone-status agendado' title='Aula prevista para $hora_aula'>⏳</span>";
        }
        $html .= "</div>";
    }
    $html .= '</div>';
    if (!empty($horario_padrao)) $html .= '<div class="horario-padrao"><i class="fas fa-clock"></i> Horário: ' . $horario_padrao . '</div>';
    $html .= '<div class="calendario-legenda">';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-agendada"></span> Prevista</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-realizada"></span> Realizada</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-cancelada-aluno"></span> Cancelada (não registrada)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-cancelada-prof"></span> Cancelada (professor)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor aula-pendente-prof"></span> Pendente (professor)</span>';
    $html .= '<span class="legenda-item"><span class="legenda-cor hoje-legend"></span> Hoje</span>';
    $html .= '</div></div>';
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
    <style>
        /* Estilos originais (mantidos) + novos para recursos/trabalhos */
        .menu-toggle { display: none; position: fixed; top: 15px; left: 15px; z-index: 1001; background: #3498db; color: white; border: none; border-radius: 5px; padding: 12px 15px; cursor: pointer; font-size: 1.2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); align-items: center; justify-content: center; transition: background-color 0.3s; }
        .menu-toggle:hover { background: #2980b9; }
        .menu-close { display: none; position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; align-items: center; justify-content: center; font-size: 1.2rem; transition: background 0.3s; z-index: 1002; }
        .menu-close:hover { background: rgba(255,255,255,0.3); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; backdrop-filter: blur(2px); }
        .sidebar-overlay.active { display: block; }
        @media (min-width: 769px) { .sidebar { transform: translateX(0) !important; } .menu-toggle, .menu-close { display: none !important; } .sidebar-overlay { display: none !important; } }
        @media (max-width: 768px) { .menu-toggle, .menu-close { display: flex; } .sidebar { position: fixed; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 1000; } .sidebar.active { transform: translateX(0) !important; } .content { margin-left: 0; padding-top: 70px; } }
        .dashboard-section.hidden { display: none !important; }
        /* Estilos para a lista de recursos e trabalhos */
        .recurso-item, .trabalho-item { border: 1px solid #e2e8f0; padding: 12px; margin-bottom: 12px; border-radius: 8px; background: white; transition: box-shadow 0.2s; }
        .recurso-item:hover, .trabalho-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .btn-sm { padding: 4px 10px; font-size: 0.8rem; border-radius: 4px; text-decoration: none; display: inline-block; margin: 2px; cursor: pointer; }
        .btn-sm i { margin-right: 4px; }
        .btn-primary { background: #3498db; color: white; border: none; }
        .btn-danger { background: #e74c3c; color: white; border: none; }
        .btn-warning { background: #f39c12; color: white; border: none; }
        .btn-success { background: #27ae60; color: white; border: none; }
        .btn-info { background: #17a2b8; color: white; border: none; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { padding: 15px 20px; background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: white; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; }
        .modal-header .close { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .modal-body { padding: 20px; }
        .badge-status { padding: 4px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .badge-pendente { background: #f39c12; color: white; }
        .badge-avaliado { background: #27ae60; color: white; }
        .disciplina-tag { display: inline-block; background: #ecf0f1; padding: 4px 8px; border-radius: 16px; margin: 2px; cursor: pointer; font-size: 0.8rem; }
        .disciplina-tag:hover { background: #3498db; color: white; }
    </style>
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
    </script>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <button class="menu-close" id="menuClose"><i class="fas fa-times"></i></button>
    <h2>Painel do Professor</h2>
    <ul>
        <li><a href="#" data-secao="boas_vindas" class="active"><i class="fas fa-home"></i> Início</a></li>
        <li><a href="#" data-secao="meus_alunos"><i class="fas fa-users"></i> Meus Alunos</a></li>
        <li><a href="#" data-secao="meus_recursos"><i class="fas fa-folder-open"></i> Meus Recursos</a></li>
        <li><a href="#" data-secao="trabalhos_alunos"><i class="fas fa-tasks"></i> Trabalhos dos Alunos</a></li>
        <li><a href="#" data-secao="perfil"><i class="fas fa-user"></i> Perfil</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
    </ul>
</aside>

<main class="content">
    <!-- Secção Boas Vindas (original) -->
    <section id="boas_vindas" class="dashboard-section">
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Bem-vindo ao Painel do Professor</h2>
                <div class="notificacoes-container">
                    <button class="notificacoes-btn" onclick="toggleNotificacoes()"><i class="fas fa-bell"></i>
                        <?php if ($notificacoes_nao_lidas > 0): ?><span class="notificacoes-badge"><?= $notificacoes_nao_lidas ?></span><?php endif; ?>
                    </button>
                    <div class="notificacoes-dropdown" id="notificacoesDropdown" style="display: none;">
                        <div class="notificacoes-header"><h4>Notificações</h4><?php if ($notificacoes_nao_lidas > 0): ?><button onclick="marcarTodasComoLidas()" class="btn-marcar-lidas"><i class="fas fa-check-double"></i> Marcar todas</button><?php endif; ?></div>
                        <div class="notificacoes-lista" id="notificacoes-lista">
                            <?php if (empty($notificacoes)): ?>
                                <div class="notificacao-vazia"><i class="fas fa-bell-slash"></i><p>Nenhuma notificação</p></div>
                            <?php else: ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                    <div class="notificacao-item <?= $notif['lida'] ? 'lida' : 'nao-lida' ?>" data-id="<?= $notif['id'] ?>">
                                        <div class="notificacao-titulo"><?= htmlspecialchars($notif['titulo']) ?></div>
                                        <div class="notificacao-mensagem"><?= htmlspecialchars($notif['mensagem']) ?></div>
                                        <div class="notificacao-data"><?= date('d/m/Y H:i', strtotime($notif['data_criacao'])) ?></div>
                                        <?php if ($notif['link']): ?><a href="<?= htmlspecialchars($notif['link']) ?>" class="notificacao-link">Ver detalhes</a><?php endif; ?>
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
        <?php if ($total_canceladas_auto > 0): ?>
        <div class="alert alert-warning" style="margin-top:20px;"><i class="fas fa-exclamation-triangle"></i> <strong>Atenção:</strong> <?= $total_canceladas_auto ?> aula(s) canceladas automaticamente. <button onclick="verAulasCanceladas()" class="btn btn-sm btn-warning" style="margin-left:10px;"><i class="fas fa-eye"></i> Ver aulas</button></div>
        <?php endif; ?>
        <div class="stats-grid"><div class="stat-card"><div class="stat-number"><?= count($alunos_com_horarios) ?></div><div class="stat-label">Total de Alunos</div></div><div class="stat-card"><div class="stat-number"><?= $result_aulas->num_rows ?></div><div class="stat-label">Aulas Registradas</div></div></div>
        <?php if (empty($alunos_com_horarios)): ?><div class="dashboard-card no-alunos"><i class="fas fa-user-graduate"></i><h3>Nenhum aluno atribuído</h3><p> Aguarde atribuição do administrador.</p></div><?php endif; ?>
    </section>

    <!-- Secção Meus Alunos (original) -->
    <section id="meus_alunos" class="dashboard-section hidden">
        <h2 class="section-title">Meus Alunos</h2>
        <div class="navegacao-mes">
            <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>" class="btn-mes"><i class="fas fa-chevron-left"></i> <?= $meses_portugues[$mes_anterior] ?></a>
            <h3><i class="fas fa-calendar-alt"></i> <?= $meses_portugues[$mes_atual] ?> <?= $ano_atual ?></h3>
            <a href="?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?>" class="btn-mes"><?= $meses_portugues[$mes_proximo] ?> <i class="fas fa-chevron-right"></i></a>
        </div>
        <?php if (empty($alunos_com_horarios)): ?>
            <div class="dashboard-card no-alunos"><i class="fas fa-user-graduate"></i><h3>Nenhum aluno atribuído</h3></div>
        <?php else: ?>
        <div class="dashboard-card">
            <div class="alert alert-info">Você tem <strong><?= count($alunos_com_horarios) ?></strong> aluno(s) atribuído(s)</div>
            <div class="table-responsive">
                <table class="tabela-aulas">
                    <thead><tr><th>Nome do Aluno</th><th>Calendário de Aulas (<?= $meses_portugues[$mes_atual] ?>)</th><th>Regime</th><th>Dificuldades</th></tr></thead>
                    <tbody>
                        <?php foreach($alunos_com_horarios as $aluno): 
                            $pacote_display = match($aluno['pacote'] ?? '') {
                                'basico' => 'Básico (2x/semana)',
                                'intermedio' => 'Intermediário (3x/semana)',
                                'premium' => 'Premium (4x/semana)',
                                default => htmlspecialchars($aluno['pacote'] ?? '')
                            };
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($aluno['nome']) ?></strong><br><small>Classe: <?= htmlspecialchars($aluno['classe'] ?? '') ?> | <?= htmlspecialchars($aluno['escola'] ?? '') ?></small><div class="info-pacote"><span class="badge-disciplinas"><?= $pacote_display ?></span></div></td>
                            <td><?= gerarCalendario($aluno['id'], $aluno['horarios'], $aluno['aulas_mes'], $mes_atual, $ano_atual) ?></td>
                            <td><div><?php if ($aluno['regime_presencial'] ?? 0): ?><span class="regime-tag">Presencial</span><?php endif; ?><?php if ($aluno['regime_online'] ?? 0): ?><span class="regime-tag">Online</span><?php endif; ?><?php if ($aluno['regime_domicilio'] ?? 0): ?><span class="regime-tag">Domicílio</span><?php endif; ?></div></td>
                            <td><div class="dificuldade-box"><?= htmlspecialchars($aluno['dificuldade'] ?? '') ?></div></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- ===== SECÇÃO MEUS RECURSOS (NOVA) ===== -->
    <section id="meus_recursos" class="dashboard-section hidden">
        <h2 class="section-title"><i class="fas fa-folder-open"></i> Meus Recursos Partilhados</h2>
        <div class="dashboard-card">
            <div style="margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="abrirModalNovoRecurso()"><i class="fas fa-upload"></i> Partilhar Novo Recurso</button>
            </div>
            <div id="lista-recursos-professor">
                <div class="loading">Carregando recursos...</div>
            </div>
        </div>
    </section>

    <!-- ===== SECÇÃO TRABALHOS DOS ALUNOS (NOVA) ===== -->
    <section id="trabalhos_alunos" class="dashboard-section hidden">
        <h2 class="section-title"><i class="fas fa-tasks"></i> Trabalhos Submetidos pelos Alunos</h2>
        <div class="dashboard-card">
            <div id="lista-trabalhos-professor">
                <div class="loading">Carregando trabalhos...</div>
            </div>
        </div>
    </section>

    <!-- Secção Perfil (original) -->
    <section id="perfil" class="dashboard-section hidden">
        <h2 class="section-title">Perfil</h2>
        <div class="dashboard-card"><p>Em breve será possível visualizar e atualizar seus dados.</p></div>
    </section>
</main>

<!-- MODAL PARA CRIAR/EDITAR RECURSO -->
<div id="modalRecurso" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="modalRecursoTitulo"><i class="fas fa-plus"></i> Partilhar Recurso</h3>
            <button class="close" onclick="fecharModal('modalRecurso')">&times;</button>
        </div>
        <form id="formRecurso" enctype="multipart/form-data">
            <input type="hidden" name="acao" id="recurso_acao" value="upload_recurso">
            <input type="hidden" name="recurso_id" id="recurso_id" value="0">
            <div class="form-group">
                <label>Aluno:</label>
                <select name="ficha_id" id="recurso_ficha_id" class="form-control" required>
                    <option value="">Selecione o aluno</option>
                    <?php foreach($alunos_com_horarios as $aluno): ?>
                        <option value="<?= $aluno['id'] ?>"><?= htmlspecialchars($aluno['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Título:</label>
                <input type="text" name="titulo" id="recurso_titulo" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Descrição (opcional):</label>
                <textarea name="descricao" id="recurso_descricao" rows="3" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Tipo:</label>
                <select name="tipo" id="recurso_tipo" class="form-control" onchange="toggleTipoRecurso()">
                    <option value="pdf">PDF</option>
                    <option value="doc">Documento Word</option>
                    <option value="image">Imagem</option>
                    <option value="link">Link</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div class="form-group" id="grupo_ficheiro">
                <label>Ficheiro:</label>
                <input type="file" name="ficheiro" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt,.zip">
            </div>
            <div class="form-group" id="grupo_link" style="display:none;">
                <label>URL do Link:</label>
                <input type="url" name="link" class="form-control" placeholder="https://...">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="fecharModal('modalRecurso')">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="salvarRecurso()">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PARA AVALIAR TRABALHO -->
<div id="modalAvaliar" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> Avaliar Trabalho</h3>
            <button class="close" onclick="fecharModal('modalAvaliar')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="avaliar_trabalho_id">
            <div class="form-group">
                <label>Nota (0-20):</label>
                <input type="number" id="avaliar_nota" class="form-control" step="0.5" min="0" max="20" required>
            </div>
            <div class="form-group">
                <label>Feedback (opcional):</label>
                <textarea id="avaliar_feedback" rows="3" class="form-control"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-secondary" onclick="fecharModal('modalAvaliar')">Cancelar</button>
                <button class="btn btn-success" onclick="confirmarAvaliacao()">Salvar Avaliação</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAIS ORIGINAIS (mantidos) -->
<div id="modalDetalhes" class="modal"><div class="modal-content" style="max-width: 750px;"><div class="modal-header"><h3><i class="fas fa-info-circle"></i> Detalhes da Aula</h3><button class="close" onclick="fecharModal('modalDetalhes')">&times;</button></div><div class="modal-body" id="modal-body-conteudo"></div></div></div>
<div id="modalDada" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-check-circle"></i> Registrar Aula como Realizada</h3><button class="close" onclick="fecharModal('modalDada')">&times;</button></div><form id="formConcluirAula"><input type="hidden" name="aula_id" id="dada_aula_id"><input type="hidden" name="acao_aula" value="concluir"><div class="aula-info"><p><strong>Aluno:</strong> <span id="dada_aluno_nome"></span></p><p><strong>Data/Hora:</strong> <span id="dada_data_hora"></span></p></div><div class="field-group"><h4><i class="fas fa-bolt"></i> Seleção Rápida de Disciplinas</h4><div class="disciplinas-rapidas" id="dada-disciplinas-rapidas"><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Matemática')"><i class="fas fa-calculator"></i> Matemática</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Português')"><i class="fas fa-book"></i> Português</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Inglês')"><i class="fas fa-language"></i> Inglês</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Física')"><i class="fas fa-atom"></i> Física</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Química')"><i class="fas fa-flask"></i> Química</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'História')"><i class="fas fa-landmark"></i> História</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Geografia')"><i class="fas fa-globe-americas"></i> Geografia</span><span class="disciplina-tag" onclick="toggleDisciplinaRapidaDada(this, 'Biologia')"><i class="fas fa-dna"></i> Biologia</span></div></div><div class="field-group"><h4><i class="fas fa-book"></i> Disciplinas</h4><div id="dada-disciplinas-container"></div></div><div class="field-group"><h4><i class="fas fa-clipboard"></i> Observações</h4><div class="form-group"><textarea id="dada_observacoes" rows="3" placeholder="Observações sobre a aula..."></textarea></div></div><div style="display:flex; gap:10px; justify-content:flex-end;"><button type="button" class="btn btn-danger" onclick="fecharModal('modalDada')">Cancelar</button><button type="button" class="btn btn-success" onclick="concluirAula()"><i class="fas fa-check"></i> Concluir Aula</button></div></form></div></div>
<div id="modalRegistro" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-plus-circle"></i> Registrar Nova Aula</h3><button class="close" onclick="fecharModal('modalRegistro')">&times;</button></div><div id="registro-loading" style="display:none; text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin fa-3x" style="color:#3498db"></i><p>Registrando aula...</p></div><div id="registro-form-container"><form id="formRegistroAula"><input type="hidden" name="acao_aula" value="registrar_multiplas"><input type="hidden" name="ficha_id" id="registro_ficha_id"><input type="hidden" name="data_hora" id="registro_data_hora"><div class="aula-info"><p><strong>Aluno:</strong> <span id="nome_aluno_registro"></span></p><p><strong>Data/Hora:</strong> <span id="data_hora_display"></span></p><p><strong>Horário padrão:</strong> <span id="horario_padrao_display"></span></p></div><div class="field-group"><h4><i class="fas fa-bolt"></i> Seleção Rápida de Disciplinas</h4><div class="disciplinas-rapidas"><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Matemática')"><i class="fas fa-calculator"></i> Matemática</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Português')"><i class="fas fa-book"></i> Português</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Inglês')"><i class="fas fa-language"></i> Inglês</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Física')"><i class="fas fa-atom"></i> Física</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Química')"><i class="fas fa-flask"></i> Química</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'História')"><i class="fas fa-landmark"></i> História</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Geografia')"><i class="fas fa-globe-americas"></i> Geografia</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Biologia')"><i class="fas fa-dna"></i> Biologia</span><span class="disciplina-tag" onclick="toggleDisciplinaRapida(this, 'Francês')"><i class="fas fa-language"></i> Francês</span></div></div><div class="field-group"><h4><i class="fas fa-book"></i> Disciplinas Selecionadas</h4><div id="disciplinas-selecionadas-container"></div></div><div class="field-group"><h4><i class="fas fa-clipboard"></i> Observações Gerais</h4><div class="form-group"><textarea id="observacoes_gerais" rows="3" placeholder="Observações sobre o comportamento, pontualidade, material necessário..."></textarea></div></div><div style="display:flex; gap:10px; justify-content:flex-end;"><button type="button" class="btn btn-danger" onclick="fecharModal('modalRegistro')">Cancelar</button><button type="button" class="btn btn-success" onclick="registrarAula()"><i class="fas fa-save"></i> Registrar Aula</button></div></form></div></div></div>
<div id="modalCancelarAntecipado" class="modal"><div class="modal-content" style="max-width: 500px;"><div class="modal-header"><h3><i class="fas fa-times-circle"></i> Cancelar Aula com Antecedência</h3><button class="close" onclick="fecharModal('modalCancelarAntecipado')">&times;</button></div><div class="aula-info"><p><strong>Aluno:</strong> <span id="cancelar_aluno_nome"></span></p><p><strong>Data/Hora:</strong> <span id="cancelar_data_hora"></span></p><input type="hidden" id="cancelar_aula_id"></div><div class="field-group"><h4><i class="fas fa-comment"></i> Motivo do Cancelamento</h4><div class="form-group"><select id="motivo_cancelamento" class="form-control"><option value="">Selecione um motivo...</option><option value="Problemas de saúde">Problemas de saúde</option><option value="Compromisso pessoal">Compromisso pessoal</option><option value="Falha técnica">Falha técnica</option><option value="Emergência familiar">Emergência familiar</option><option value="Outro">Outro</option></select><textarea id="motivo_outro" rows="3" style="display:none;" placeholder="Descreva o motivo..."></textarea></div></div><div class="alert alert-warning"><i class="fas fa-info-circle"></i> O aluno será notificado. Um crédito de reposição será gerado.</div><div style="display:flex; gap:10px;"><button class="btn btn-secondary" onclick="fecharModal('modalCancelarAntecipado')">Voltar</button><button class="btn btn-danger" onclick="confirmarCancelamentoAntecipado()">Confirmar Cancelamento</button></div></div></div>
<div id="modalAulasAluno" class="modal"><div class="modal-content" style="max-width:800px;"><div class="modal-header"><h3><i class="fas fa-calendar-alt"></i> Aulas do Aluno</h3><button class="close" onclick="fecharModal('modalAulasAluno')">&times;</button></div><div class="aula-info"><p><strong>Aluno:</strong> <span id="nome_aluno_aulas"></span></p><p>Lista de todas as aulas registradas para este aluno.</p></div><div id="conteudo_aulas_aluno"><div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Carregando aulas...</p></div></div><div style="display:flex; justify-content:flex-end;"><button class="btn btn-secondary" onclick="fecharModal('modalAulasAluno')">Fechar</button></div></div></div>

<style>
    .notificacoes-container { position: relative; display: inline-block; margin-right: 10px; }
    .notificacoes-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; position: relative; color: #2c3e50; padding: 8px; }
    .notificacoes-badge { position: absolute; top: 0; right: 0; background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.7rem; }
    .notificacoes-dropdown { position: absolute; top: 100%; right: 0; width: 350px; background: white; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); z-index: 1000; margin-top: 10px; border: 1px solid #eef2f6; display: none; }
    .notificacoes-dropdown.active { display: block; }
    .notificacoes-header { padding: 15px; border-bottom: 1px solid #eef2f6; display: flex; justify-content: space-between; background: #f8fafd; border-radius: 12px 12px 0 0; }
    .notificacoes-lista { max-height: 350px; overflow-y: auto; }
    .notificacao-item { padding: 15px; border-bottom: 1px solid #eef2f6; cursor: pointer; }
    .notificacao-item.nao-lida { background: #ebf5ff; border-left: 3px solid #3498db; }
    .notificacao-titulo { font-weight: 600; margin-bottom: 5px; }
    .notificacao-mensagem { font-size: 0.85rem; color: #7f8c8d; margin-bottom: 8px; }
    .notificacao-data { font-size: 0.7rem; color: #95a5a6; }
    .loading { text-align: center; padding: 20px; color: #7f8c8d; }
    .alert { padding: 12px; border-radius: 8px; margin: 10px 0; }
    .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
    .alert-info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
    .alert-success { background: #d4edda; border-left: 4px solid #28a745; }
    .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.9rem; }
    .btn-primary { background: #3498db; color: white; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-success { background: #27ae60; color: white; }
    .btn-secondary { background: #95a5a6; color: white; }
    .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
</style>

<script>
    // ========== FUNÇÕES PARA RECURSOS E TRABALHOS ==========
    const HANDLER_URL = 'Recursos_Academicos/recursos_handler.php';

    function carregarRecursosProfessor() {
        fetch(`${HANDLER_URL}?acao=listar_todos_recursos`)
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('lista-recursos-professor');
                if (!data.success) {
                    container.innerHTML = `<div class="alert alert-danger">Erro: ${data.message}</div>`;
                    return;
                }
                if (data.recursos.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">Nenhum recurso partilhado ainda.</div>';
                    return;
                }
                let html = '<div class="lista-recursos">';
                data.recursos.forEach(r => {
                    let downloadLink = (r.tipo === 'link') ? `<a href="${escapeHtml(r.ficheiro)}" target="_blank" class="btn-sm btn-info">🔗 Abrir Link</a>` : `<a href="${HANDLER_URL}?acao=baixar_recurso&id=${r.id}" class="btn-sm btn-primary">📥 Baixar</a>`;
                    html += `<div class="recurso-item">
                                <strong>${escapeHtml(r.titulo)}</strong><br>
                                <small>${escapeHtml(r.descricao || '')}</small><br>
                                <small>Aluno: ${escapeHtml(r.aluno_nome)} | Tipo: ${r.tipo} | Data: ${r.data_formatada}</small><br>
                                ${downloadLink}
                                <button class="btn-sm btn-danger" onclick="apagarRecurso(${r.id})"><i class="fas fa-trash"></i> Apagar</button>
                            </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            })
            .catch(err => document.getElementById('lista-recursos-professor').innerHTML = '<div class="alert alert-danger">Erro ao carregar recursos.</div>');
    }

    function carregarTrabalhosProfessor() {
        fetch(`${HANDLER_URL}?acao=listar_trabalhos`)
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('lista-trabalhos-professor');
                if (!data.success) {
                    container.innerHTML = `<div class="alert alert-danger">Erro: ${data.message}</div>`;
                    return;
                }
                if (data.trabalhos.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">Nenhum trabalho submetido ainda.</div>';
                    return;
                }
                let html = '<div class="lista-trabalhos">';
                data.trabalhos.forEach(t => {
                    let downloadLink = '';
                    if (t.ficheiro) downloadLink = `<a href="${HANDLER_URL}?acao=baixar_trabalho&id=${t.id}" class="btn-sm btn-primary">📥 Baixar</a>`;
                    else if (t.link) downloadLink = `<a href="${t.link}" target="_blank" class="btn-sm btn-info">🔗 Abrir Link</a>`;
                    let statusBadge = t.status === 'avaliado' ? '<span class="badge-status badge-avaliado">Avaliado</span>' : '<span class="badge-status badge-pendente">Pendente</span>';
                    html += `<div class="trabalho-item">
                                <strong>${escapeHtml(t.titulo)}</strong><br>
                                <small>${escapeHtml(t.descricao || '')}</small><br>
                                <small>Aluno: ${escapeHtml(t.aluno_nome)} | Data: ${t.data_formatada} | Nota: ${t.nota !== null ? t.nota : '-'}</small><br>
                                ${statusBadge}<br>
                                ${downloadLink}
                                ${t.status !== 'avaliado' ? `<button class="btn-sm btn-success" onclick="abrirModalAvaliar(${t.id})"><i class="fas fa-star"></i> Avaliar</button>` : ''}
                            </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            })
            .catch(err => document.getElementById('lista-trabalhos-professor').innerHTML = '<div class="alert alert-danger">Erro ao carregar trabalhos.</div>');
    }

    function abrirModalNovoRecurso() {
        document.getElementById('modalRecursoTitulo').innerHTML = '<i class="fas fa-upload"></i> Partilhar Recurso';
        document.getElementById('recurso_acao').value = 'upload_recurso';
        document.getElementById('recurso_id').value = 0;
        document.getElementById('formRecurso').reset();
        toggleTipoRecurso();
        abrirModal('modalRecurso');
    }

    function toggleTipoRecurso() {
        const tipo = document.getElementById('recurso_tipo').value;
        const grupoFicheiro = document.getElementById('grupo_ficheiro');
        const grupoLink = document.getElementById('grupo_link');
        if (tipo === 'link') {
            grupoFicheiro.style.display = 'none';
            grupoLink.style.display = 'block';
            document.querySelector('input[name="ficheiro"]').required = false;
            document.querySelector('input[name="link"]').required = true;
        } else {
            grupoFicheiro.style.display = 'block';
            grupoLink.style.display = 'none';
            document.querySelector('input[name="ficheiro"]').required = true;
            document.querySelector('input[name="link"]').required = false;
        }
    }

    function salvarRecurso() {
        const form = document.getElementById('formRecurso');
        const formData = new FormData(form);
        const acao = document.getElementById('recurso_acao').value;
        formData.append('acao', acao);
        // Se for tipo link, enviar o link como 'ficheiro' (o handler espera 'ficheiro' para link também)
        if (document.getElementById('recurso_tipo').value === 'link') {
            const link = document.querySelector('input[name="link"]').value;
            formData.set('ficheiro', link);
        }
        fetch(HANDLER_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Recurso salvo com sucesso!');
                    fecharModal('modalRecurso');
                    carregarRecursosProfessor();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(err => alert('Erro de comunicação.'));
    }

    function apagarRecurso(recursoId) {
        if (!confirm('Tem certeza que deseja apagar este recurso?')) return;
        const formData = new FormData();
        formData.append('acao', 'apagar_recurso');
        formData.append('recurso_id', recursoId);
        fetch(HANDLER_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Recurso apagado.');
                    carregarRecursosProfessor();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
    }

    function abrirModalAvaliar(trabalhoId) {
        document.getElementById('avaliar_trabalho_id').value = trabalhoId;
        document.getElementById('avaliar_nota').value = '';
        document.getElementById('avaliar_feedback').value = '';
        abrirModal('modalAvaliar');
    }

    function confirmarAvaliacao() {
        const trabalhoId = document.getElementById('avaliar_trabalho_id').value;
        const nota = parseFloat(document.getElementById('avaliar_nota').value);
        const feedback = document.getElementById('avaliar_feedback').value;
        if (isNaN(nota) || nota < 0 || nota > 20) {
            alert('Nota inválida. Use um número entre 0 e 20.');
            return;
        }
        const formData = new FormData();
        formData.append('acao', 'avaliar_trabalho');
        formData.append('trabalho_id', trabalhoId);
        formData.append('nota', nota);
        formData.append('feedback', feedback);
        fetch(HANDLER_URL, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Trabalho avaliado com sucesso!');
                    fecharModal('modalAvaliar');
                    carregarTrabalhosProfessor();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
    }

    function fecharModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    function abrirModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    function toggleNotificacoes() {
        const dropdown = document.getElementById('notificacoesDropdown');
        dropdown.classList.toggle('active');
    }

    function marcarTodasComoLidas() {
        fetch('notificacoes.php?acao=marcar_todas&tipo=professor')
            .then(() => location.reload());
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // ========== FUNÇÕES ORIGINAIS (professor.js) ==========
    // As funções registrarAula, concluirAula, confirmarCancelamentoAntecipado, etc.
    // serão mantidas do ficheiro professor.js original.
    // Para não quebrar, vamos garantir que existem (se não, implementamos stubs)
    if (typeof registrarAula === 'undefined') {
        window.registrarAula = function() { alert('Função registrarAula deve ser implementada em professor.js'); };
        window.concluirAula = function() { alert('Função concluirAula deve ser implementada em professor.js'); };
        window.confirmarCancelamentoAntecipado = function() { alert('Função confirmarCancelamentoAntecipado deve ser implementada em professor.js'); };
        window.toggleDisciplinaRapida = function() {};
        window.toggleDisciplinaRapidaDada = function() {};
        window.verAulasCanceladas = function() {};
    }

    // ========== NAVEGAÇÃO DAS SECÇÕES ==========
    function alternarSecao(secaoId, linkElement) {
        document.querySelectorAll('.dashboard-section').forEach(sec => sec.classList.add('hidden'));
        const target = document.getElementById(secaoId);
        if (target) target.classList.remove('hidden');
        document.querySelectorAll('.sidebar ul li a').forEach(a => a.classList.remove('active'));
        if (linkElement) linkElement.classList.add('active');
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
        // Carregar dados dinâmicos conforme secção
        if (secaoId === 'meus_recursos') carregarRecursosProfessor();
        if (secaoId === 'trabalhos_alunos') carregarTrabalhosProfessor();
    }

    document.querySelectorAll('.sidebar ul li a[data-secao]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            alternarSecao(this.getAttribute('data-secao'), this);
        });
    });

    const menuToggle = document.getElementById('menuToggle');
    const menuClose = document.getElementById('menuClose');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) menuToggle.addEventListener('click', () => { sidebar.classList.add('active'); overlay.classList.add('active'); });
    if (menuClose) menuClose.addEventListener('click', () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); });
    if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); });
    window.addEventListener('resize', () => { if (window.innerWidth > 768) { sidebar.classList.remove('active'); overlay.classList.remove('active'); } });
 
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dashboard-section').forEach(sec => sec.classList.add('hidden'));
        document.getElementById('boas_vindas').classList.remove('hidden');
    });
</script>

<script src="JavaScript/professor.js"></script>
</body>
</html>
<?php
if (isset($stmt_aulas)) $stmt_aulas->close();
$conn->close();
?>