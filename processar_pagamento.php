<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php'; // conexão PDO

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_POST['ficha_id'], $_POST['valor'], $_POST['referencia'], $_POST['metodo'], $_POST['pin'])) {
    die("Dados incompletos.");
}

$ficha_id   = intval($_POST['ficha_id']);
$valor      = floatval($_POST['valor']);
$referencia = trim($_POST['referencia']);
$metodo     = trim($_POST['metodo']);
$pin        = trim($_POST['pin']);

// 1. Validar PIN (simulação)
if (strlen($pin) != 6 || !ctype_digit($pin)) {
    die("PIN inválido. O PIN deve ter 6 dígitos.");
}
$pin_valido = ($pin === '123456'); // Apenas para teste
if (!$pin_valido) {
    die("PIN incorreto. Tente novamente.");
}

// 2. Verificar se a ficha existe e pertence ao utilizador
$stmt = $conn->prepare("SELECT id, valor_total, nome, pacote, regime_domicilio, usuario_id FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $_SESSION['usuario_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ficha) {
    die("Ficha não encontrada.");
}

// 3. Verificar se já existe pagamento confirmado para esta ficha
$stmt = $conn->prepare("SELECT id FROM pagamentos WHERE ficha_id = ? AND estado = 'pago'");
$stmt->execute([$ficha_id]);
if ($stmt->fetch()) {
    die("Esta ficha já foi paga.");
}

// 4. Iniciar transação
$conn->beginTransaction();

try {
    // 4.1 Inserir pagamento confirmado
    $stmt = $conn->prepare("INSERT INTO pagamentos (ficha_id, referencia, metodo, valor, estado, confirmado_em) 
                            VALUES (?, ?, ?, ?, 'pago', NOW())");
    $stmt->execute([$ficha_id, $referencia, $metodo, $valor]);

    // 4.2 Atualizar status da ficha para 'pago' e data de validade (30 dias)
    $stmt = $conn->prepare("UPDATE fichas SET 
                            pagamento_status = 'pago', 
                            valor_pago = ?, 
                            data_pagamento = NOW(),
                            pacote_valido_ate = DATE_ADD(NOW(), INTERVAL 30 DAY)
                            WHERE id = ?");
    $stmt->execute([$valor, $ficha_id]);

    // 4.3 Agendar aulas (se houver horários)
    $aulas_agendadas = 0;
    $stmt = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ?");
    $stmt->execute([$ficha_id]);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($horarios)) {
        $data_pagamento = new DateTime();
        $data_inicio = clone $data_pagamento;
        $data_fim = clone $data_pagamento;
        $data_fim->modify('+30 days');

        $dias_numero = [
            'segunda' => 1, 'terca' => 2, 'quarta' => 3,
            'quinta' => 4, 'sexta' => 5, 'sabado' => 6, 'domingo' => 7
        ];

        $periodo = new DatePeriod($data_inicio, new DateInterval('P1D'), $data_fim);
        foreach ($periodo as $data) {
            $dia_semana_num = (int)$data->format('N');
            $data_str = $data->format('Y-m-d');
            foreach ($horarios as $horario) {
                $dia_num = $dias_numero[$horario['dia_semana']] ?? 0;
                if ($dia_num == $dia_semana_num) {
                    $data_hora = $data_str . ' ' . $horario['horario'] . ':00';
                    // Verificar duplicado
                    $stmt_check = $conn->prepare("SELECT id FROM agendamentos_aulas WHERE aluno_id = ? AND data_hora = ?");
                    $stmt_check->execute([$ficha_id, $data_hora]);
                    if (!$stmt_check->fetch()) {
                        // Inserir aula SEM professor_id (a coluna agora permite NULL)
                        $stmt_insert = $conn->prepare("INSERT INTO agendamentos_aulas (aluno_id, data_hora, status) VALUES (?, ?, 'agendado')");
                        $stmt_insert->execute([$ficha_id, $data_hora]);
                        $aulas_agendadas++;
                    }
                }
            }
        }

        // Atualizar contadores na ficha
        $stmt = $conn->prepare("UPDATE fichas SET aulas_contratadas_mes = ?, aulas_restantes = ?, pacote_valido_ate = ? WHERE id = ?");
        $stmt->execute([$aulas_agendadas, $aulas_agendadas, $data_fim->format('Y-m-d'), $ficha_id]);
    }

    // 4.4 Notificar aluno
    if ($aulas_agendadas > 0) {
        $titulo = " Aulas Agendadas!";
        $mensagem = "Suas aulas foram agendadas com sucesso! Você tem $aulas_agendadas aulas neste mês.";
        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, link, data_criacao) VALUES (?, 'aluno', ?, ?, 'dashboard.php', NOW())");
        $stmt->execute([$_SESSION['usuario_id'], $titulo, $mensagem]);
    } else {
        // Se não houver horários, notificar que é necessário definir horários
        $titulo = "⚠️ Atenção: Defina os seus horários";
        $mensagem = "O pagamento foi confirmado, mas não foram encontrados horários de aula. Por favor, complete o seu perfil com os dias e horários.";
        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, link, data_criacao) VALUES (?, 'aluno', ?, ?, 'dashboard.php', NOW())");
        $stmt->execute([$_SESSION['usuario_id'], $titulo, $mensagem]);
    }

    // Commit da transação
    $conn->commit();

    error_log("Pagamento confirmado e aulas agendadas (ou tentativa) para ficha $ficha_id. Aulas: $aulas_agendadas");

    // Redirecionar para o recibo
    header("Location: recibo_pdf.php?referencia=" . urlencode($referencia));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    $erro_msg = $e->getMessage();
    error_log("Erro no processamento do pagamento: " . $erro_msg);
    // Mostrar erro detalhado (apenas para debug – remova em produção)
    die("Erro: " . $erro_msg);
}
?>