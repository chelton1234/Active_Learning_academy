<?php
// pagamento.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php'; // Conexão PDO $conn

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Pega ficha_id via GET
if (!isset($_GET['ficha_id'])) {
    die("Ficha não especificada.");
}

$ficha_id = intval($_GET['ficha_id']);
$valor = isset($_GET['valor']) ? floatval($_GET['valor']) : 0;
$telefone = isset($_GET['telefone']) ? preg_replace('/[^0-9]/', '', $_GET['telefone']) : '';
$metodo = isset($_GET['metodo']) ? $_GET['metodo'] : '';

// Busca dados atualizados da ficha
$stmt = $conn->prepare("SELECT * FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $_SESSION['usuario_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die("Ficha não encontrada ou não pertence ao usuário.");
}

// Valor total atualizado da ficha
$valor_total = isset($ficha['valor_total']) ? floatval($ficha['valor_total']) : $valor;

// Montar string de regimes
$regimes = [];
if (!empty($ficha['regime_presencial'])) $regimes[] = "Presencial";
if (!empty($ficha['regime_online'])) $regimes[] = "Online";
if (!empty($ficha['regime_domicilio'])) $regimes[] = "Ao Domicílio";
$regime_str = $regimes ? implode(", ", $regimes) : "Não definido";

// Traduzir nível de ensino
$nivel_label = match($ficha['nivel']) {
    'primary' => 'Ensino Primário',
    'secondary' => 'Ensino Secundário',
    'cambridge' => 'Pré-Universitário (Cambridge)',
    default => $ficha['nivel']
};

// Traduzir pacote
$pacote_label = match($ficha['pacote']) {
    'basico' => 'Básico (2x por semana)',
    'intermedio' => 'Intermediário (3x por semana)',
    'premium' => 'Premium (4x por semana)',
    default => $ficha['pacote']
};

// Gerar referência única
$referencia = 'PAY' . date('Ymd') . rand(1000, 9999);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pagamento - Reforço Escolar</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 500px;
        margin: 0 auto;
    }

    .card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        color: white;
        padding: 30px;
        text-align: center;
    }

    .card-header h1 {
        font-size: 24px;
        margin-bottom: 10px;
    }

    .card-body {
        padding: 30px;
    }

    .fatura {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .fatura p {
        margin: 12px 0;
        font-size: 14px;
        color: #555;
        display: flex;
        justify-content: space-between;
    }

    .fatura strong {
        color: #2c3e50;
    }

    .valor-destaque {
        font-size: 28px;
        font-weight: bold;
        color: #27ae60;
        text-align: center;
        margin: 15px 0;
    }

    .pin-container {
        text-align: center;
        margin: 20px 0;
    }

    .pin-input {
        width: 100%;
        padding: 15px;
        font-size: 24px;
        text-align: center;
        letter-spacing: 5px;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        font-family: monospace;
    }

    .pin-input:focus {
        outline: none;
        border-color: #3498db;
    }

    .btn-confirmar {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        border: none;
        padding: 16px;
        font-size: 18px;
        font-weight: 600;
        border-radius: 50px;
        cursor: pointer;
        width: 100%;
        transition: transform 0.2s;
        margin-top: 20px;
    }

    .btn-confirmar:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(39,174,96,0.3);
    }

    .alert {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #ffc107;
        font-size: 14px;
    }

    .info-badge {
        background: #e8f4fd;
        padding: 10px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 20px;
        font-size: 14px;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-lock"></i> Confirmar Pagamento</h1>
                <p>Digite seu PIN para finalizar a transação</p>
            </div>
            
            <div class="card-body">
                <div class="fatura">
                    <p><strong><i class="fas fa-user"></i> Aluno:</strong> <span><?= htmlspecialchars($ficha['nome']) ?></span></p>
                    <p><strong><i class="fas fa-graduation-cap"></i> Pacote:</strong> <span><?= htmlspecialchars($pacote_label) ?></span></p>
                    <p><strong><i class="fas fa-school"></i> Regime:</strong> <span><?= htmlspecialchars($regime_str) ?></span></p>
                    <p><strong><i class="fas fa-phone"></i> Telefone:</strong> <span>+258 <?= htmlspecialchars($telefone) ?></span></p>
                    <p><strong><i class="fas fa-hashtag"></i> Referência:</strong> <span><?= htmlspecialchars($referencia) ?></span></p>
                    <div class="valor-destaque">
                        <?= number_format($valor_total, 0, ',', '.') ?> <small>MZN</small>
                    </div>
                </div>
                
                <div class="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>Importante:</strong> Insira o PIN da sua conta M-Pesa ou e-Mola para confirmar o pagamento.
                </div>
                
                <div class="info-badge">
                    <i class="fas fa-shield-alt"></i> Pagamento processado com segurança
                </div>
                
                <form action="processar_pagamento.php" method="POST">
                    <input type="hidden" name="ficha_id" value="<?= $ficha_id ?>">
                    <input type="hidden" name="valor" value="<?= $valor_total ?>">
                    <input type="hidden" name="referencia" value="<?= $referencia ?>">
                    <input type="hidden" name="metodo" value="<?= htmlspecialchars($metodo) ?>">
                    <input type="hidden" name="telefone" value="<?= htmlspecialchars($telefone) ?>">
                    
                    <div class="pin-container">
                        <input type="password" name="pin" class="pin-input" maxlength="6" placeholder="••••••" required>
                    </div>
                    
                    <button type="submit" class="btn-confirmar">
                        <i class="fas fa-check-circle"></i> Confirmar Pagamento
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>