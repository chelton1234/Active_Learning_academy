<?php
// aprovar_pedido.php - Aprovacao de pedido com insercao na tabela fichas
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = isset($input['pedido_id']) ? (int)$input['pedido_id'] : 0;
$professor_id = isset($input['professor_id']) ? (int)$input['professor_id'] : 0;

if (!$pedido_id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do pedido inválido']);
    exit;
}

// Buscar pedido
$stmt = $conn->prepare("SELECT * FROM pedidos_explicadores WHERE id = ? AND status = 'pendente'");
$stmt->bind_param("i", $pedido_id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado ou já processado']);
    exit;
}

// Verificar se email já existe
$check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$check->bind_param("s", $pedido['email']);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Email já registado no sistema']);
    $check->close();
    exit;
}
$check->close();

// Gerar senha aleatória
$senha = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$conn->begin_transaction();

try {
    // 1. Inserir utilizador
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ativo, data_criacao) VALUES (?, ?, ?, 'aluno', 1, NOW())");
    $stmt->bind_param("sss", $pedido['nome'], $pedido['email'], $senha_hash);
    if (!$stmt->execute()) throw new Exception("Erro ao criar utilizador: " . $stmt->error);
    $usuario_id = $stmt->insert_id;
    $stmt->close();

    // 2. Buscar nome do professor
    $prof_nome = '';
    if ($professor_id) {
        $stmt2 = $conn->prepare("SELECT u.nome FROM professores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?");
        $stmt2->bind_param("i", $professor_id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        if ($row = $res->fetch_assoc()) $prof_nome = $row['nome'];
        $stmt2->close();
    }

    // 3. Preparar dados para a tabela fichas
    $regime_presencial = ($pedido['tipo_aula'] == 'presencial') ? 1 : 0;
    $regime_domicilio = ($pedido['tipo_aula'] == 'domicilio') ? 1 : 0;
    $dias_semana_json = json_encode(['dias' => explode(', ', $pedido['dias_semana'])]);
    $horarios_json = json_encode(['preferencial' => $pedido['horario']]);
    $nivel_cambridge = $pedido['nivel_cambridge'];
    $pacote_nome = $pedido['pacote']; // 2dias,3dias,4dias
    $valor_total = $pedido['preco_total'];
    $valor_mensal = $pedido['preco_base'];
    $observacoes = $pedido['observacoes'];
    $contacto = $pedido['contacto'];
    $localizacao = $pedido['localizacao'];

    // 4. Inserir ficha (apenas colunas que existem)
    $sql = "INSERT INTO fichas 
            (usuario_id, nome, email, contacto_encarregado, localizacao, nivel, nivel_cambridge, 
             pacote, regime_presencial, regime_domicilio, dias_semana, horarios_json, dificuldade, 
             valor_total, valor_mensal, pagamento_status, professor_id, professor_atribuido, data_submissao)
            VALUES (?, ?, ?, ?, ?, 'cambridge', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, NOW())";
    
    $stmt3 = $conn->prepare($sql);
    $stmt3->bind_param("isssssiisssddiis", 
        $usuario_id,                       // i
        $pedido['nome'],                   // s
        $pedido['email'],                  // s
        $contacto,                         // s (contacto_encarregado)
        $localizacao,                      // s
        $nivel_cambridge,                  // s (nivel_cambridge)
        $pacote_nome,                      // s (pacote)
        $regime_presencial,                // i
        $regime_domicilio,                 // i
        $dias_semana_json,                 // s
        $horarios_json,                    // s
        $observacoes,                      // s (dificuldade)
        $valor_total,                      // d
        $valor_mensal,                     // d
        $professor_id,                     // i
        $prof_nome                         // s
    );
    if (!$stmt3->execute()) throw new Exception("Erro ao criar ficha: " . $stmt3->error);
    $ficha_id = $stmt3->insert_id;
    $stmt3->close();

    // 5. Atualizar pedido
    $stmt4 = $conn->prepare("UPDATE pedidos_explicadores SET status = 'aprovado', ficha_id = ?, usuario_id = ? WHERE id = ?");
    $stmt4->bind_param("iii", $ficha_id, $usuario_id, $pedido_id);
    $stmt4->execute();
    $stmt4->close();

    $conn->commit();

    // Enviar email (opcional)
    $assunto = "Bem-vindo à plataforma WebTeaching - Credenciais de acesso";
    $mensagem_email = "Olá {$pedido['nome']},<br><br>Seu pedido foi aprovado!<br>
                       Acesse o sistema com:<br>
                       Email: {$pedido['email']}<br>
                       Senha: {$senha}<br>
                       <a href='http://localhost/Active_Learning_Academy/login.php'>Clique aqui para fazer login</a><br><br>
                       Atenciosamente,<br>Equipa WebTeaching";
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: noreply@webteaching.com\r\n";
    // mail($pedido['email'], $assunto, $mensagem_email, $headers); // Descomente quando tiver email configurado

    echo json_encode([
        'sucesso' => true,
        'mensagem' => "Pedido aprovado! Aluno criado com senha: $senha",
        'ficha_id' => $ficha_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}

$conn->close();
?>