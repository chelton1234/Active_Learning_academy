<?php
// recibo_pdf.php - Versão Simplificada e Profissional
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

// Buscar ficha com dados completos
$stmt = $conn->prepare("SELECT f.*, 
                        GROUP_CONCAT(CONCAT(h.dia_semana, ':', h.horario) SEPARATOR '|') as horarios_detalhes
                        FROM fichas f
                        LEFT JOIN horarios_aulas h ON f.id = h.ficha_id
                        WHERE f.id = ?
                        GROUP BY f.id");
$stmt->execute([$pagamento['ficha_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    exit("Ficha do aluno não encontrada.");
}

// Processar horários
$horarios = [];
if (!empty($ficha['horarios_detalhes'])) {
    $horarios_raw = explode('|', $ficha['horarios_detalhes']);
    foreach ($horarios_raw as $h) {
        $parts = explode(':', $h);
        if (count($parts) == 2) {
            $horarios[$parts[0]] = $parts[1];
        }
    }
}

// Dias da semana
$dias_semana = json_decode($ficha['dias_semana'], true) ?: [];
if (is_string($dias_semana)) {
    $dias_semana = json_decode($dias_semana, true) ?: [];
}

$dias_nomes = [
    'segunda' => 'Segunda-feira', 'terca' => 'Terça-feira', 'quarta' => 'Quarta-feira',
    'quinta' => 'Quinta-feira', 'sexta' => 'Sexta-feira', 'sabado' => 'Sábado', 'domingo' => 'Domingo'
];

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
$pdf->SetMargins(20, 20, 20);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// HTML Simplificado
$html = '
<table style="width:100%; border-bottom:2px solid #1a3e60; margin-bottom:20px;">
    <tr>
        <td style="width:60%;">
            <h1 style="color:#1a3e60; margin:0; font-size:22px;">ACTIVE LEARNING ACADEMY</h1>
            <p style="color:#666; margin:3px 0; font-size:9px;">Av. Marginal, Maputo | +258 84 123 4567</p>
        </td>
        <td style="width:40%; text-align:right;">
            <h2 style="color:#1a3e60; margin:0; font-size:16px;">RECIBO OFICIAL</h2>
            <p style="color:#666; margin:2px 0; font-size:9px;">Nº: ' . $referencia . '</p>
        </td>
    </tr>
</table>

<!-- Dados do Pagamento -->
<table style="width:100%; margin-bottom:15px;">
    <tr>
        <td style="width:25%;"><strong>Data:</strong></td>
        <td style="width:25%;">' . $data_pagamento . '</td>
        <td style="width:25%;"><strong>Método:</strong></td>
        <td style="width:25%;">' . htmlspecialchars($pagamento['metodo']) . '</td>
    </tr>
    <tr>
        <td><strong>Referência:</strong></td>
        <td>' . htmlspecialchars($pagamento['referencia']) . '</td>
        <td><strong>Status:</strong></td>
        <td><span style="color:#27ae60;">Confirmado</span></td>
    </tr>
</table>

<!-- Dados do Aluno -->
<h3 style="color:#1a3e60; border-left:3px solid #1a3e60; padding-left:8px; margin:15px 0 10px;">DADOS DO ALUNO</h3>
<table style="width:100%; margin-bottom:15px;">
    <tr>
        <td style="width:20%;"><strong>Nome:</strong></td>
        <td style="width:30%;">' . htmlspecialchars($ficha['nome']) . '</td>
        <td style="width:20%;"><strong>Classe:</strong></td>
        <td style="width:30%;">' . htmlspecialchars($ficha['classe']) . '</td>
    </tr>
    <tr>
        <td><strong>Escola:</strong></td>
        <td>' . htmlspecialchars($ficha['escola']) . '</td>
        <td><strong>Contacto:</strong></td>
        <td>' . htmlspecialchars($ficha['contacto_encarregado']) . '</td>
    </tr>
    <tr>
        <td><strong>Nível:</strong></td>
        <td>' . htmlspecialchars($nivel_label) . '</td>
        <td><strong>Localização:</strong></td>
        <td>' . htmlspecialchars(substr($ficha['localizacao'], 0, 35)) . '</td>
    </tr>
</table>

<!-- Pacote e Horários -->
<h3 style="color:#1a3e60; border-left:3px solid #1a3e60; padding-left:8px; margin:15px 0 10px;">PACOTE E HORÁRIOS</h3>
<table style="width:100%; margin-bottom:15px;">
    <tr>
        <td style="width:25%;"><strong>Pacote:</strong></td>
        <td style="width:75%;">' . $pacote_nome . '</td>
    </tr>
    ' . (!empty($dias_semana) ? '
    <tr>
        <td><strong>Horários:</strong></td>
        <td>' . implode(', ', array_map(function($dia) use ($dias_nomes, $horarios) {
            return $dias_nomes[$dia] . ' (' . ($horarios[$dia] ?? '--:--') . 'h)';
        }, $dias_semana)) . '</td>
    </tr>' : '') . '
    <tr>
        <td><strong>Regime:</strong></td>
        <td>' . htmlspecialchars($regime_str) . '</td>
    </tr>
</table>

<!-- Resumo Financeiro -->
<h3 style="color:#1a3e60; border-left:3px solid #1a3e60; padding-left:8px; margin:15px 0 10px;">RESUMO FINANCEIRO</h3>
<table style="width:100%; margin-bottom:15px;">
    <tr>
        <td style="width:70%;">Valor do Pacote (' . $pacote_nome . '):</td>
        <td style="width:30%; text-align:right;">' . number_format($valor_base, 0, ',', '.') . ' MZN</td>
    </tr>
    ' . ($ficha['regime_domicilio'] ? '
    <tr>
        <td>Taxa de Domicílio:</td>
        <td style="text-align:right;">+ 1.000 MZN</td>
    </tr>' : '') . '
    <tr style="border-top:1px solid #ddd;">
        <td style="padding-top:8px;"><strong>TOTAL PAGO:</strong></td>
        <td style="padding-top:8px; text-align:right;"><strong style="color:#1a3e60;">' . number_format($pagamento['valor'], 0, ',', '.') . ' MZN</strong></td>
    </tr>
</table>
';

// Observações
if (!empty($ficha['dificuldade'])) {
    $html .= '
    <div style="background:#f9f9f9; padding:8px; border-left:3px solid #f39c12; margin:10px 0;">
        <strong>Observações:</strong><br>' . nl2br(htmlspecialchars($ficha['dificuldade'])) . '
    </div>';
}

// Rodapé
$html .= '
<div style="text-align:center; margin-top:20px; padding-top:10px; border-top:1px solid #ddd;">
    <p style="color:#1a3e60; font-size:10px;">Active Learning Academy - Excelência em Educação</p>
    <p style="color:#999; font-size:8px;">Documento emitido eletronicamente - Válido em todo território nacional</p>
    <p style="color:#999; font-size:8px;">Código: ' . $referencia . ' | Emitido: ' . date('d/m/Y H:i:s') . '</p>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("Recibo_" . $pagamento['referencia'] . ".pdf", "I");
?>