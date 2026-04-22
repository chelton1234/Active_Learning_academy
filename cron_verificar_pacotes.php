
<?php
/**
 * cron_verificar_pacotes.php
 * Executar diariamente para verificar pacotes a expirar
 */

require_once 'config.php';
require_once 'notificacoes.php';
require_once 'funcoes_pacotes.php';

function verificarPacotesParaRenovacao($conn) {
    $hoje = new DateTime();
    $hoje_str = $hoje->format('Y-m-d');
    
    // Datas para verificação
    $data_10dias = clone $hoje;
    $data_10dias->modify('+10 days');
    $data_10dias_str = $data_10dias->format('Y-m-d');
    
    $data_5dias = clone $hoje;
    $data_5dias->modify('+5 days');
    $data_5dias_str = $data_5dias->format('Y-m-d');
    
    $data_1dia = clone $hoje;
    $data_1dia->modify('+1 day');
    $data_1dia_str = $data_1dia->format('Y-m-d');
    
    $resultados = [
        '10_dias' => [],
        '5_dias' => [],
        '1_dia' => [],
        'expirados' => [],
        'total' => 0
    ];
    
    // NOTIFICAR 10 DIAS ANTES
    $sql = "SELECT f.*, u.nome as aluno_nome, u.id as usuario_id
            FROM fichas f
            JOIN usuarios u ON u.id = f.usuario_id
            WHERE f.pagamento_status = 'pago' 
            AND DATE(f.pacote_valido_ate) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data_10dias_str]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alunos as $aluno) {
        $titulo = "Seu pacote vence em 10 dias!";
        $mensagem = "Olá " . $aluno['aluno_nome'] . "!\n\n" .
                    "Seu pacote de aulas vence em 10 dias.\n" .
                    "Renove agora para não perder suas aulas.";
        $link = "pagamento_form.php?ficha_id=" . $aluno['id'] . "&renovacao=1";
        
        $notif_id = criarNotificacao($conn, $aluno['usuario_id'], 'aluno', $titulo, $mensagem, $link);
        if ($notif_id) {
            $resultados['10_dias'][] = $aluno['aluno_nome'];
            $resultados['total']++;
        }
    }
    
    // NOTIFICAR 5 DIAS ANTES
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data_5dias_str]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alunos as $aluno) {
        $titulo = "Atenção! Seu pacote vence em 5 dias!";
        $mensagem = "Olá " . $aluno['aluno_nome'] . "!\n\n" .
                    "SEU PACOTE VENCE EM 5 DIAS!\n\n" .
                    "Renove agora!";
        $link = "pagamento_form.php?ficha_id=" . $aluno['id'] . "&renovacao=1";
        
        $notif_id = criarNotificacao($conn, $aluno['usuario_id'], 'aluno', $titulo, $mensagem, $link);
        if ($notif_id) {
            $resultados['5_dias'][] = $aluno['aluno_nome'];
            $resultados['total']++;
        }
    }
    
    // NOTIFICAR 1 DIA ANTES
    $stmt = $conn->prepare($sql);
    $stmt->execute([$data_1dia_str]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alunos as $aluno) {
        $titulo = "ULTIMO DIA! Seu pacote vence amanha!";
        $mensagem = "Olá " . $aluno['aluno_nome'] . "!\n\n" .
                    "ATENCAO! Seu pacote vence AMANHA!\n\n" .
                    "Renova hoje para continuar suas aulas!";
        $link = "pagamento_form.php?ficha_id=" . $aluno['id'] . "&renovacao=1";
        
        $notif_id = criarNotificacao($conn, $aluno['usuario_id'], 'aluno', $titulo, $mensagem, $link);
        if ($notif_id) {
            $resultados['1_dia'][] = $aluno['aluno_nome'];
            $resultados['total']++;
        }
    }
    
    // MARCAR PACOTES EXPIRADOS
    $sql_exp = "SELECT f.*, u.nome as aluno_nome, u.id as usuario_id
                FROM fichas f
                JOIN usuarios u ON u.id = f.usuario_id
                WHERE f.pagamento_status = 'pago' 
                AND DATE(f.pacote_valido_ate) < ?";
    $stmt = $conn->prepare($sql_exp);
    $stmt->execute([$hoje_str]);
    $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alunos as $aluno) {
        $stmt_update = $conn->prepare("UPDATE fichas SET pagamento_status = 'expirado' WHERE id = ?");
        $stmt_update->execute([$aluno['id']]);
        
        $titulo = "Seu pacote expirou!";
        $mensagem = "Olá " . $aluno['aluno_nome'] . "!\n\n" .
                    "Seu pacote de aulas expirou.\n\n" .
                    "Renove agora para continuar as aulas!";
        $link = "pagamento_form.php?ficha_id=" . $aluno['id'] . "&renovacao=1";
        
        $notif_id = criarNotificacao($conn, $aluno['usuario_id'], 'aluno', $titulo, $mensagem, $link);
        if ($notif_id) {
            $resultados['expirados'][] = $aluno['aluno_nome'];
            $resultados['total']++;
        }
    }
    
    return $resultados;
}

// Executar apenas se for chamado via linha de comando
if (php_sapi_name() === 'cli') {
    echo "========================================\n";
    echo "=== VERIFICANDO PACOTES PARA RENOVACAO ===\n";
    echo "Data: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n\n";
    
    if (!file_exists('config.php')) {
        echo "ERRO: config.php nao encontrado!\n";
        echo "Diretorio atual: " . __DIR__ . "\n";
        exit(1);
    }
    
    echo "Config.php encontrado\n";
    echo "Conectando ao banco de dados...\n\n";
    
    $resultados = verificarPacotesParaRenovacao($conn);
    
    echo "RESULTADOS:\n";
    echo "--------------------------------------------------\n";
    echo "Notificacoes de 10 dias: " . count($resultados['10_dias']) . "\n";
    echo "Notificacoes de 5 dias: " . count($resultados['5_dias']) . "\n";
    echo "Notificacoes de 1 dia: " . count($resultados['1_dia']) . "\n";
    echo "Pacotes expirados: " . count($resultados['expirados']) . "\n";
    echo "--------------------------------------------------\n";
    echo "Total de notificacoes enviadas: " . $resultados['total'] . "\n";
    echo "Processamento concluido!\n";
}
?>