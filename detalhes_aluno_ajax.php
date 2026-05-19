<?php
// detalhes_aluno_ajax.php - Busca informações do aluno e gera calendário
session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin'])) {
    die(json_encode(['erro' => 'Não autorizado']));
}
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) die(json_encode(['erro' => 'Erro de conexão']));

$ficha_id = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('m');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');

if (!$ficha_id) die(json_encode(['erro' => 'ID inválido']));

// Dados do aluno
$stmt = $conn->prepare("
    SELECT f.*, u.email, u.data_criacao, u.ultimo_login 
    FROM fichas f 
    JOIN usuarios u ON u.id = f.usuario_id 
    WHERE f.id = ?
");
$stmt->bind_param("i", $ficha_id);
$stmt->execute();
$aluno = $stmt->get_result()->fetch_assoc();
if (!$aluno) die(json_encode(['erro' => 'Aluno não encontrado']));

// Horários
$horarios = [];
$stmt2 = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ?");
$stmt2->bind_param("i", $ficha_id);
$stmt2->execute();
$res = $stmt2->get_result();
while ($h = $res->fetch_assoc()) $horarios[] = $h;

// Aulas do mês selecionado
$aulas = [];
$stmt3 = $conn->prepare("
    SELECT DATE(data_hora) as data, status, TIME(data_hora) as hora 
    FROM agendamentos_aulas 
    WHERE aluno_id = (SELECT id FROM fichas WHERE id = ? LIMIT 1) 
    AND MONTH(data_hora) = ? AND YEAR(data_hora) = ?
");
$stmt3->bind_param("iii", $ficha_id, $mes, $ano);
$stmt3->execute();
$res3 = $stmt3->get_result();
while ($a = $res3->fetch_assoc()) $aulas[$a['data']] = ['status' => $a['status'], 'hora' => substr($a['hora'],0,5)];

// Gerar HTML do calendário (simplificado, pode copiar do dashboard do aluno)
function gerarCalendarioAdmin($horarios, $aulas, $mes, $ano) {
    $primeiro_dia = mktime(0,0,0,$mes,1,$ano);
    $dias_no_mes = date('t', $primeiro_dia);
    $dia_semana_inicio = date('w', $primeiro_dia);
    $dias_semana_port = ['domingo','segunda','terca','quarta','quinta','sexta','sabado'];
    $dias_aula = array_column($horarios, 'dia_semana');
    $html = '<div class="calendario-container"><div class="calendario-dias-semana"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div><div class="calendario-grid">';
    for ($i=0; $i<$dia_semana_inicio; $i++) $html .= '<div class="calendario-dia vazio"></div>';
    for ($dia=1; $dia<=$dias_no_mes; $dia++) {
        $data = sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
        $timestamp = mktime(0,0,0,$mes,$dia,$ano);
        $dia_semana = $dias_semana_port[date('w', $timestamp)];
        $tem_aula = in_array($dia_semana, $dias_aula);
        $aula = $aulas[$data] ?? null;
        $classes = ['calendario-dia'];
        if ($tem_aula) $classes[] = 'dia-aula';
        if ($aula) {
            if ($aula['status'] == 'realizado') $classes[] = 'aula-realizada';
            elseif ($aula['status'] == 'cancelado_aluno') $classes[] = 'aula-cancelada-aluno';
            elseif ($aula['status'] == 'cancelado_professor') $classes[] = 'aula-cancelada-professor';
        }
        $html .= "<div class='".implode(' ',$classes)."'>$dia";
        if ($aula) $html .= "<span class='icone-status'>".($aula['status']=='realizado'?'✓':'✗')."</span>";
        $html .= "</div>";
    }
    $html .= '</div></div>';
    return $html;
}

$calendario_html = gerarCalendarioAdmin($horarios, $aulas, $mes, $ano);

echo json_encode([
    'aluno' => $aluno,
    'horarios' => $horarios,
    'calendario_html' => $calendario_html,
    'mes' => $mes,
    'ano' => $ano
]);
?>