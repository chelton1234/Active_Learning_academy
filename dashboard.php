<?php
// 🔎 Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    die("Acesso negado. Faça login primeiro.");
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// Conexão com o banco
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Incluir funções de pacote e notificações
require_once 'funcoes_pacotes.php';
require_once 'notificacoes.php';

// Buscar ficha do aluno
$sql = "SELECT * FROM fichas WHERE usuario_id = ? ORDER BY data_submissao DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$ficha_id = $row['id'] ?? 0;
$pagamento_status = $row['pagamento_status'] ?? 'pendente';
$data_pagamento = $row['data_pagamento'] ?? null;
$pacote_nome = $row['pacote'] ?? 'Não definido';

// Calcular valor do pacote
$valor_pacote = 0;
switch($pacote_nome) {
    case 'basico':
        $valor_pacote = 3000;
        break;
    case 'intermedio':
        $valor_pacote = 4000;
        break;
    case 'premium':
        $valor_pacote = 5000;
        break;
    default:
        $valor_pacote = 0;
}

$valor_domicilio = ($row['regime_domicilio'] ?? 0) ? 1000 : 0;
$valor_total = $valor_pacote + $valor_domicilio;

// ========== DADOS DAS NOTIFICAÇÕES ==========
$notificacoes_nao_lidas = contarNotificacoesNaoLidas($conn, $usuario_id, 'aluno');
$notificacoes = buscarNotificacoes($conn, $usuario_id, 'aluno', true, 10);

// ========== OBTER MÊS E ANO PARA NAVEGAÇÃO ==========
$mes_exibicao = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano_exibicao = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Validar mês e ano
if ($mes_exibicao < 1) $mes_exibicao = 1;
if ($mes_exibicao > 12) $mes_exibicao = 12;
if ($ano_exibicao < 2020) $ano_exibicao = 2020;
if ($ano_exibicao > 2030) $ano_exibicao = 2030;

// Mês anterior e próximo
$mes_anterior = $mes_exibicao - 1;
$ano_anterior = $ano_exibicao;
if ($mes_anterior < 1) {
    $mes_anterior = 12;
    $ano_anterior--;
}

$mes_proximo = $mes_exibicao + 1;
$ano_proximo = $ano_exibicao;
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

// ========== BUSCAR HORÁRIOS DO ALUNO ==========
$horarios = [];
$dias_semana_array = [];

if ($ficha_id > 0) {
    $sql_horarios = "SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ? ORDER BY 
                    FIELD(dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')";
    $stmt_horarios = $conn->prepare($sql_horarios);
    $stmt_horarios->bind_param("i", $ficha_id);
    $stmt_horarios->execute();
    $result_horarios = $stmt_horarios->get_result();
    
    while ($horario_row = $result_horarios->fetch_assoc()) {
        $horarios[] = $horario_row;
    }
    $stmt_horarios->close();
    
    if (!empty($row['dias_semana'])) {
        $dias_json = $row['dias_semana'];
        $dias_semana_array = json_decode($dias_json, true);
        if (!is_array($dias_semana_array)) {
            $dias_semana_array = [];
        }
    }
}

// ========== BUSCAR INFORMAÇÕES DO PACOTE ==========
$info_pacote_completa = getInfoPacoteCompleta($conn, $ficha_id, $pacote_nome, $data_pacote_inicio ?? null);

// Calcular período do pacote para o calendário
$info_periodo = calcularAulasDoPeriodo($pacote_nome, $data_pacote_inicio ?? null, $horarios);

// Dados para exibição
$pacote_dias_semana = [
    'basico' => '2x/semana',
    'intermedio' => '3x/semana', 
    'premium' => '4x/semana'
][$pacote_nome] ?? '';

// Formatar data de validade
$validade_formatada = '';
$data_inicio_obj = null;
$data_fim_obj = null;

if ($info_pacote_completa['data_fim']) {
    $validade_obj = new DateTime($info_pacote_completa['data_fim']);
    $validade_formatada = $validade_obj->format('d/m/Y');
    $data_fim_obj = $validade_obj;
}

// Data de início formatada
$inicio_formatada = '';
if ($info_pacote_completa['data_inicio']) {
    $inicio_obj = new DateTime($info_pacote_completa['data_inicio']);
    $inicio_formatada = $inicio_obj->format('d/m/Y');
    $data_inicio_obj = $inicio_obj;
}

// Determinar cor do pacote
$pacote_cores = [
    'basico' => '#6c5ce7',
    'intermedio' => '#00b894',
    'premium' => '#fdcb6e'
];
$pacote_cor = $pacote_cores[$pacote_nome] ?? '#667eea';

// Aulas consumidas
$aulas_consumidas = $info_pacote_completa['aulas_contratadas'] - $info_pacote_completa['aulas_restantes'];

// Verificar se precisa renovar
$precisa_renovar = $info_pacote_completa['precisa_renovar'] ?? false;
$dias_restantes = $info_pacote_completa['dias_restantes'] ?? 0;
$expirado = $info_pacote_completa['expirado'] ?? false;

// ========== BUSCAR AULAS DO MÊS DE EXIBIÇÃO PARA O CALENDÁRIO ==========
$sql_aulas_mes = "SELECT DATE(data_hora) as data, status, id, TIME(data_hora) as hora
                  FROM agendamentos_aulas 
                  WHERE aluno_id = ? 
                  AND MONTH(data_hora) = ? 
                  AND YEAR(data_hora) = ?";
$stmt_aulas_mes = $conn->prepare($sql_aulas_mes);
$stmt_aulas_mes->bind_param("iii", $ficha_id, $mes_exibicao, $ano_exibicao);
$stmt_aulas_mes->execute();
$result_aulas_mes = $stmt_aulas_mes->get_result();

$aulas_mes = [];
while ($aula = $result_aulas_mes->fetch_assoc()) {
    $aulas_mes[$aula['data']] = [
        'status' => $aula['status'],
        'id' => $aula['id'],
        'hora' => substr($aula['hora'], 0, 5)
    ];
}
$stmt_aulas_mes->close();

// ========== GERAR DIAS DE AULA DO PERÍODO DO PACOTE ==========
$dias_aula_periodo = [];
if ($data_inicio_obj && $data_fim_obj && !empty($horarios)) {
    $dias_semana_aluno = [];
    foreach ($horarios as $h) {
        $dias_semana_aluno[] = $h['dia_semana'];
    }
    
    $dias_semana_portugues = [
        'sunday' => 'domingo', 'monday' => 'segunda', 'tuesday' => 'terca',
        'wednesday' => 'quarta', 'thursday' => 'quinta', 'friday' => 'sexta',
        'saturday' => 'sabado'
    ];
    
    $periodo = new DatePeriod($data_inicio_obj, new DateInterval('P1D'), $data_fim_obj->modify('+1 day'));
    $data_fim_obj->modify('-1 day'); // Restaurar
    
    foreach ($periodo as $data) {
        $dia_semana_ingles = strtolower($data->format('l'));
        $dia_semana = $dias_semana_portugues[$dia_semana_ingles] ?? '';
        
        if (in_array($dia_semana, $dias_semana_aluno)) {
            $dias_aula_periodo[] = $data->format('Y-m-d');
        }
    }
}

// ========== BUSCAR AULAS COM SEUS ITENS ==========
$sql_aulas = "SELECT a.id, a.data_hora, a.status, a.materia,
                     a.conteudo_abordado as aula_conteudo,
                     a.dificuldades_identificadas as aula_dificuldades,
                     a.observacoes_professor as aula_observacoes,
                     u_prof.nome AS professor_nome,
                     ai.id AS item_id, ai.disciplina, 
                     ai.conteudo_abordado as item_conteudo,
                     ai.dificuldades_identificadas as item_dificuldades,
                     ai.observacoes_professor as item_observacoes
              FROM agendamentos_aulas a
              LEFT JOIN usuarios u_prof ON u_prof.id = a.professor_id
              LEFT JOIN aula_itens ai ON ai.aula_id = a.id
              WHERE a.aluno_id = ?
              ORDER BY a.data_hora DESC, ai.id ASC";
$stmt_aulas = $conn->prepare($sql_aulas);
$stmt_aulas->bind_param("i", $ficha_id);
$stmt_aulas->execute();
$result_aulas = $stmt_aulas->get_result();

// Organizar aulas com seus itens
$aulas = [];
while ($row_aula = $result_aulas->fetch_assoc()) {
    $aula_id = $row_aula['id'];
    
    if (!isset($aulas[$aula_id])) {
        $aulas[$aula_id] = [
            'id' => $row_aula['id'],
            'data_hora' => $row_aula['data_hora'],
            'status' => $row_aula['status'] ?? 'agendado',
            'materia' => $row_aula['materia'] ?? 'Geral',
            'professor_nome' => $row_aula['professor_nome'] ?? '',
            'aula_conteudo' => $row_aula['aula_conteudo'] ?? '',
            'aula_dificuldades' => $row_aula['aula_dificuldades'] ?? '',
            'aula_observacoes' => $row_aula['aula_observacoes'] ?? '',
            'itens' => []
        ];
    }
    
    if (!empty($row_aula['item_id'])) {
        $aulas[$aula_id]['itens'][] = [
            'disciplina' => $row_aula['disciplina'] ?? 'Sem disciplina',
            'conteudo' => $row_aula['item_conteudo'] ?? '',
            'dificuldades' => $row_aula['item_dificuldades'] ?? '',
            'observacoes' => $row_aula['item_observacoes'] ?? ''
        ];
    }
}

// Calcular estatísticas
$total_aulas = count($aulas);
$realizadas = 0;
$agendadas = 0;
$canceladas = 0;
$canceladas_aluno = 0;
$canceladas_professor = 0;
$pendentes_professor = 0;

foreach ($aulas as $aula) {
    switch($aula['status']) {
        case 'realizado': $realizadas++; break;
        case 'agendado': $agendadas++; break;
        case 'cancelado_aluno': $canceladas_aluno++; $canceladas++; break;
        case 'cancelado_professor': $canceladas_professor++; $canceladas++; break;
        case 'pendente_professor': $pendentes_professor++; break;
    }
}

$valor_mensal = $row['valor_mensal'] ?? $valor_pacote;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Aluno</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Passar dados para o JavaScript -->
    <script>
        window.fichaId = <?= $ficha_id ?>;
        window.pagamentoStatus = '<?= $pagamento_status ?>';
        window.valorTotal = <?= $valor_total ?>;
        window.pacoteInfo = <?= json_encode([
            'dias_restantes' => $dias_restantes,
            'expirado' => $expirado,
            'precisa_renovar' => $precisa_renovar,
            'data_inicio' => $inicio_formatada,
            'data_fim' => $validade_formatada
        ]) ?>;
        window.diasAulaPeriodo = <?= json_encode($dias_aula_periodo) ?>;
        window.notificacoesNaoLidas = <?= $notificacoes_nao_lidas ?>;
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
    
    <h2>Menu do Aluno</h2>
    <ul>
        <li><a href="#" onclick="mostrarSecao('boas_vindas', event)" id="link-boas-vindas">
            <i class="fas fa-home"></i> Início
        </a></li>
        <li><a href="#" onclick="mostrarSecao('ficha', event)" id="link-ficha">
            <i class="fas fa-file-alt"></i> Encontrar Professor
        </a></li>
        <li><a href="#" onclick="mostrarSecao('aulas', event)" id="link-aulas" class="active">
            <i class="fas fa-chalkboard-teacher"></i> Minhas Aulas
        </a></li>
        <li><a href="#" onclick="mostrarSecao('financeiro', event)" id="link-financeiro">
            <i class="fas fa-money-bill-wave"></i> Situação Financeira
        </a></li>
        <li><a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a></li>
    </ul>
</aside>

<!-- CONTEÚDO -->
<main class="content">

    <!-- Boas-vindas -->
    <section id="boas_vindas" class="dashboard-section">
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2><i class="fas fa-home"></i> Bem-vindo ao Painel do Estudante</h2>
                
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
            
            <p>👋 Olá, <strong><?= htmlspecialchars($usuario_nome) ?></strong>!</p>
            <p>Use o menu à esquerda para aceder às suas informações.</p>
            
            <!-- NOTIFICAÇÃO DE PAGAMENTO PENDENTE -->
            <?php if ($pagamento_status !== 'pago'): ?>
                <div class="alert alert-warning" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Pagamento Pendente!</strong>
                    <p style="margin: 5px 0 0 0;">Complete o pagamento para que o administrador possa atribuir um professor e iniciar as aulas.</p>
                    <a href="pagamento_form.php?ficha_id=<?= $ficha_id ?>" class="btn btn-warning" style="margin-top: 10px; display: inline-block;">
                        <i class="fas fa-credit-card"></i> Efetuar Pagamento Agora
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- INFO DO PACOTE ATUAL (apenas se já pagou) -->
            <?php if ($pagamento_status === 'pago'): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 25px; background: #f8f9fa; padding: 20px; border-radius: 12px;">
                <div>
                    <small style="color: #7f8c8d;">Período do Pacote</small>
                    <div style="font-weight: 600; color: #2c3e50;">
                        <?= $inicio_formatada ?: '--/--/----' ?> até <?= $validade_formatada ?: '--/--/----' ?>
                    </div>
                </div>
                <div>
                    <small style="color: #7f8c8d;">Dias Restantes</small>
                    <div style="font-weight: 600; color: <?= $dias_restantes <= 7 ? '#e74c3c' : '#27ae60' ?>;">
                        <?= $dias_restantes > 0 ? $dias_restantes . ' dias' : ($expirado ? 'Expirado' : 'Último dia') ?>
                    </div>
                </div>
                <div>
                    <small style="color: #7f8c8d;">Aulas Restantes</small>
                    <div style="font-weight: 600; color: #3498db;">
                        <?= $info_pacote_completa['aulas_restantes'] ?> de <?= $info_pacote_completa['aulas_contratadas'] ?>
                        <?php if ($info_pacote_completa['creditos'] > 0): ?>
                            <span style="background: #f39c12; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">
                                +<?= $info_pacote_completa['creditos'] ?> créditos
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="alert alert-info" style="margin-top: 20px;">
                <i class="fas fa-info-circle"></i> 
                <strong>Informação:</strong> Após a confirmação do pagamento, o administrador atribuirá um professor e as aulas serão agendadas.
            </div>
        </div>
    </section>

    <!-- Minha Ficha -->
    <section id="ficha" class="dashboard-section hidden">
        
        <p>Permita-nos conhecer as tuas dificuldades para te oferecer um professor ideal</p>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="alert alert-success">✅ Ficha atualizada com sucesso!</div>
        <?php endif; ?>

        <?php if ($row): ?>
            <div class="dashboard-card">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nome:</span>
                        <span class="info-value"><?= htmlspecialchars($row['nome']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Idade:</span>
                        <span class="info-value"><?= $row['idade'] ?> anos</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Classe:</span>
                        <span class="info-value"><?= htmlspecialchars($row['classe']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Sexo:</span>
                        <span class="info-value"><?= $row['sexo'] === 'm' ? 'Masculino' : 'Feminino' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Localização:</span>
                        <span class="info-value"><?= htmlspecialchars($row['localizacao']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contacto:</span>
                        <span class="info-value"><?= htmlspecialchars($row['contacto_encarregado'] ?? $row['contacto'] ?? '') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Escola:</span>
                        <span class="info-value"><?= htmlspecialchars($row['escola']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nível:</span>
                        <span class="info-value"><?= $row['nivel'] ?? 'Não definido' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Pacote:</span>
                        <span class="info-value"><?= $row['pacote'] ?? 'Não definido' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fins de Semana:</span>
                        <span class="info-value"><?= ($row['permite_finsemana'] ?? 0) ? 'Sim' : 'Não' ?></span>
                    </div>
                    <div class="info-item full-width">
                        <span class="info-label">Dificuldades:</span>
                        <span class="info-value"><?= htmlspecialchars($row['dificuldade']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Província:</span>
                        <span class="info-value"><?= htmlspecialchars($row['provincia']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Internet:</span>
                        <span class="info-value"><?= $row['internet_casa'] ? 'Sim' : 'Não' ?></span>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <a href="editar_ficha.php?id=<?= $row['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar Ficha
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard-card">
                <p class="text-muted">Nenhuma ficha encontrada.</p>
                <a href="FichaAluno.html" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Preencher Agora
                </a>
            </div>
        <?php endif; ?>
    </section>

    <!-- MINHAS AULAS - APENAS CALENDÁRIO (visível apenas se pagou) -->
    <section id="aulas" class="dashboard-section <?= $pagamento_status !== 'pago' ? 'hidden' : '' ?>">
        <div class="tabela-aulas-wrapper">
            <div class="tabela-header">
                <h3><i class="fas fa-chalkboard-teacher"></i> Minhas Aulas</h3>
                
                <!-- PACOTE DO ALUNO -->
                <div class="pacote-info-header">
                    <i class="fas fa-crown"></i>
                    <div class="pacote-info-content">
                        <span class="pacote-nome">
                            <?php 
                            if (!empty($row['pacote'])) {
                                switch ($row['pacote']) {
                                    case 'basico': echo 'Pacote Básico'; break;
                                    case 'intermedio': echo 'Pacote Intermédio'; break;
                                    case 'premium': echo 'Pacote Premium'; break;
                                    default: echo ucfirst($row['pacote']);
                                }
                            } else {
                                echo 'Pacote não definido';
                            }
                            ?>
                        </span>
                        <span class="pacote-aulas">
                            <?= $info_pacote_completa['aulas_restantes'] ?> de <?= $info_pacote_completa['aulas_contratadas'] ?> aulas
                            <?php if ($info_pacote_completa['creditos'] > 0): ?>
                                <span class="credito-badge">+<?= $info_pacote_completa['creditos'] ?> créditos</span>
                            <?php endif; ?>
                        </span>
                        <?php if ($validade_formatada): ?>
                            <span class="pacote-validade <?= $dias_restantes <= 7 ? 'validade-proxima' : '' ?>">
                                válido até <?= $validade_formatada ?>
                                <?php if ($dias_restantes > 0 && $dias_restantes <= 7): ?>
                                    (<?= $dias_restantes ?> dias)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- CARD DO CALENDÁRIO -->
            <div style="padding: 20px; display: flex; justify-content: center;">
                <div class="calendario-card">
                    <!-- Cabeçalho do card -->
                    <div class="calendario-card-header">
                        <div class="calendario-card-titulo">
                            <i class="fas fa-calendar-alt" style="color: <?= $pacote_cor ?>;"></i>
                            <h2>Meu Calendário de Aulas</h2>
                        </div>
                        <div class="calendario-card-status">
                            <?php if ($info_pacote_completa['status_pagamento'] === 'pago'): ?>
                                <span class="status-badge ativo <?= $expirado ? 'expirado' : '' ?>">
                                    <i class="fas fa-<?= $expirado ? 'exclamation-circle' : 'check-circle' ?>"></i> 
                                    <?= $expirado ? 'Expirado' : 'Ativo' ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge pendente"><i class="fas fa-hourglass-half"></i> Pendente</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informações do pacote -->
                    <div class="calendario-info-pacote">
                        <div class="info-pacote-item">
                            <span class="info-label">Pacote</span>
                            <span class="info-valor" style="color: <?= $pacote_cor ?>;"><?= ucfirst($pacote_nome) ?></span>
                            <span class="info-detalhe"><?= $pacote_dias_semana ?></span>
                        </div>
                        <div class="info-pacote-item">
                            <span class="info-label">Horário</span>
                            <span class="info-valor"><?= !empty($horarios) ? htmlspecialchars($horarios[0]['horario']) : '--:--' ?></span>
                            <span class="info-detalhe">fixo</span>
                        </div>
                        <div class="info-pacote-item">
                            <span class="info-label">Válido de</span>
                            <span class="info-valor"><?= $inicio_formatada ?: '--/--/----' ?></span>
                            <span class="info-detalhe">até <?= $validade_formatada ?: '--/--/----' ?></span>
                        </div>
                    </div>
                    
                    <!-- Navegação do Calendário -->
                    <div class="calendario-navegacao">
                        <a href="?mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>" class="btn-navegacao">
                            <i class="fas fa-chevron-left"></i> <?= $meses_portugues[$mes_anterior] ?>
                        </a>
                        <h4><?= $meses_portugues[$mes_exibicao] ?> <?= $ano_exibicao ?></h4>
                        <a href="?mes=<?= $mes_proximo ?>&ano=<?= $ano_proximo ?>" class="btn-navegacao">
                            <?= $meses_portugues[$mes_proximo] ?> <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    
                    <!-- CALENDÁRIO -->
                    <div class="calendario-container">
                        <div class="calendario-dias-semana">
                            <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                        </div>
                        
                        <div class="calendario-grid" id="calendario-aulas">
                            <?php
                            // Dados para o calendário
                            $primeiro_dia = mktime(0, 0, 0, $mes_exibicao, 1, $ano_exibicao);
                            $dias_no_mes = date('t', $primeiro_dia);
                            $dia_semana_inicio = date('w', $primeiro_dia);
                            
                            // Dias da semana em português
                            $dias_semana_portugues = [
                                'sunday' => 'domingo', 'monday' => 'segunda', 'tuesday' => 'terca',
                                'wednesday' => 'quarta', 'thursday' => 'quinta', 'friday' => 'sexta',
                                'saturday' => 'sabado'
                            ];
                            
                            // Dias que o aluno tem aula
                            $dias_aula = [];
                            foreach ($horarios as $h) {
                                $dias_aula[] = $h['dia_semana'];
                            }
                            
                            // Preencher dias vazios no início
                            for ($i = 0; $i < $dia_semana_inicio; $i++) {
                                echo '<div class="calendario-dia vazio"></div>';
                            }
                            
                            // Preencher os dias do mês
                            $hoje = date('Y-m-d');
                            $agora = new DateTime();
                            
                            for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
                                $data_atual = sprintf("%04d-%02d-%02d", $ano_exibicao, $mes_exibicao, $dia);
                                $timestamp = mktime(0, 0, 0, $mes_exibicao, $dia, $ano_exibicao);
                                $dia_semana_ingles = strtolower(date('l', $timestamp));
                                $dia_semana = $dias_semana_portugues[$dia_semana_ingles] ?? '';
                                
                                $tem_aula = in_array($dia_semana, $dias_aula);
                                $dentro_periodo = false;
                                
                                // Verificar se a data está dentro do período do pacote
                                if ($data_inicio_obj && $data_fim_obj) {
                                    $data_atual_obj = new DateTime($data_atual);
                                    $dentro_periodo = ($data_atual_obj >= $data_inicio_obj && $data_atual_obj <= $data_fim_obj);
                                }
                                
                                $hoje_flag = ($data_atual == $hoje);
                                $is_futuro = ($data_atual > $hoje);
                                
                                $aula_existente = $aulas_mes[$data_atual] ?? null;
                                $status_aula = $aula_existente['status'] ?? null;
                                $aula_id = $aula_existente['id'] ?? null;
                                $hora_aula = $aula_existente['hora'] ?? (!empty($horarios) ? $horarios[0]['horario'] : '');
                                
                                $classes = ['calendario-dia'];
                                if ($tem_aula && $dentro_periodo) $classes[] = 'dia-aula';
                                if ($hoje_flag) $classes[] = 'hoje';
                                if ($status_aula === 'realizado') $classes[] = 'aula-realizada';
                                if ($status_aula === 'cancelado_aluno') $classes[] = 'aula-cancelada-aluno';
                                if ($status_aula === 'cancelado_professor') $classes[] = 'aula-cancelada-professor';
                                if ($status_aula === 'pendente_professor') $classes[] = 'aula-pendente-professor';
                                if ($aula_existente) $classes[] = 'tem-aula';
                                if (!$dentro_periodo) $classes[] = 'fora-periodo';
                                
                                // ===== ATRIBUTOS DATA =====
                                $data_attributes = "data-dia='$dia' data-data='$data_atual' data-horario='$hora_aula'";
                                
                                if ($aula_id) {
                                    $data_attributes .= " data-aula-id='$aula_id'";
                                }
                                
                                if ($status_aula) {
                                    $data_attributes .= " data-status='$status_aula'";
                                }
                                
                                // REGRA PARA CANCELAR
                                if ($aula_id && $is_futuro && $status_aula === 'agendado' && $dentro_periodo) {
                                    $data_attributes .= " data-pode-cancelar='true'";
                                }
                                
                                $data_attributes .= " data-debug-is-futuro='".($is_futuro?'true':'false')."'";
                                $data_attributes .= " data-debug-dentro-periodo='".($dentro_periodo?'true':'false')."'";
                                ?>
                                
                                <div class="<?= implode(' ', $classes) ?>" 
                                     <?= $data_attributes ?>
                                     onclick="abrirModalDia(this)">
                                    
                                    <span class="dia-numero"><?= $dia ?></span>
                                    
                                    <!-- Ícones de status -->
                                    <?php if ($status_aula === 'realizado'): ?>
                                        <span class="icone-status realizado" title="Aula realizada">✓</span>
                                    <?php elseif ($status_aula === 'cancelado_aluno'): ?>
                                        <span class="icone-status cancelado-aluno" title="Cancelada pelo aluno">✕</span>
                                    <?php elseif ($status_aula === 'cancelado_professor'): ?>
                                        <span class="icone-status cancelado-professor" title="Cancelada pelo professor">⌧</span>
                                    <?php elseif ($status_aula === 'pendente_professor'): ?>
                                        <span class="icone-status pendente" title="Aula pendente - Professor deve reposição">⏰</span>
                                    <?php elseif ($aula_id && $is_futuro && $status_aula === 'agendado'): ?>
                                        <span class="icone-status agendado" title="Aula agendada - Clique para cancelar">⏳</span>
                                    <?php elseif ($tem_aula && !$aula_existente): ?>
                                        <?php if ($is_futuro): ?>
                                            <span class="icone-status agendado" title="Aula prevista">⏳</span>
                                        <?php else: ?>
                                            <span class="icone-status cancelado-aluno" title="Aula não registrada">✕</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                </div>
                            <?php } ?>
                        </div>
                        
                        <!-- Legenda -->
                        <div class="calendario-legenda">
                            <span class="legenda-item"><span class="legenda-cor aula-agendada"></span> Agendada (pode cancelar)</span>
                            <span class="legenda-item"><span class="legenda-cor aula-realizada"></span> Realizada</span>
                            <span class="legenda-item"><span class="legenda-cor aula-cancelada-aluno"></span> Cancelada (aluno)</span>
                            <span class="legenda-item"><span class="legenda-cor aula-cancelada-prof"></span> Cancelada (prof)</span>
                            <span class="legenda-item"><span class="legenda-cor aula-pendente-prof"></span> Pendente (prof)</span>
                            <span class="legenda-item"><span class="legenda-cor hoje-legend"></span> Hoje</span>
                            <span class="legenda-item"><span class="legenda-cor fora-periodo"></span> Fora do período</span>
                        </div>
                    </div>
                    
                    <!-- Resumo de aulas -->
                    <div class="calendario-resumo">
                        <div class="resumo-item">
                            <span class="resumo-label">Aulas no período</span>
                            <span class="resumo-valor"><?= $info_pacote_completa['aulas_contratadas'] ?></span>
                        </div>
                        <div class="resumo-item">
                            <span class="resumo-label">Realizadas</span>
                            <span class="resumo-valor"><?= $info_pacote_completa['aulas_realizadas'] ?></span>
                        </div>
                        <div class="resumo-item">
                            <span class="resumo-label">Restantes</span>
                            <span class="resumo-valor"><?= $info_pacote_completa['aulas_restantes'] ?></span>
                        </div>
                        <?php if ($pendentes_professor > 0): ?>
                            <div class="resumo-item pendentes">
                                <span class="resumo-label">Pendentes</span>
                                <span class="resumo-valor"><?= $pendentes_professor ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($info_pacote_completa['creditos'] > 0): ?>
                            <div class="resumo-item creditos">
                                <span class="resumo-label">Créditos</span>
                                <span class="resumo-valor">+<?= $info_pacote_completa['creditos'] ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Informação do período -->
                    <div class="periodo-info">
                        <i class="fas fa-info-circle"></i>
                        Apenas dias de <strong><?= $inicio_formatada ?></strong> até <strong><?= $validade_formatada ?></strong> fazem parte do seu pacote.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Situação Financeira -->
    <section id="financeiro" class="dashboard-section">
        <h2><i class="fas fa-money-bill-wave"></i> Situação Financeira</h2>
        
        <?php if ($row): ?>
            <div class="dashboard-card">
                <h3><i class="fas fa-credit-card"></i> Status do Pagamento</h3>
                
                <!-- STATUS PENDENTE -->
                <?php if ($pagamento_status !== 'pago'): ?>
                    <div class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div style="text-align: center;">
                                <i class="fas fa-hourglass-half" style="font-size: 3rem; color: #f39c12;"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 10px 0; color: #856404;">Pagamento Pendente</h3>
                                <p style="margin: 0; color: #856404;">O pagamento ainda não foi realizado. Após a confirmação, o administrador poderá atribuir um professor.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-pendente">
                                    <i class="fas fa-hourglass-half"></i> Pendente
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Pacote Selecionado:</span>
                            <span class="info-value">
                                <?php 
                                switch($pacote_nome) {
                                    case 'basico': echo 'Básico (2x/semana)'; break;
                                    case 'intermedio': echo 'Intermediário (3x/semana)'; break;
                                    case 'premium': echo 'Premium (4x/semana)'; break;
                                    default: echo ucfirst($pacote_nome);
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Valor do Pacote:</span>
                            <span class="info-value"><?= number_format($valor_pacote, 0, ',', '.') ?> MT</span>
                        </div>
                        <?php if ($valor_domicilio > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Taxa Domicílio:</span>
                            <span class="info-value">+ <?= number_format($valor_domicilio, 0, ',', '.') ?> MT</span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Valor Total:</span>
                            <span class="info-value" style="font-size: 1.2rem; font-weight: bold; color: #2c3e50;"><?= number_format($valor_total, 0, ',', '.') ?> MT</span>
                        </div>
                    </div>
                    
                    <!-- BOTÃO DE PAGAMENTO -->
                    <div style="margin-top: 25px; text-align: center;">
                        <a href="pagamento_form.php?ficha_id=<?= $ficha_id ?>" class="btn btn-primary" style="display: inline-block; padding: 14px 40px; font-size: 1.2rem; background: linear-gradient(135deg, #28a745, #218838);">
                            <i class="fas fa-credit-card"></i> 💳 EFETUAR PAGAMENTO
                        </a>
                        <p style="margin-top: 15px; font-size: 0.85rem; color: #6c757d;">
                            <i class="fas fa-lock"></i> Pagamento seguro via M-Pesa ou e-Mola
                        </p>
                    </div>
                    
                <!-- STATUS PAGO -->
                <?php else: ?>
                    <div class="info-box" style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div style="text-align: center;">
                                <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 10px 0; color: #155724;">Pagamento Confirmado!</h3>
                                <p style="margin: 0; color: #155724;">Seu pagamento foi confirmado. O administrador já pode atribuir um professor.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="info-value">
                                <span class="status-badge status-pago">
                                    <i class="fas fa-check-circle"></i> Pago
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data do Pagamento:</span>
                            <span class="info-value"><?= $data_pagamento ? date('d/m/Y H:i', strtotime($data_pagamento)) : '--/--/----' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Valor Pago:</span>
                            <span class="info-value"><?= number_format($valor_total, 0, ',', '.') ?> MT</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Período:</span>
                            <span class="info-value"><?= $inicio_formatada ?: '--/--/----' ?> a <?= $validade_formatada ?: '--/--/----' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Aulas Restantes:</span>
                            <span class="info-value"><?= $info_pacote_completa['aulas_restantes'] ?> de <?= $info_pacote_completa['aulas_contratadas'] ?></span>
                        </div>
                    </div>
                    
                    <!-- NOTIFICAÇÃO DE RENOVAÇÃO -->
                    <?php if ($precisa_renovar): ?>
                        <div class="alert alert-warning" style="margin-top: 20px;">
                            <i class="fas fa-<?= $expirado ? 'exclamation-triangle' : 'hourglass-half' ?>"></i>
                            <strong><?= $expirado ? 'Pacote Expirado!' : 'Renovação Próxima!' ?></strong>
                            <?php if ($expirado): ?>
                                <p>Seu pacote expirou em <?= $validade_formatada ?>. Renove agora para continuar tendo aulas.</p>
                            <?php else: ?>
                                <p>Seu pacote vence em <strong><?= $dias_restantes ?> dias</strong> (<?= $validade_formatada ?>).</p>
                            <?php endif; ?>
                            <a href="pagamento_form.php?ficha_id=<?= $ficha_id ?>" class="btn btn-warning" style="margin-top: 10px;">
                                <i class="fas fa-sync-alt"></i> Renovar Pacote
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Informações sobre créditos -->
            <?php if ($info_pacote_completa['creditos'] > 0): ?>
                <div class="info-box creditos" style="margin-top: 20px; padding: 15px; background: #fefcbf; border-left: 4px solid #ecc94b; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-gift" style="font-size: 2rem; color: #ecc94b;"></i>
                        <div>
                            <h4 style="margin: 0 0 5px 0;">Créditos de Reposição</h4>
                            <p style="margin: 0; color: #2d3748;">
                                Você tem <strong><?= $info_pacote_completa['creditos'] ?> crédito(s)</strong> de reposição. 
                                Estes créditos são usados automaticamente quando o professor cancela uma aula.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Informações sobre aulas pendentes -->
            <?php if ($pendentes_professor > 0): ?>
                <div class="info-box pendentes" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #f39c12; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-clock" style="font-size: 2rem; color: #f39c12;"></i>
                        <div>
                            <h4 style="margin: 0 0 5px 0;">Aulas Pendentes</h4>
                            <p style="margin: 0; color: #2d3748;">
                                O professor tem <strong><?= $pendentes_professor ?> aula(s) pendente(s)</strong> 
                                que não foram registradas. Ele deve realizar uma reposição.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="dashboard-card">
                <p class="text-muted">Complete o seu cadastro para ver as informações financeiras.</p>
                <a href="FichaAluno.html" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Preencher Ficha
                </a>
            </div>
        <?php endif; ?>
    </section>

</main>

<!-- ===== MODAL DE DETALHES DA AULA ===== -->
<div id="modalDetalhes" class="modal-overlay">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2>
                <i class="fas fa-graduation-cap"></i> 
                Detalhes da Aula
            </h2>
            <button class="modal-close" onclick="fecharModalDetalhes()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body" id="modal-body-conteudo">
            <!-- Conteúdo preenchido via JavaScript -->
        </div>
    </div>
</div>

<!-- ===== MODAL PARA CANCELAR/VER AULA (CALENDÁRIO) ===== -->
<div id="modalAula" class="modal-overlay">
    <div class="modal-conteudo" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h2><i class="fas fa-calendar-day"></i> Detalhes da Aula</h2>
            <button class="modal-close" onclick="fecharModalAula()">&times;</button>
        </div>
        
        <div class="modal-body" id="modal-aula-conteudo">
            <!-- Preenchido via JavaScript -->
        </div>
    </div>
</div>

<!-- ===== MODAL PARA CANCELAR AULA COM JUSTIFICATIVA (ALUNO) ===== -->
<div id="modalCancelarAluno" class="modal-overlay">
    <div class="modal-conteudo" style="max-width: 450px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
            <h2><i class="fas fa-times-circle"></i> Cancelar Aula</h2>
            <button class="modal-close" onclick="fecharModal('modalCancelarAluno')">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="aula-info" style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <p><strong>Aluno:</strong> <span id="cancel_aluno_nome"><?= htmlspecialchars($usuario_nome) ?></span></p>
                <p><strong>Data:</strong> <span id="cancel_data"></span></p>
                <p><strong>Hora:</strong> <span id="cancel_hora"></span></p>
            </div>
            
            <div class="form-group">
                <label class="required" style="font-weight: 600; color: #2c3e50;">Motivo do Cancelamento</label>
                <select id="motivo_cancelamento" class="form-control" style="width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 8px;">
                    <option value="">Selecione um motivo...</option>
                    <option value="Problemas de saúde">🤒 Problemas de saúde</option>
                    <option value="Compromisso pessoal">📅 Compromisso pessoal</option>
                    <option value="Falha técnica">💻 Falha técnica (internet/energia)</option>
                    <option value="Emergência familiar">🚨 Emergência familiar</option>
                    <option value="Outro">✏️ Outro (especifique)</option>
                </select>
                
                <textarea id="motivo_outro" rows="3" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; display: none; margin-top: 10px;" 
                          placeholder="Descreva o motivo do cancelamento..."></textarea>
            </div>
            
            <div class="alert alert-warning" style="margin: 20px 0;">
                <i class="fas fa-info-circle"></i> 
                <strong>Nota:</strong> O professor será notificado imediatamente sobre este cancelamento.
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="fecharModal('modalCancelarAluno')">Voltar</button>
                <button class="btn btn-danger" onclick="confirmarCancelamentoAluno()">
                    <i class="fas fa-check"></i> Confirmar Cancelamento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Agendamento (removido, mas mantido para compatibilidade) -->
<div id="modalAgendamento" class="modal-overlay" style="display: none;"></div>

<!-- CSS adicional para o calendário real e notificações -->
<style>
.calendario-navegacao {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
    padding: 10px;
    background: #f8fafd;
    border-radius: 8px;
}

.calendario-navegacao h4 {
    margin: 0;
    font-size: 1rem;
    color: #2c3e50;
    font-weight: 600;
}

.btn-navegacao {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 6px;
    color: #4a5568;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-navegacao:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.calendario-dia.fora-periodo {
    opacity: 0.3;
    background: #f1f5f9;
    border-color: #e2e8f0;
    cursor: default;
    pointer-events: none;
}

.calendario-dia.fora-periodo:hover {
    transform: none;
    box-shadow: none;
    border-color: #e2e8f0;
}

.legenda-cor.fora-periodo {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    opacity: 0.5;
}

.periodo-info {
    margin-top: 15px;
    padding: 10px;
    background: #e3f2fd;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #1976d2;
    display: flex;
    align-items: center;
    gap: 8px;
}

.periodo-info i {
    font-size: 1rem;
}

/* Status badges */
.status-badge.status-pendente {
    background: #fff3cd;
    color: #856404;
    padding: 6px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.status-badge.status-pago {
    background: #d4edda;
    color: #155724;
    padding: 6px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

/* Estilos para notificações */
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

<script>
// Variáveis globais para funções do modal
function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function fecharModalDetalhes() {
    fecharModal('modalDetalhes');
}

function fecharModalAula() {
    fecharModal('modalAula');
}

// Funções para notificações
function toggleNotificacoes() {
    const dropdown = document.getElementById('notificacoesDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
}

function marcarComoLida(notificacaoId) {
    fetch('notificacoes.php?acao=marcar_lida', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificacaoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`.notificacao-item[data-id="${notificacaoId}"]`);
            if (item) {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            }
            atualizarContadorNotificacoes();
        }
    });
}

function marcarTodasComoLidas() {
    fetch('notificacoes.php?acao=marcar_todas_lidas', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notificacao-item.nao-lida').forEach(item => {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            });
            atualizarContadorNotificacoes();
        }
    });
}

function atualizarContadorNotificacoes() {
    fetch('notificacoes.php?acao=listar&nao_lidas=1&limite=1')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notificacoes-badge');
            if (data.nao_lidas > 0) {
                if (badge) {
                    badge.textContent = data.nao_lidas;
                } else {
                    const btn = document.querySelector('.notificacoes-btn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notificacoes-badge';
                    newBadge.textContent = data.nao_lidas;
                    btn.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        });
}

// Funções para seções
function mostrarSecao(id, event) {
    if (event) event.preventDefault();
    
    document.querySelectorAll('.dashboard-section').forEach(section => {
        section.classList.add('hidden');
    });
    
    document.getElementById(id).classList.remove('hidden');
    
    document.querySelectorAll('.sidebar ul li a').forEach(link => {
        link.classList.remove('active');
    });
    
    if (event && event.target) {
        const link = event.target.closest('a');
        if (link) link.classList.add('active');
    }
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(event) {
    if (!event.target.closest('.notificacoes-container')) {
        const dropdown = document.getElementById('notificacoesDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
});

// Menu mobile
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const menuClose = document.getElementById('menuClose');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if (menuClose) {
        menuClose.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Se não estiver pago, garantir que a seção de aulas fique escondida
    if (window.pagamentoStatus !== 'pago') {
        document.getElementById('aulas').classList.add('hidden');
    }
});
</script>

<script src="JavaScript/dashboard.js"></script>
</body>
</html>

<?php
if (isset($stmt_aulas)) $stmt_aulas->close();
$conn->close();
?>