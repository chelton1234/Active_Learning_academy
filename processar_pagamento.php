<?php
// processar_pagamento.php
session_start();
require 'config.php'; // conexão com $conn (PDO)

// Verifica se o aluno está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se os dados foram enviados
if (!isset($_POST['ficha_id'], $_POST['valor'], $_POST['referencia'], $_POST['metodo'], $_POST['pin'])) {
    die("Dados incompletos.");
}

$ficha_id   = intval($_POST['ficha_id']);
$valor      = floatval($_POST['valor']);
$referencia = trim($_POST['referencia']);
$metodo     = trim($_POST['metodo']);
$pin        = trim($_POST['pin']);

// Validar PIN (simulação - em produção, integrar com API real)
if (strlen($pin) != 6 || !ctype_digit($pin)) {
    die("PIN inválido. O PIN deve ter 6 dígitos.");
}

// Simular validação do PIN (em produção, isso seria feito via API)
$pin_valido = ($pin === '123456'); // Exemplo: PIN válido é 123456

if (!$pin_valido) {
    echo "<!DOCTYPE html>
    <html lang='pt'>
    <head>
        <meta charset='UTF-8'>
        <title>Erro no Pagamento</title>
        <style>
            body { font-family: Arial, sans-serif; background:#f5f6fa; padding:20px; }
            .container { max-width:500px; margin:50px auto; background:#fff; border-radius:10px; padding:30px; text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
            .error { color:#e74c3c; font-size:18px; margin-bottom:20px; }
            .btn { display:inline-block; padding:12px 24px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px; margin-top:20px; }
            .btn:hover { background:#2980b9; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error'>❌ PIN Inválido</div>
            <p>O PIN informado não corresponde ao registado na sua conta.</p>
            <p>Por favor, tente novamente.</p>
            <a href='pagamento.php?ficha_id=$ficha_id' class='btn'>Voltar e Tentar Novamente</a>
        </div>
    </body>
    </html>";
    exit;
}

// Verificar se a ficha existe e pertence ao usuário
$stmt = $conn->prepare("SELECT id, valor_total, nome FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $_SESSION['usuario_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die("Ficha não encontrada ou não pertence ao usuário.");
}

// Verificar se já existe um pagamento confirmado para esta ficha
$stmt = $conn->prepare("SELECT id, estado FROM pagamentos WHERE ficha_id = ? AND estado = 'pago'");
$stmt->execute([$ficha_id]);
$pagamento_existente = $stmt->fetch();

if ($pagamento_existente) {
    die("Esta ficha já possui um pagamento confirmado.");
}

// Atualizar o pagamento existente (pendente) para confirmado
$stmt = $conn->prepare("UPDATE pagamentos 
                        SET estado = 'pago', confirmado_em = NOW() 
                        WHERE referencia = ? AND ficha_id = ?");
$stmt->execute([$referencia, $ficha_id]);

$linhas_afetadas = $stmt->rowCount();

if ($linhas_afetadas == 0) {
    // Se não encontrou, criar novo pagamento como pago
    $stmt = $conn->prepare("INSERT INTO pagamentos (ficha_id, referencia, metodo, valor, estado, confirmado_em) 
                            VALUES (?, ?, ?, ?, 'pago', NOW())");
    $stmt->execute([$ficha_id, $referencia, $metodo, $valor]);
}

// Atualizar status da ficha para 'pago'
$stmt = $conn->prepare("UPDATE fichas SET pagamento_status = 'pago', 
                        valor_pago = ?, data_pagamento = NOW() 
                        WHERE id = ?");
$stmt->execute([$valor, $ficha_id]);

// Registrar no log
error_log("✅ Pagamento confirmado - Ficha: $ficha_id, Referência: $referencia, Valor: $valor MZN, Método: $metodo");

// Redirecionar para o recibo
header("Location: recibo_pdf.php?referencia=" . urlencode($referencia));
exit;
?>