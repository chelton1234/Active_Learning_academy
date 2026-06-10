<?php
// dashboard_admin.php - Painel Administrativo (com exclusão de aluno)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error);

// ========== PROCESSAR EXCLUSÃO DE PEDIDO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_pedido_id'])) {
    $id = (int)$_POST['excluir_pedido_id'];
    $conn->query("DELETE FROM pedidos_explicadores WHERE id = $id");
    $mensagem = "Pedido excluído.";
}

// ========== PROCESSAR EXCLUSÃO DE PROFESSOR ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_professor'])) {
    $acao = $_POST['acao_professor'];
    if ($acao === 'adicionar') {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $especialidade = trim($_POST['especialidade']);
        $telefone = trim($_POST['telefone']);
        $disponivel = $_POST['disponivel'] === 'sim' ? 'sim' : 'nao';
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'docente')");
        $stmt->bind_param("sss", $nome, $email, $senha);
        if ($stmt->execute()) {
            $uid = $stmt->insert_id;
            $stmt->close();
            $stmt2 = $conn->prepare("INSERT INTO professores (usuario_id, especialidade, telefone, disponivel, criado_em) VALUES (?, ?, ?, ?, NOW())");
            $stmt2->bind_param("isss", $uid, $especialidade, $telefone, $disponivel);
            $stmt2->execute();
            $stmt2->close();
            $mensagem_prof = "Professor adicionado.";
        } else {
            $mensagem_prof = "Erro: email já existe?";
            $stmt->close();
        }
    } elseif ($acao === 'excluir' && !empty($_POST['professor_id'])) {
        $pid = (int)$_POST['professor_id'];
        $res = $conn->query("SELECT usuario_id FROM professores WHERE id = $pid");
        if ($res && $row = $res->fetch_assoc()) {
            $conn->query("DELETE FROM professores WHERE id = $pid");
            $conn->query("DELETE FROM usuarios WHERE id = {$row['usuario_id']}");
            $mensagem_prof = "Professor excluído.";
        }
    }
}

// ========== PROCESSAR EXCLUSÃO DE ALUNO (COMPLETO) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_aluno'])) {
    $ficha_id = (int)$_POST['ficha_id'];
    $usuario_id = (int)$_POST['usuario_id'];
    
    if ($ficha_id && $usuario_id) {
        // Iniciar transação
        $conn->begin_transaction();
        try {
            // Remover itens relacionados (aulas, horários, notificações, etc.)
            $conn->query("DELETE FROM agendamentos_aulas WHERE aluno_id = $ficha_id");
            $conn->query("DELETE FROM horarios_aulas WHERE ficha_id = $ficha_id");
            $conn->query("DELETE FROM notificacoes WHERE usuario_id = $usuario_id");
            $conn->query("DELETE FROM pagamentos WHERE ficha_id = $ficha_id");
            $conn->query("DELETE FROM password_resets WHERE email = (SELECT email FROM usuarios WHERE id = $usuario_id)");
            // Remover ficha
            $conn->query("DELETE FROM fichas WHERE id = $ficha_id");
            // Remover utilizador
            $conn->query("DELETE FROM usuarios WHERE id = $usuario_id");
            $conn->commit();
            $mensagem_aluno = "Aluno excluído completamente.";
        } catch (Exception $e) {
            $conn->rollback();
            $mensagem_aluno = "Erro ao excluir aluno: " . $e->getMessage();
        }
    } else {
        $mensagem_aluno = "Dados inválidos para exclusão.";
    }
}

// ========== LISTAGENS ==========
// Pedidos pendentes (solicitações)
$pendentes = $conn->query("SELECT * FROM pedidos_explicadores WHERE status = 'pendente' ORDER BY data_submissao DESC");

// Alunos activos (utilizadores com tipo 'aluno' e que têm ficha)
$alunos_activos = $conn->query("
    SELECT u.id as usuario_id, u.nome, u.email, f.id as ficha_id, 
           COALESCE(f.nivel_cambridge, f.nivel, '') as nivel_cambridge, 
           f.pacote, f.professor_atribuido, f.pagamento_status, f.data_submissao as data_ficha
    FROM usuarios u
    JOIN fichas f ON u.id = f.usuario_id
    WHERE u.tipo = 'aluno' AND u.ativo = 1
    ORDER BY f.data_submissao DESC
");

// Professores para usar nos selects
$professores = $conn->query("SELECT p.id, u.nome FROM professores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.disponivel = 'sim' ORDER BY u.nome");

// Estatísticas
$total_pendentes = $pendentes->num_rows;
$total_activos = $alunos_activos->num_rows;
$total_professores = $conn->query("SELECT COUNT(*) as t FROM professores")->fetch_assoc()['t'];

// Notificações (pedidos pendentes nas últimas 24h)
$notificacoes = $conn->query("SELECT id, nome, data_submissao FROM pedidos_explicadores WHERE status = 'pendente' AND data_submissao >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY data_submissao DESC LIMIT 10");

// Endpoint AJAX para polling de notificações
if (isset($_GET['ajax']) && $_GET['ajax'] === 'notificacoes') {
    $ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
    $res = $conn->query("SELECT id, nome, data_submissao FROM pedidos_explicadores WHERE status = 'pendente' AND id > $ultimo_id ORDER BY id DESC LIMIT 5");
    $novas = [];
    while ($r = $res->fetch_assoc()) $novas[] = $r;
    echo json_encode(['novas' => $novas]);
    exit;
}

// Endpoint para obter detalhes do aluno (para a secção Alunos Activos)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'aluno_detalhes') {
    $ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
    if (!$ficha_id) die(json_encode(['erro' => 'ID inválido']));
    
    $stmt = $conn->prepare("
        SELECT f.*, u.email, u.ativo 
        FROM fichas f 
        JOIN usuarios u ON u.id = f.usuario_id 
        WHERE f.id = ?
    ");
    $stmt->bind_param("i", $ficha_id);
    $stmt->execute();
    $aluno = $stmt->get_result()->fetch_assoc();
    if (!$aluno) die(json_encode(['erro' => 'Aluno não encontrado']));
    
    $horarios = [];
    $stmt2 = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ?");
    $stmt2->bind_param("i", $ficha_id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    while ($h = $res->fetch_assoc()) $horarios[] = $h;
    
    $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
    $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
    $aulas = [];
    $stmt3 = $conn->prepare("
        SELECT DATE(data_hora) as data, status, TIME(data_hora) as hora 
        FROM agendamentos_aulas 
        WHERE aluno_id = (SELECT id FROM fichas WHERE id = ?) 
        AND MONTH(data_hora) = ? AND YEAR(data_hora) = ?
    ");
    $stmt3->bind_param("iii", $ficha_id, $mes, $ano);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($a = $res3->fetch_assoc()) $aulas[$a['data']] = ['status' => $a['status'], 'hora' => substr($a['hora'],0,5)];
    
    echo json_encode([
        'aluno' => $aluno,
        'horarios' => $horarios,
        'aulas' => $aulas,
        'mes' => $mes,
        'ano' => $ano
    ]);
    exit;
}

// Endpoint para carregar calendário de um aluno (AJAX) – versão simplificada
if (isset($_GET['ajax']) && $_GET['ajax'] === 'calendario_aluno') {
    $ficha_id = (int)$_GET['ficha_id'];
    $mes = (int)($_GET['mes'] ?? date('m'));
    $ano = (int)($_GET['ano'] ?? date('Y'));
    if (!$ficha_id) die('<p>Aluno inválido.</p>');
    
    // Buscar horários
    $horarios = [];
    $stmt = $conn->prepare("SELECT dia_semana FROM horarios_aulas WHERE ficha_id = ?");
    $stmt->bind_param("i", $ficha_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($h = $res->fetch_assoc()) $horarios[] = $h['dia_semana'];
    
    // Buscar aulas do mês
    $aulas_mes = [];
    $stmt2 = $conn->prepare("
        SELECT DATE(data_hora) as data, status 
        FROM agendamentos_aulas 
        WHERE aluno_id = (SELECT id FROM fichas WHERE id = ? LIMIT 1) 
        AND MONTH(data_hora) = ? AND YEAR(data_hora) = ?
    ");
    $stmt2->bind_param("iii", $ficha_id, $mes, $ano);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($a = $res2->fetch_assoc()) $aulas_mes[$a['data']] = $a['status'];
    
    // Gerar HTML do calendário
    $primeiro_dia = mktime(0,0,0,$mes,1,$ano);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);
    $dias_semana_port = ['domingo','segunda','terca','quarta','quinta','sexta','sabado'];
    $html = '<div class="calendario-container"><div class="calendario-dias-semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div><div class="calendario-grid">';
    for ($i=0;$i<$dia_semana_inicio;$i++) $html .= '<div class="calendario-dia vazio"></div>';
    for ($dia=1;$dia<=$dias_no_mes;$dia++) {
        $data = sprintf("%04d-%02d-%02d",$ano,$mes,$dia);
        $timestamp = mktime(0,0,0,$mes,$dia,$ano);
        $dia_semana = $dias_semana_port[date('w',$timestamp)];
        $tem_aula = in_array($dia_semana, $horarios);
        $aula = $aulas_mes[$data] ?? null;
        $classes = ['calendario-dia'];
        if ($tem_aula) $classes[] = 'dia-aula';
        if ($aula) {
            if ($aula == 'realizado') $classes[] = 'aula-realizada';
            elseif ($aula == 'cancelado_aluno') $classes[] = 'aula-cancelada-aluno';
            elseif ($aula == 'cancelado_professor') $classes[] = 'aula-cancelada-professor';
        }
        $html .= "<div class='".implode(' ',$classes)."'>".$dia;
        if ($aula) $html .= "<span class='icone-status'>".($aula=='realizado'?'✓':'✗')."</span>";
        $html .= "</div>";
    }
    $html .= '</div></div>';
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin | WebTeaching</title>
    <link rel="stylesheet" href="Css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge { background: #e74c3c; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 30px; margin-left: 8px; }
        .calendario-container { background: #f9fafb; border-radius: 16px; padding: 15px; margin-top: 15px; }
        .calendario-dias-semana { display: grid; grid-template-columns: repeat(7,1fr); text-align: center; font-weight: bold; margin-bottom: 5px; }
        .calendario-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 4px; }
        .calendario-dia { background: white; border-radius: 8px; text-align: center; padding: 8px 0; font-size: 0.8rem; border: 1px solid #e2e8f0; position: relative; }
        .calendario-dia.dia-aula { background: #e0f2fe; }
        .calendario-dia.aula-realizada { background: #d1fae5; }
        .calendario-dia.aula-cancelada-aluno { background: #fee2e2; }
        .calendario-dia.aula-cancelada-professor { background: #ffedd5; }
        .icone-status { position: absolute; bottom: 2px; right: 2px; font-size: 0.6rem; background: white; border-radius: 50%; width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .modal-conteudo { max-width: 750px; }
        .info-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 12px; margin-bottom: 20px; }
        .info-item strong { display: block; color: #2c3e50; margin-bottom: 4px; }
        .info-item span { color: #5a6b7a; }
    </style>
</head>
<body>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <button class="menu-close" id="menuClose"><i class="fas fa-times"></i></button>
    <h2>Menu Admin</h2>
    <ul>
        <li><a href="#" data-secao="inicio"><i class="fas fa-tachometer-alt"></i> Início</a></li>
        <li><a href="#" data-secao="solicitacoes"><i class="fas fa-check-circle"></i> Solicitações <?= $total_pendentes > 0 ? "<span class='badge'>$total_pendentes</span>" : '' ?></a></li>
        <li><a href="#" data-secao="alunos_activos"><i class="fas fa-users"></i> Alunos Activos</a></li>
        <li><a href="#" data-secao="professores"><i class="fas fa-chalkboard-user"></i> Professores</a></li>
        <li><a href="#" data-secao="fichas_antigas"><i class="fas fa-archive"></i> Fichas Antigas</a></li>
        <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
    </ul>
</aside>

<main class="content">
    <!-- Início -->
    <section id="inicio" class="dashboard-section active">
        <div class="welcome-card">
            <div style="display: flex; justify-content: space-between;">
                <h2><i class="fas fa-home"></i> Painel do Administrador</h2>
                <div class="notificacoes-container">
                    <button class="notificacoes-btn" onclick="toggleNotificacoes()">
                        <i class="fas fa-bell"></i>
                        <?php $notif_count = $conn->query("SELECT COUNT(*) as t FROM pedidos_explicadores WHERE status='pendente' AND data_submissao >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetch_assoc()['t']; ?>
                        <?php if ($notif_count): ?><span class="notificacoes-badge"><?= $notif_count ?></span><?php endif; ?>
                    </button>
                    <div class="notificacoes-dropdown" id="notificacoesDropdown">
                        <div class="notificacoes-header">Notificações</div>
                        <div class="notificacoes-lista" id="notifList"></div>
                        <div class="dropdown-footer"><a href="#" onclick="marcarTodasLidas()">Marcar todas</a></div>
                    </div>
                </div>
            </div>
            <p>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?>.</p>
            <div class="stats-cards">
                <div class="stat-card"><div class="stat-number"><?= $total_pendentes ?></div><div class="stat-label">Solicitações</div></div>
                <div class="stat-card"><div class="stat-number"><?= $total_activos ?></div><div class="stat-label">Alunos Activos</div></div>
                <div class="stat-card"><div class="stat-number"><?= $total_professores ?></div><div class="stat-label">Professores</div></div>
            </div>
        </div>
    </section>

    <!-- Solicitações (Pedidos Pendentes) -->
    <section id="solicitacoes" class="dashboard-section">
        <h2><i class="fas fa-check-circle"></i> Solicitações (Pedidos Pendentes)</h2>
        <?php if ($pendentes->num_rows == 0): ?>
            <p>Nenhuma solicitação pendente.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabela-aulas">
                    <thead><tr><th>Data</th><th>Nome</th><th>Email</th><th>Contacto</th><th>Nível</th><th>Pacote</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php while($p = $pendentes->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($p['data_submissao'])) ?></td>
                            <td><?= htmlspecialchars($p['nome'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['contacto'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['nivel_cambridge'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['pacote'] ?? '') ?></td>
                            <td>
                                <button class="btn-sm btn-info" onclick="verDetalhesSolicitacao(<?= $p['id'] ?>)">Detalhes</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Rejeitar este pedido?')">
                                    <input type="hidden" name="excluir_pedido_id" value="<?= $p['id'] ?>">
                                    <button class="btn-sm btn-danger">Rejeitar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Alunos Activos (com botão Excluir) -->
    <section id="alunos_activos" class="dashboard-section">
        <h2><i class="fas fa-users"></i> Alunos Activos</h2>
        <?php if ($alunos_activos->num_rows == 0): ?>
            <p>Nenhum aluno activo ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="tabela-aulas" id="tabela-alunos">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Nível</th>
                            <th>Pacote</th>
                            <th>Professor</th>
                            <th>Status Pag.</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($a = $alunos_activos->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['nome'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['nivel_cambridge'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['pacote'] ?? '') ?></td>
                            <td><?= htmlspecialchars($a['professor_atribuido'] ?? '—') ?></td>
                            <td><?= $a['pagamento_status'] == 'pago' ? 'Pago' : 'Pendente' ?></td>
                            <td class="acoes">
                                <button class="btn-sm btn-primary" onclick="verDetalhesAluno(<?= $a['ficha_id'] ?>)">Ver Detalhes</button>
                                <button class="btn-sm btn-danger" onclick="excluirAluno(<?= $a['ficha_id'] ?>, <?= $a['usuario_id'] ?>, '<?= addslashes($a['nome']) ?>')">Excluir</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Professores (inalterado) -->
    <section id="professores" class="dashboard-section">
        <h2><i class="fas fa-chalkboard-user"></i> Gestão de Professores</h2>
        <div class="dashboard-card">
            <h3>Adicionar Professor</h3>
            <form method="POST" class="inline-form">
                <input type="hidden" name="acao_professor" value="adicionar">
                <input type="text" name="nome" placeholder="Nome" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <input type="text" name="especialidade" placeholder="Especialidade" required>
                <input type="text" name="telefone" placeholder="Telefone" required>
                <select name="disponivel"><option value="sim">Disponível</option><option value="nao">Indisponível</option></select>
                <button type="submit" class="btn btn-primary">Adicionar</button>
            </form>
        </div>
        <div class="dashboard-card">
            <h3>Lista de Professores</h3>
            <?php $prof_list = $conn->query("SELECT p.id, u.nome, u.email, p.especialidade, p.telefone, p.disponivel FROM professores p JOIN usuarios u ON u.id=p.usuario_id ORDER BY p.criado_em DESC"); ?>
            <div class="table-responsive">
                <table class="tabela-aulas">
                    <thead><tr><th>Nome</th><th>Email</th><th>Especialidade</th><th>Disponível</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php while($prof = $prof_list->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($prof['nome'] ?? '') ?></td>
                            <td><?= htmlspecialchars($prof['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($prof['especialidade'] ?? '') ?></td>
                            <td><?= $prof['disponivel'] == 'sim' ? 'Sim' : 'Não' ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao_professor" value="excluir">
                                    <input type="hidden" name="professor_id" value="<?= $prof['id'] ?>">
                                    <button class="btn-sm btn-danger" onclick="return confirm('Excluir este professor?')">Excluir</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Fichas Antigas -->
    <section id="fichas_antigas" class="dashboard-section">
        <h2><i class="fas fa-archive"></i> Fichas Antigas (Sistema anterior)</h2>
        <?php $fichas_ant = $conn->query("SELECT * FROM fichas ORDER BY data_submissao DESC LIMIT 50"); ?>
        <div class="table-responsive">
            <table class="tabela-aulas">
                <thead><tr><th>Nome</th><th>Email</th><th>Classe</th><th>Data</th></tr></thead>
                <tbody>
                    <?php while($f = $fichas_ant->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['nome'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($f['classe'] ?? '') ?></td>
                        <td><?= date('d/m/Y', strtotime($f['data_submissao'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- ========== MODAL UNIFICADO: DETALHES + APROVAÇÃO ========== -->
<div id="modalDetalhesSolicitacao" class="modal-overlay">
    <div class="modal-conteudo">
        <div class="modal-header">
            <h2><i class="fas fa-clipboard-list"></i> Detalhes da Solicitação</h2>
            <button class="modal-close" onclick="fecharModal('modalDetalhesSolicitacao')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detalhesSolicitacaoBody"><div class="loading">Carregando...</div></div>
            <div class="form-group" style="margin-top: 25px; border-top: 1px solid #eef2f6; padding-top: 20px;">
                <label for="selectProfessorSolicitacao"><strong>Atribuir Professor:</strong></label>
                <select id="selectProfessorSolicitacao" class="form-control">
                    <option value="">-- Selecione um professor --</option>
                    <?php 
                    $prof_opts = $conn->query("SELECT p.id, u.nome FROM professores p JOIN usuarios u ON u.id=p.usuario_id WHERE p.disponivel='sim' ORDER BY u.nome");
                    while($opt = $prof_opts->fetch_assoc()): ?>
                        <option value="<?= $opt['id'] ?>"><?= htmlspecialchars($opt['nome']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="fecharModal('modalDetalhesSolicitacao')">Cancelar</button>
            <button class="btn btn-success" onclick="aprovarSolicitacao()">✅ Aprovar e Criar Utilizador</button>
        </div>
    </div>
</div>

<!-- Modal para detalhes do aluno (calendário, etc.) -->
<div id="modalDetalhesAluno" class="modal-overlay">
    <div class="modal-conteudo" style="max-width: 800px;">
        <div class="modal-header">
            <h2>Detalhes do Aluno</h2>
            <button class="modal-close" onclick="fecharModal('modalDetalhesAluno')">&times;</button>
        </div>
        <div class="modal-body" id="detalhesAlunoBody"><div class="loading">Carregando...</div></div>
    </div>
</div>

<script>
// Variável global para o ID do pedido actual
let currentPedidoId = null;

// Função auxiliar para escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function formatarData(dataStr) {
    const d = new Date(dataStr);
    return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
}

// Navegação por secções (data-secao)
document.querySelectorAll('.sidebar ul li a[data-secao]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const secao = this.getAttribute('data-secao');
        document.querySelectorAll('.dashboard-section').forEach(s => s.classList.remove('active'));
        document.getElementById(secao).classList.add('active');
        document.querySelectorAll('.sidebar ul li a').forEach(a => a.classList.remove('active'));
        this.classList.add('active');
    });
});

// Inicializar secção activa
document.querySelector('.sidebar ul li a[data-secao="inicio"]').classList.add('active');

// ========== DETALHES SOLICITAÇÃO ==========
function verDetalhesSolicitacao(pedidoId) {
    currentPedidoId = pedidoId;
    fetch(`detalhes_pedido.php?id=${pedidoId}`)
        .then(res => res.json())
        .then(data => {
            if (data.erro) throw new Error(data.erro);
            let html = `
                <div class="info-grid">
                    <div class="info-item"><strong>Nome:</strong> <span>${escapeHtml(data.nome)}</span></div>
                    <div class="info-item"><strong>Email:</strong> <span>${escapeHtml(data.email)}</span></div>
                    <div class="info-item"><strong>Contacto:</strong> <span>${escapeHtml(data.contacto)}</span></div>
                    <div class="info-item"><strong>Localização:</strong> <span>${escapeHtml(data.localizacao) || '—'}</span></div>
                    <div class="info-item"><strong>Nível:</strong> <span>${escapeHtml(data.nivel_cambridge)}</span></div>
                    <div class="info-item"><strong>Tipo aula:</strong> <span>${data.tipo_aula === 'presencial' ? 'Presencial' : 'Ao domicílio'}</span></div>
                    <div class="info-item"><strong>Pacote:</strong> <span>${data.pacote}</span></div>
                    <div class="info-item"><strong>Total:</strong> <span>${data.preco_total} MT</span></div>
                    <div class="info-item"><strong>Dias:</strong> <span>${escapeHtml(data.dias_semana)}</span></div>
                    <div class="info-item"><strong>Horário:</strong> <span>${escapeHtml(data.horario)}</span></div>
                    <div class="info-item" style="grid-column:span 2"><strong>Observações:</strong> <span>${escapeHtml(data.observacoes) || '—'}</span></div>
                </div>
            `;
            document.getElementById('detalhesSolicitacaoBody').innerHTML = html;
            document.getElementById('modalDetalhesSolicitacao').classList.add('active');
        })
        .catch(err => alert('Erro: ' + err.message));
}

function aprovarSolicitacao() {
    if (!currentPedidoId) { alert('Nenhum pedido selecionado.'); return; }
    const professorId = document.getElementById('selectProfessorSolicitacao').value;
    if (!professorId) { alert('Selecione um professor.'); return; }
    const btn = document.querySelector('#modalDetalhesSolicitacao .btn-success');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    btn.disabled = true;
    fetch('aprovar_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pedido_id: currentPedidoId, professor_id: professorId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            alert('✅ ' + data.mensagem);
            location.reload();
        } else {
            alert('❌ Erro: ' + data.mensagem);
            btn.innerHTML = original;
            btn.disabled = false;
        }
    })
    .catch(err => { alert('Erro: ' + err); btn.innerHTML = original; btn.disabled = false; });
}

// ========== DETALHES DO ALUNO (com calendário) ==========
function verDetalhesAluno(fichaId) {
    const modal = document.getElementById('modalDetalhesAluno');
    const body = document.getElementById('detalhesAlunoBody');
    body.innerHTML = '<div class="loading">Carregando dados...</div>';
    modal.classList.add('active');
    const mes = new Date().getMonth() + 1;
    const ano = new Date().getFullYear();
    fetch(`dashboard_admin.php?ajax=aluno_detalhes&ficha_id=${fichaId}&mes=${mes}&ano=${ano}`)
        .then(res => res.json())
        .then(data => {
            if (data.erro) throw new Error(data.erro);
            const aluno = data.aluno;
            let html = `
                <div class="info-grid">
                    <div><strong>Nome:</strong> ${escapeHtml(aluno.nome)}</div>
                    <div><strong>Email:</strong> ${escapeHtml(aluno.email)}</div>
                    <div><strong>Contacto:</strong> ${escapeHtml(aluno.contacto) || '—'}</div>
                    <div><strong>Nível:</strong> ${escapeHtml(aluno.nivel_cambridge) || '—'}</div>
                    <div><strong>Pacote:</strong> ${aluno.pacote}</div>
                    <div><strong>Professor:</strong> ${escapeHtml(aluno.professor_atribuido) || '—'}</div>
                    <div><strong>Pagamento:</strong> ${aluno.pagamento_status === 'pago' ? '✅ Pago' : '⏳ Pendente'}</div>
                </div>
                <hr>
                <h4>Calendário de Aulas</h4>
                <div id="calendario-aluno-${fichaId}">Carregando calendário...</div>
            `;
            body.innerHTML = html;
            // Carregar calendário via outro endpoint
            fetch(`dashboard_admin.php?ajax=calendario_aluno&ficha_id=${fichaId}&mes=${data.mes}&ano=${data.ano}`)
                .then(res => res.text())
                .then(calHtml => {
                    document.getElementById(`calendario-aluno-${fichaId}`).innerHTML = calHtml;
                })
                .catch(() => document.getElementById(`calendario-aluno-${fichaId}`).innerHTML = '<p>Erro ao carregar calendário.</p>');
        })
        .catch(err => { body.innerHTML = '<p class="erro">Erro: ' + err.message + '</p>'; });
}

function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// ========== EXCLUIR ALUNO COMPLETO ==========
function excluirAluno(fichaId, usuarioId, nome) {
    if (!confirm(`Tem certeza que deseja excluir permanentemente o aluno "${nome}"?\n\nTodas as suas aulas, horários e dados serão removidos.`)) return;
    fetch('dashboard_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao_excluir_aluno=1&ficha_id=${fichaId}&usuario_id=${usuarioId}`
    })
    .then(res => res.text())
    .then(data => {
        if (data.includes('excluído completamente')) {
            alert('✅ Aluno excluído com sucesso.');
            location.reload();
        } else {
            alert('❌ Erro ao excluir aluno: ' + data);
        }
    })
    .catch(err => alert('Erro: ' + err));
}

// ========== NOTIFICAÇÕES ==========
function toggleNotificacoes() {
    const dd = document.getElementById('notificacoesDropdown');
    if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}
let ultimoIdNotif = 0;
function carregarNotificacoes() {
    fetch(`dashboard_admin.php?ajax=notificacoes&ultimo_id=${ultimoIdNotif}`)
        .then(res => res.json())
        .then(data => {
            const lista = document.getElementById('notifList');
            if (lista && data.novas && data.novas.length) {
                let badge = document.querySelector('.notificacoes-badge');
                let count = badge ? parseInt(badge.innerText) || 0 : 0;
                data.novas.forEach(n => {
                    const item = document.createElement('div');
                    item.className = 'notificacao-item nao-lida';
                    item.innerHTML = `<div class="notificacao-titulo">Novo pedido</div><div class="notificacao-mensagem">${escapeHtml(n.nome)} submeteu uma solicitação.</div><div class="notificacao-data">${formatarData(n.data_submissao)}</div>`;
                    lista.insertBefore(item, lista.firstChild);
                    if (n.id > ultimoIdNotif) ultimoIdNotif = n.id;
                    count++;
                });
                if (badge) badge.innerText = count;
                const menuBadge = document.querySelector('.sidebar ul li a[data-secao="solicitacoes"] .badge');
                if (menuBadge) menuBadge.innerText = count;
            }
        })
        .catch(console.warn);
}
function marcarTodasLidas() {
    document.querySelector('.notificacoes-badge')?.style.display('none');
    document.getElementById('notifList').innerHTML = '<div class="notificacao-vazia"><i class="fas fa-bell-slash"></i><p>Nenhuma notificação</p></div>';
    const menuBadge = document.querySelector('.sidebar ul li a[data-secao="solicitacoes"] .badge');
    if (menuBadge) menuBadge.innerText = '0';
}
setInterval(carregarNotificacoes, 30000);
carregarNotificacoes();

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notificacoes-container')) {
        const dd = document.getElementById('notificacoesDropdown');
        if (dd) dd.style.display = 'none';
    }
});

// Fechar modais ao clicar no overlay
window.addEventListener('click', function(e) {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>