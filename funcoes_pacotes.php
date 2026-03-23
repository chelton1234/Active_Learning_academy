<?php
/**
 * funcoes_pacotes.php
 * Funções para gestão de pacotes e aulas
 */

/**
 * Obtém informações completas do pacote do aluno
 * @param mysqli $conn Conexão com o banco
 * @param int $ficha_id ID da ficha do aluno
 * @param string $pacote_nome Nome do pacote (basico, intermedio, premium)
 * @param string $data_pagamento Data do pagamento
 * @return array Informações do pacote
 */
function getInfoPacoteCompleta($conn, $ficha_id, $pacote_nome, $data_pagamento) {
    
    // Definir número de aulas por pacote
    $aulas_por_pacote = [
        'basico' => 8,      // 2x/semana durante 1 mês (aprox 8 aulas)
        'intermedio' => 12,  // 3x/semana durante 1 mês (aprox 12 aulas)
        'premium' => 16      // 4x/semana durante 1 mês (aprox 16 aulas)
    ];
    
    $aulas_contratadas = $aulas_por_pacote[$pacote_nome] ?? 0;
    
    // Contar aulas realizadas
    $sql_realizadas = "SELECT COUNT(*) as total FROM agendamentos_aulas 
                       WHERE aluno_id = ? AND status = 'realizado'";
    $stmt = $conn->prepare($sql_realizadas);
    $stmt->bind_param("i", $ficha_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $aulas_realizadas = $row['total'] ?? 0;
    
    // Calcular aulas restantes
    $aulas_restantes = max(0, $aulas_contratadas - $aulas_realizadas);
    
    // Contar créditos de reposição (aulas canceladas pelo professor)
    $sql_creditos = "SELECT COUNT(*) as total FROM agendamentos_aulas 
                     WHERE aluno_id = ? AND status = 'cancelado_professor'";
    $stmt = $conn->prepare($sql_creditos);
    $stmt->bind_param("i", $ficha_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $creditos = $row['total'] ?? 0;
    
    // Calcular datas de início e fim
    $data_inicio = $data_pagamento ? new DateTime($data_pagamento) : new DateTime();
    $data_fim = clone $data_inicio;
    $data_fim->modify('+30 days');
    
    // Calcular dias restantes
    $hoje = new DateTime();
    $dias_restantes = $data_fim->diff($hoje)->days;
    $expirado = $hoje > $data_fim;
    if ($expirado) {
        $dias_restantes = 0;
    }
    
    // Verificar se precisa renovar (menos de 7 dias)
    $precisa_renovar = $dias_restantes <= 7 && !$expirado;
    
    // Status do pagamento (assumindo que está pago)
    $status_pagamento = 'pago';
    
    return [
        'aulas_contratadas' => $aulas_contratadas,
        'aulas_realizadas' => $aulas_realizadas,
        'aulas_restantes' => $aulas_restantes,
        'creditos' => $creditos,
        'data_inicio' => $data_inicio->format('Y-m-d H:i:s'),
        'data_fim' => $data_fim->format('Y-m-d H:i:s'),
        'status_pagamento' => $status_pagamento,
        'precisa_renovar' => $precisa_renovar,
        'dias_restantes' => $dias_restantes,
        'expirado' => $expirado
    ];
}

/**
 * Calcula as aulas do período do pacote
 * @param string $pacote_nome Nome do pacote
 * @param string $data_pagamento Data do pagamento
 * @param array $horarios Horários do aluno
 * @return array Informações do período
 */
function calcularAulasDoPeriodo($pacote_nome, $data_pagamento, $horarios) {
    
    $dias_por_semana = [
        'basico' => 2,
        'intermedio' => 3,
        'premium' => 4
    ][$pacote_nome] ?? 2;
    
    $data_inicio = $data_pagamento ? new DateTime($data_pagamento) : new DateTime();
    $data_fim = clone $data_inicio;
    $data_fim->modify('+30 days');
    
    $total_dias = 0;
    $periodo = new DatePeriod($data_inicio, new DateInterval('P1D'), $data_fim);
    
    $dias_semana_aluno = [];
    foreach ($horarios as $h) {
        $dias_semana_aluno[] = $h['dia_semana'];
    }
    
    $dias_semana_portugues = [
        'sunday' => 'domingo', 'monday' => 'segunda', 'tuesday' => 'terca',
        'wednesday' => 'quarta', 'thursday' => 'quinta', 'friday' => 'sexta',
        'saturday' => 'sabado'
    ];
    
    foreach ($periodo as $data) {
        $dia_semana_ingles = strtolower($data->format('l'));
        $dia_semana = $dias_semana_portugues[$dia_semana_ingles] ?? '';
        
        if (in_array($dia_semana, $dias_semana_aluno)) {
            $total_dias++;
        }
    }
    
    return [
        'total_dias' => $total_dias,
        'data_inicio' => $data_inicio->format('Y-m-d'),
        'data_fim' => $data_fim->format('Y-m-d'),
        'dias_por_semana' => $dias_por_semana
    ];
}

/**
 * Consome uma aula do pacote
 * @param mysqli $conn Conexão com o banco
 * @param int $ficha_id ID da ficha
 * @param int $aula_id ID da aula
 * @param string $tipo Tipo de consumo (realizado, aluno, professor)
 * @return bool True se sucesso
 */
function consumirAula($conn, $ficha_id, $aula_id, $tipo) {
    // Esta função pode ser expandida conforme necessidade
    // Por exemplo, para registrar consumo em uma tabela de histórico
    
    switch ($tipo) {
        case 'realizado':
            // Aula normal consumida
            error_log("✅ Aula $aula_id consumida normalmente");
            break;
        case 'aluno':
            // Aluno cancelou - não consome
            error_log("⏸️ Aula $aula_id cancelada pelo aluno - não consumida");
            break;
        case 'professor':
            // Professor cancelou - gera crédito
            error_log("🔄 Aula $aula_id cancelada pelo professor - crédito gerado");
            break;
        default:
            error_log("⚠️ Tipo de consumo desconhecido: $tipo");
    }
    
    return true;
}
?>