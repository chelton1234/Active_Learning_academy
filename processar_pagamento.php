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

// Validar PIN
if (strlen($pin) != 6 || !ctype_digit($pin)) {
    die("PIN inválido. O PIN deve ter 6 dígitos.");
}
if ($pin !== '123456') {
    die("PIN incorreto. Tente novamente.");
}

// Buscar dados da ficha (incluindo professor_id)
$stmt = $conn->prepare("SELECT id, professor_id FROM fichas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$ficha_id, $_SESSION['usuario_id']]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ficha) {
    die("Ficha não encontrada.");
}

// Verificar se já foi pago
$stmt = $conn->prepare("SELECT id FROM pagamentos WHERE ficha_id = ? AND estado = 'pago'");
$stmt->execute([$ficha_id]);
if ($stmt->fetch()) {
    die("Esta ficha já foi paga.");
}

$conn->beginTransaction();

try {
    // Registrar pagamento
    $stmt = $conn->prepare("INSERT INTO pagamentos (ficha_id, referencia, metodo, valor, estado, confirmado_em) 
                            VALUES (?, ?, ?, ?, 'pago', NOW())");
    $stmt->execute([$ficha_id, $referencia, $metodo, $valor]);

    // Atualizar ficha
    $stmt = $conn->prepare("UPDATE fichas SET 
                            pagamento_status = 'pago', 
                            valor_pago = ?, 
                            data_pagamento = NOW(),
                            pacote_valido_ate = DATE_ADD(NOW(), INTERVAL 30 DAY)
                            WHERE id = ?");
    $stmt->execute([$valor, $ficha_id]);

    // ========== AGENDAR AULAS ==========
    $aulas_agendadas = 0;
    $professor_id_ficha = $ficha['professor_id'];

    if ($professor_id_ficha) {
        // Buscar o usuario_id do professor (tabela professores guarda usuario_id)
        $stmt_prof = $conn->prepare("SELECT usuario_id FROM professores WHERE id = ?");
        $stmt_prof->execute([$professor_id_ficha]);
        $professor = $stmt_prof->fetch(PDO::FETCH_ASSOC);
        $usuario_professor_id = $professor ? $professor['usuario_id'] : null;

        if ($usuario_professor_id) {
            // Buscar horários do aluno
            $stmt = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ?");
            $stmt->execute([$ficha_id]);
            $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($horarios)) {
                $data_inicio = new DateTime();
                $data_fim = (new DateTime())->modify('+30 days');
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
                            // Normalizar horário
                            $horario_raw = $horario['horario'];
                            $hora_inicio = '00:00';
                            if (preg_match('/(\d{1,2})(?::(\d{2}))?/', $horario_raw, $matches)) {
                                $hora = (int)$matches[1];
                                $minuto = isset($matches[2]) ? (int)$matches[2] : 0;
                                $hora_inicio = sprintf("%02d:%02d", $hora, $minuto);
                            } elseif (preg_match('/(\d{1,2})h/', $horario_raw, $matches)) {
                                $hora = (int)$matches[1];
                                $hora_inicio = sprintf("%02d:00", $hora);
                            }
                            $data_hora = $data_str . ' ' . $hora_inicio . ':00';

                            // Verificar duplicado
                            $stmt_check = $conn->prepare("SELECT id FROM agendamentos_aulas WHERE aluno_id = ? AND data_hora = ?");
                            $stmt_check->execute([$ficha_id, $data_hora]);
                            if (!$stmt_check->fetch()) {
                                $stmt_insert = $conn->prepare("INSERT INTO agendamentos_aulas (aluno_id, professor_id, data_hora, status) VALUES (?, ?, ?, 'agendado')");
                                $stmt_insert->execute([$ficha_id, $usuario_professor_id, $data_hora]);
                                $aulas_agendadas++;
                            }
                        }
                    }
                }

                // Atualizar contadores
                $stmt = $conn->prepare("UPDATE fichas SET aulas_contratadas_mes = ?, aulas_restantes = ? WHERE id = ?");
                $stmt->execute([$aulas_agendadas, $aulas_agendadas, $ficha_id]);
            }
        } else {
            error_log("Professor sem usuario_id para ficha $ficha_id (professor_id: $professor_id_ficha)");
        }
    }

    // Notificações
    if ($aulas_agendadas > 0) {
        $titulo = "✅ Aulas Agendadas!";
        $mensagem = "Suas aulas foram agendadas com sucesso! Você tem $aulas_agendadas aulas neste mês.";
        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, link, data_criacao) VALUES (?, 'aluno', ?, ?, 'dashboard.php', NOW())");
        $stmt->execute([$_SESSION['usuario_id'], $titulo, $mensagem]);
    } else {
        $titulo = "⚠️ Atenção: Agendamento pendente";
        $mensagem = "Pagamento confirmado, mas não foi possível gerar as aulas automaticamente. Contacte o suporte.";
        $stmt = $conn->prepare("INSERT INTO notificacoes (usuario_id, tipo_usuario, titulo, mensagem, link, data_criacao) VALUES (?, 'aluno', ?, ?, 'dashboard.php', NOW())");
        $stmt->execute([$_SESSION['usuario_id'], $titulo, $mensagem]);
    }

    $conn->commit();
    error_log("Pagamento confirmado para ficha $ficha_id. Aulas geradas: $aulas_agendadas");

    header("Location: recibo_pdf.php?referencia=" . urlencode($referencia));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erro no pagamento: " . $e->getMessage());
    die("Erro: " . $e->getMessage());
}
?>