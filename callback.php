<?php
// callback.php - Processamento do pagamento após confirmação do PIN
session_start();
require 'config.php';

header('Content-Type: application/json');

// Verifica se o aluno está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verifica se os dados foram enviados
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$ficha_id   = intval($input['ficha_id'] ?? 0);
$valor      = floatval($input['valor'] ?? 0);
$referencia = trim($input['referencia'] ?? '');
$metodo     = trim($input['metodo'] ?? '');
$pin        = trim($input['pin'] ?? '');

// Validar dados
if (!$ficha_id || !$valor || !$referencia || !$metodo || !$pin) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

// Validar PIN (simulação - em produção, integrar com API real)
if (strlen($pin) != 6 || !ctype_digit($pin)) {
    echo json_encode(['success' => false, 'message' => 'PIN inválido. Deve ter 6 dígitos.']);
    exit;
}

// Simular validação do PIN (em produção, isso seria feito via API)
$pin_valido = ($pin === '123456'); // Exemplo: PIN válido é 123456

if (!$pin_valido) {
    echo json_encode(['success' => false, 'message' => 'PIN incorreto. Tente novamente.']);
    exit;
}

// Verificar se a ficha existe e pertence ao usuário
$stmt = $conn->prepare("SELECT id, valor_total, nome FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $_SESSION['usuario_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    echo json_encode(['success' => false, 'message' => 'Ficha não encontrada']);
    exit;
}

// Verificar se já existe um pagamento confirmado
$stmt = $conn->prepare("SELECT id FROM pagamentos WHERE ficha_id = ? AND estado = 'pago'");
$stmt->execute([$ficha_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Esta ficha já foi paga']);
    exit;
}

// Atualizar ou criar pagamento
$stmt = $conn->prepare("UPDATE pagamentos SET estado = 'pago', confirmado_em = NOW() 
                        WHERE referencia = ? AND ficha_id = ?");
$stmt->execute([$referencia, $ficha_id]);

if ($stmt->rowCount() == 0) {
    // Criar novo pagamento
    $stmt = $conn->prepare("INSERT INTO pagamentos (ficha_id, referencia, metodo, valor, estado, confirmado_em) 
                            VALUES (?, ?, ?, ?, 'pago', NOW())");
    $stmt->execute([$ficha_id, $referencia, $metodo, $valor]);
}

// Atualizar status da ficha
$stmt = $conn->prepare("UPDATE fichas SET pagamento_status = 'pago', 
                        valor_pago = ?, data_pagamento = NOW() 
                        WHERE id = ?");
$stmt->execute([$valor, $ficha_id]);

// Registrar no log
error_log("✅ Pagamento confirmado via callback - Ficha: $ficha_id, Ref: $referencia");

// Retornar sucesso com URL do recibo
echo json_encode([
    'success' => true,
    'message' => 'Pagamento confirmado com sucesso!',
    'recibo_url' => 'recibo_pdf.php?referencia=' . urlencode($referencia)
]);
exit;
?>