<?php
// agendar_aulas_para_aluno.php
require_once 'config.php';

function agendarAulas($conn, $ficha_id, $professor_id) {
    // Buscar horários do aluno
    $stmt = $conn->prepare("SELECT dia_semana, horario FROM horarios_aulas WHERE ficha_id = ?");
    $stmt->execute([$ficha_id]);
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($horarios)) {
        return ['success' => false, 'message' => 'Aluno sem horários definidos.'];
    }

    // Buscar data de início do pacote (data de pagamento)
    $stmt = $conn->prepare("SELECT data_pagamento, pacote_valido_ate FROM fichas WHERE id = ?");
    $stmt->execute([$ficha_id]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ficha || !$ficha['data_pagamento']) {
        return ['success' => false, 'message' => 'Ficha sem data de pagamento.'];
    }

    $data_inicio = new DateTime($ficha['data_pagamento']);
    $data_fim = new DateTime($ficha['pacote_valido_ate'] ?? $ficha['data_pagamento']);
    $data_fim->modify('+30 days');

    $dias_numero = [
        'segunda' => 1, 'terca' => 2, 'quarta' => 3,
        'quinta' => 4, 'sexta' => 5, 'sabado' => 6, 'domingo' => 7
    ];

    $aulas_agendadas = 0;
    $periodo = new DatePeriod($data_inicio, new DateInterval('P1D'), $data_fim);

    foreach ($periodo as $data) {
        $dia_semana_num = (int)$data->format('N');
        $data_str = $data->format('Y-m-d');
        foreach ($horarios as $horario) {
            $dia_num = $dias_numero[$horario['dia_semana']] ?? 0;
            if ($dia_num == $dia_semana_num) {
                $data_hora = $data_str . ' ' . $horario['horario'] . ':00';
                $stmt_check = $conn->prepare("SELECT id FROM agendamentos_aulas WHERE aluno_id = ? AND data_hora = ?");
                $stmt_check->execute([$ficha_id, $data_hora]);
                if (!$stmt_check->fetch()) {
                    $stmt_insert = $conn->prepare("INSERT INTO agendamentos_aulas (aluno_id, professor_id, data_hora, status) VALUES (?, ?, ?, 'agendado')");
                    $stmt_insert->execute([$ficha_id, $professor_id, $data_hora]);
                    $aulas_agendadas++;
                }
            }
        }
    }

    // Atualizar campos na ficha
    $stmt = $conn->prepare("UPDATE fichas SET aulas_contratadas_mes = ?, aulas_restantes = ? WHERE id = ?");
    $stmt->execute([$aulas_agendadas, $aulas_agendadas, $ficha_id]);

    return ['success' => true, 'aulas_agendadas' => $aulas_agendadas];
}

// Exemplo de uso (se chamado via GET/POST com ficha_id e professor_id)
if (isset($_GET['ficha_id']) && isset($_GET['professor_id'])) {
    $ficha_id = intval($_GET['ficha_id']);
    $professor_id = intval($_GET['professor_id']);
    $resultado = agendarAulas($conn, $ficha_id, $professor_id);
    header('Content-Type: application/json');
    echo json_encode($resultado);
}
?>