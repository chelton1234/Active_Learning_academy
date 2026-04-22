<?php
// recibo_pdf.php - Versão corrigida com header em tabela
session_start();
require 'config.php';
require_once 'vendor/autoload.php';

use TCPDF;

// Verifica se a referência foi passada
if (!isset($_GET['referencia'])) {
    exit("Referência não informada.");
}

$referencia = trim($_GET['referencia']);

// Buscar pagamento
$stmt = $conn->prepare("SELECT * FROM pagamentos WHERE referencia = ? AND estado = 'pago'");
$stmt->execute([$referencia]);
$pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pagamento) {
    exit("Pagamento não encontrado.");
}

// Buscar ficha
$stmt = $conn->prepare("SELECT * FROM fichas WHERE id = ?");
$stmt->execute([$pagamento['ficha_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    exit("Ficha do aluno não encontrada.");
}

// BUSCAR HORÁRIOS DA TABELA horarios_aulas
$stmt = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ? ORDER BY 
                        FIELD(dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')");
$stmt->execute([$pagamento['ficha_id']]);
$horarios_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

$horarios = [];
$dias_semana = [];

foreach ($horarios_db as $h) {
    $dias_semana[] = $h['dia_semana'];
    $horarios[$h['dia_semana']] = $h['horario'];
}

// Se não encontrou na tabela horarios_aulas, tentar buscar do JSON
if (empty($dias_semana) && !empty($ficha['dias_semana'])) {
    $dias_semana = json_decode($ficha['dias_semana'], true);
    if (!is_array($dias_semana)) {
        $dias_semana = [];
    }
}

if (empty($horarios) && !empty($ficha['horarios_json'])) {
    $horarios = json_decode($ficha['horarios_json'], true);
    if (!is_array($horarios)) {
        $horarios = [];
    }
}

$dias_nomes = [
    'segunda' => 'Segunda-feira',
    'terca' => 'Terça-feira',
    'quarta' => 'Quarta-feira',
    'quinta' => 'Quinta-feira',
    'sexta' => 'Sexta-feira',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];

// Criar tabela de horários
$horarios_table = '';
if (!empty($dias_semana)) {
    foreach ($dias_semana as $dia) {
        $horario = isset($horarios[$dia]) ? $horarios[$dia] : '--:--';
        $horarios_table .= '<tr><td style="padding:6px; border-bottom:1px solid #ddd;">' . $dias_nomes[$dia] . '</td>
                            <td style="padding:6px; border-bottom:1px solid #ddd; text-align:center;">' . $horario . 'h</td></tr>';
    }
} else {
    $horarios_table = '<tr><td colspan="2" style="padding:10px; text-align:center;">A definir com o professor</td></tr>';
}

// Pacote
$pacote_nome = match($ficha['pacote']) {
    'basico' => 'Básico (2 dias/semana)',
    'intermedio' => 'Intermediário (3 dias/semana)',
    'premium' => 'Premium (4 dias/semana)',
    default => $ficha['pacote']
};

// Nível
$nivel_label = match($ficha['nivel']) {
    'primary' => 'Ensino Primário',
    'secondary' => 'Ensino Secundário',
    'cambridge' => 'Pré-Universitário',
    default => $ficha['nivel']
};

// Regimes
$regimes = [];
if ($ficha['regime_presencial']) $regimes[] = "Presencial";
if ($ficha['regime_online']) $regimes[] = "Online";
if ($ficha['regime_domicilio']) $regimes[] = "Domicílio";
$regime_str = implode(" / ", $regimes);

// Valor base
$valor_base = $pagamento['valor'] - ($ficha['regime_domicilio'] ? 1000 : 0);
$data_pagamento = date('d/m/Y H:i', strtotime($pagamento['confirmado_em']));

// Criar PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Active Learning Academy');
$pdf->SetAuthor('Active Learning Academy');
$pdf->SetTitle('Recibo - ' . $referencia);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// HTML SEM CSS - APENAS TABELAS
$html = '
<!-- HEADER AZUL ESCURO COM EMPRESA NO CANTO DIREITO - USANDO TABELA -->
<table style="width:100%; background-color:#003366; margin-bottom:15px;" cellpadding="8" cellspacing="0">
    <tr>
        <td align="right" style="color:white;">
            <h1 style="color:white; margin:0; font-size:20px; font-weight:bold;">ACTIVE LEARNING ACADEMY</h1>
            <p style="color:#CCCCCC; margin:2px 0 0; font-size:9px;">Excelência em Educação</p>
            <p style="color:#AAAAAA; margin:2px 0 0; font-size:7px;">Av. Marginal, Maputo | +258 84 123 4567</p>
        </td>
    </tr>
</table>

<!-- TÍTULO DO RECIBO -->
<table style="width:100%; margin-bottom:8px;">
    <tr>
        <td align="right">
            <h2 style="color:#003366; margin:0; font-size:14px; font-weight:bold;">COMPROVANTE DE PAGAMENTO</h2>
            <p style="color:#666; margin:2px 0 0; font-size:9px;">Nº: ' . $referencia . '</p>
        </td>
    </tr>
</table>

<!-- INFORMAÇÕES DO PAGAMENTO -->
<table style="width:100%; background-color:#f5f5f5; margin-bottom:12px;" cellpadding="6" cellspacing="0">
    <tr>
        <td style="width:50%;"><strong>Data Pagamento:</strong> ' . $data_pagamento . '</td>
        <td style="width:50%;"><strong>Método:</strong> ' . htmlspecialchars($pagamento['metodo']) . '</td>
    </tr>
    <tr>
        <td><strong>Referência:</strong> ' . htmlspecialchars($pagamento['referencia']) . '</td>
        <td><strong>Status:</strong> <span style="color:#27ae60;">CONFIRMADO</span></td>
    </tr>
</table>

<!-- DADOS DO CLIENTE -->
<table style="width:100%; border-collapse:collapse; margin-bottom:10px;" cellpadding="5" cellspacing="0">
    <tr style="background-color:#003366;">
        <td colspan="4" style="color:white; font-weight:bold;">DADOS DO CLIENTE</td>
    </tr>
    <tr>
        <td style="width:25%; border-bottom:1px solid #ddd;"><strong>Nome:</strong> ' . htmlspecialchars($ficha['nome']) . '</td>
        <td style="width:25%; border-bottom:1px solid #ddd;"><strong>Classe:</strong> ' . htmlspecialchars($ficha['classe']) . '</td>
        <td style="width:25%; border-bottom:1px solid #ddd;"><strong>Escola:</strong> ' . htmlspecialchars($ficha['escola']) . '</td>
        <td style="width:25%; border-bottom:1px solid #ddd;"><strong>Contacto:</strong> ' . htmlspecialchars($ficha['contacto_encarregado']) . '</td>
    </tr>
    <tr>
        <td><strong>Nível:</strong> ' . htmlspecialchars($nivel_label) . '</td>
        <td colspan="3"><strong>Localização:</strong> ' . htmlspecialchars(substr($ficha['localizacao'], 0, 40)) . '</td>
    </tr>
</table>

<!-- DETALHES DO SERVIÇO -->
<table style="width:100%; border-collapse:collapse; margin-bottom:10px;" cellpadding="5" cellspacing="0">
    <tr style="background-color:#003366;">
        <td colspan="2" style="color:white; font-weight:bold;">DETALHES DO SERVIÇO</td>
    </tr>
    <tr>
        <td style="width:50%; border-bottom:1px solid #ddd;"><strong>Pacote Contratado:</strong> ' . $pacote_nome . '</td>
        <td style="width:50%; border-bottom:1px solid #ddd;"><strong>Regime das Aulas:</strong> ' . htmlspecialchars($regime_str) . '</td>
    </tr>
</table>

<!-- HORÁRIOS DAS AULAS -->
<table style="width:100%; border-collapse:collapse; margin-bottom:10px;" cellpadding="5" cellspacing="0">
    <tr style="background-color:#003366;">
        <td colspan="2" style="color:white; font-weight:bold;">HORÁRIOS DAS AULAS</td>
    </tr>
    <tr style="background-color:#e6f0fa;">
        <th style="width:50%; text-align:left;">Dia da Semana</th>
        <th style="width:50%; text-align:center;">Horário</th>
    </tr>
    ' . $horarios_table . '
</table>';

// Endereço de domicílio se aplicável
if ($ficha['regime_domicilio'] && $ficha['localizacao']) {
    $html .= '
    <table style="width:100%; background-color:#f9f9f9; margin-bottom:10px;" cellpadding="6" cellspacing="0">
        <tr>
            <td><strong>Endereço para Atendimento:</strong><br>' . nl2br(htmlspecialchars($ficha['localizacao'])) . '</td>
        </tr>
    </table>';
}

// RESUMO FINANCEIRO
$html .= '
<table style="width:100%; border-collapse:collapse; margin-bottom:10px;" cellpadding="5" cellspacing="0">
    <tr style="background-color:#003366;">
        <td style="color:white; font-weight:bold;">RESUMO FINANCEIRO</td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #ddd;">Valor do Pacote (' . $pacote_nome . '): ' . number_format($valor_base, 0, ',', '.') . ' MZN</td>
    </tr>';

if ($ficha['regime_domicilio']) {
    $html .= '
    <tr>
        <td style="border-bottom:1px solid #ddd;">Taxa de Domicílio: + 1.000 MZN</td>
    </tr>';
}

$html .= '
    <tr style="background-color:#f0f0f0;">
        <td style="padding:8px; text-align:right;"><strong style="font-size:14px; color:#003366;">TOTAL PAGO: ' . number_format($pagamento['valor'], 0, ',', '.') . ' MZN</strong></td>
    </tr>
</table>';

// Observações
if (!empty($ficha['dificuldade'])) {
    $html .= '
    <table style="width:100%; background-color:#f9f9f9; margin-bottom:10px;" cellpadding="6" cellspacing="0">
        <tr>
            <td><strong>Observações:</strong><br>' . nl2br(htmlspecialchars($ficha['dificuldade'])) . '</td>
        </tr>
    </table>';
}

// Rodapé
$html .= '
<table style="width:100%; margin-top:15px; border-top:1px solid #ddd; padding-top:8px;">
    <tr>
        <td align="center" style="color:#999; font-size:7px;">
            Documento emitido eletronicamente - Válido em todo território nacional<br>
            Código de Verificação: ' . $referencia . ' | Emitido: ' . date('d/m/Y H:i:s') . '<br>
            Active Learning Academy - Transformando conhecimento em sucesso!
        </td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("Recibo_" . $pagamento['referencia'] . ".pdf", "I");
?>