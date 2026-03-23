<?php
session_start();
require 'config.php'; // conexão com $conn

// Verifica se o aluno está logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Não autorizado");
}

// Recebe ficha_id via GET
$ficha_id = isset($_GET['ficha_id']) ? intval($_GET['ficha_id']) : 0;
if ($ficha_id <= 0) {
    die("Ficha inválida");
}

// Busca dados da ficha
$stmt = $conn->prepare("SELECT nome, pacote FROM fichas WHERE id = ?");
$stmt->execute([$ficha_id]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die("Ficha não encontrada");
}

// Busca o último pagamento da ficha
$stmt = $conn->prepare("SELECT * FROM pagamentos WHERE ficha_id = ? ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$ficha_id]);
$pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Verificar Pagamento</title>
<style>
    body { font-family: Arial, sans-serif; background:#f5f6fa; padding:20px; }
    .container { background:#fff; max-width:600px; margin:50px auto; padding:25px; border-radius:10px; 
                 box-shadow:0 4px 15px rgba(0,0,0,0.1); }
    h2 { text-align:center; margin-bottom:20px; }
    p { font-size:16px; margin:8px 0; }
    .estado { font-weight:bold; }
    .sucesso { color:green; }
    .pendente { color:orange; }
    .falhado { color:red; }
    a { display:inline-block; margin-top:20px; padding:12px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:8px; }
    a:hover { background:#0056b3; }
</style>
</head>
<body>
<div class="container">
    <h2>Estado do Pagamento</h2>
    <p><strong>Aluno:</strong> <?= htmlspecialchars($ficha['nome']) ?></p>
    <p><strong>Pacote:</strong> <?= htmlspecialchars($ficha['pacote']) ?></p>

    <?php if ($pagamento): ?>
        <p><strong>Método:</strong> <?= htmlspecialchars($pagamento['metodo']) ?></p>
        <p><strong>Valor:</strong> MZN <?= number_format($pagamento['valor'], 2) ?></p>
        <p><strong>Referência:</strong> <?= htmlspecialchars($pagamento['referencia']) ?></p>
        <p><strong>Data/Hora:</strong> <?= date("d/m/Y H:i:s", strtotime($pagamento['criado_em'])) ?></p>
        <p class="estado 
            <?= $pagamento['estado'] === 'sucesso' ? 'sucesso' : ($pagamento['estado'] === 'pendente' ? 'pendente' : 'falhado') ?>">
            Estado: <?= ucfirst($pagamento['estado']) ?>
        </p>

        <?php if ($pagamento['estado'] === 'sucesso'): ?>
            <a href="recibo_pdf.php?referencia=<?= urlencode($pagamento['referencia']) ?>" target="_blank">
                
            </a>
        <?php endif; ?>

    <?php else: ?>
        <p>Nenhum pagamento registrado para esta ficha.</p>
    <?php endif; ?>

    <a href="dashboard.php">Voltar à Dashboard</a>
</div>
</body>
</html>
