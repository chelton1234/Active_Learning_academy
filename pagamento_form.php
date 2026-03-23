<?php
// pagamento_form.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['ficha_id'])) {
    die("Ficha não especificada.");
}

$ficha_id = intval($_GET['ficha_id']);
$usuario_id = $_SESSION['usuario_id'];

// Buscar ficha completa
$stmt = $conn->prepare("SELECT * FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $usuario_id]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die("Ficha não encontrada.");
}

// Buscar horários do aluno
$stmt = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ? ORDER BY 
                        FIELD(dia_semana, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')");
$stmt->execute([$ficha_id]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar telefone do aluno
$telefone = $ficha['contacto_encarregado'] ?? $ficha['contacto'] ?? '';

// Calcular valor baseado no pacote
$valor_pacote = 0;
$pacote_label = '';

switch($ficha['pacote']) {
    case 'basico':
        $valor_pacote = 3000;
        $pacote_label = 'Básico (2x/semana)';
        break;
    case 'intermedio':
        $valor_pacote = 4000;
        $pacote_label = 'Intermediário (3x/semana)';
        break;
    case 'premium':
        $valor_pacote = 5000;
        $pacote_label = 'Premium (4x/semana)';
        break;
    default:
        $valor_pacote = 0;
        $pacote_label = ucfirst($ficha['pacote']);
}

$valor_adicional = ($ficha['regime_domicilio'] ?? 0) ? 1000 : 0;
$valor_total = $valor_pacote + $valor_adicional;

// Nomes dos dias em português
$dias_nomes = [
    'segunda' => 'Segunda-feira',
    'terca' => 'Terça-feira',
    'quarta' => 'Quarta-feira',
    'quinta' => 'Quinta-feira',
    'sexta' => 'Sexta-feira',
    'sabado' => 'Sábado',
    'domingo' => 'Domingo'
];
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
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.8;
        }
        .content {
            padding: 30px;
        }
        .resumo {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .resumo-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .resumo-item:last-child {
            border-bottom: none;
        }
        .resumo-label {
            font-weight: 600;
            color: #495057;
        }
        .resumo-valor {
            color: #2c3e50;
            font-weight: 500;
        }
        .total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
        }
        .total .resumo-label {
            font-size: 1.2rem;
            color: #2c3e50;
        }
        .total .resumo-valor {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .horarios-list {
            margin-top: 15px;
            background: #fff3e0;
            padding: 15px;
            border-radius: 10px;
        }
        .horario-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #ffd966;
        }
        .horario-item:last-child {
            border-bottom: none;
        }
        .horario-dia {
            font-weight: 600;
            color: #b45f06;
        }
        .horario-hora {
            color: #e67e22;
        }
        .metodo-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .info {
            background: #e7f3ff;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #2c3e50;
        }
        .regime-tag {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 12px;
            margin: 0 2px;
            display: inline-block;
        }
        .telefone-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Pagamento da Inscrição</h1>
            <p>Escolha o método de pagamento</p>
        </div>
        
        <div class="content">
            <div class="resumo">
                <div class="resumo-item">
                    <span class="resumo-label">👤 Aluno:</span>
                    <span class="resumo-valor"><?= htmlspecialchars($ficha['nome']) ?></span>
                </div>
                <div class="resumo-item">
                    <span class="resumo-label">📦 Pacote:</span>
                    <span class="resumo-valor"><?= $pacote_label ?></span>
                </div>
                
                <!-- HORÁRIOS -->
                <?php if (!empty($horarios)): ?>
                <div class="horarios-list">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #b45f06;">⏰ Horários das Aulas:</div>
                    <?php foreach ($horarios as $h): ?>
                    <div class="horario-item">
                        <span class="horario-dia"><?= $dias_nomes[$h['dia_semana']] ?? $h['dia_semana'] ?></span>
                        <span class="horario-hora"><?= htmlspecialchars($h['horario']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="resumo-item">
                    <span class="resumo-label">🏫 Regime:</span>
                    <span class="resumo-valor">
                        <?php if ($ficha['regime_presencial']): ?>
                            <span class="regime-tag">Presencial</span>
                        <?php endif; ?>
                        <?php if ($ficha['regime_online']): ?>
                            <span class="regime-tag">Online</span>
                        <?php endif; ?>
                        <?php if ($ficha['regime_domicilio']): ?>
                            <span class="regime-tag">Domicílio (+1000 MT)</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="resumo-item total">
                    <span class="resumo-label">💰 Total a Pagar:</span>
                    <span class="resumo-valor"><?= number_format($valor_total, 0, ',', '.') ?> MT</span>
                </div>
            </div>
            
            <!-- FORMULÁRIO PARA ESCOLHER MÉTODO (SEM PIN) -->
            <form action="pagamento.php" method="GET">
                <input type="hidden" name="ficha_id" value="<?= $ficha_id ?>">
                <input type="hidden" name="valor" value="<?= $valor_total ?>">
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">📱 Número de Telefone:</label>
                <input type="tel" name="telefone" class="telefone-input" 
                       placeholder="84 123 4567" value="<?= htmlspecialchars($telefone) ?>" required>
                
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">💳 Escolha o método de pagamento:</label>
                <select name="metodo" class="metodo-select" required>
                    <option value="">Selecione...</option>
                    <option value="Mpesa">📱 M-Pesa</option>
                    <option value="Emola">📱 e-Mola</option>
                    <option value="Transferência">🏦 Transferência Bancária</option>
                </select>
                
                <button type="submit">✅ Continuar para Confirmação</button>
            </form>
            
            <div class="info">
                <i class="fas fa-lock"></i> 
                <strong>Pagamento Seguro:</strong> Na próxima etapa você confirmará o pagamento com seu PIN.
            </div>
        </div>
    </div>
</body>
</html>