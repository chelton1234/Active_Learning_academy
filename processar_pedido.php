<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão: ' . $conn->connect_error]);
    exit;
}

// Garantir colunas necessárias
$conn->query("ALTER TABLE pedidos_explicadores ADD COLUMN IF NOT EXISTS status ENUM('pendente','aprovado','rejeitado') DEFAULT 'pendente'");
$conn->query("ALTER TABLE pedidos_explicadores MODIFY usuario_id INT NULL DEFAULT NULL");

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input || !isset($input['dados'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum dado recebido.']);
    exit;
}
$dados = $input['dados'];

$nome = trim($conn->real_escape_string($dados['nome'] ?? ''));
$email = trim($conn->real_escape_string($dados['email'] ?? ''));
$contacto = trim($conn->real_escape_string($dados['contacto'] ?? ''));
$localizacao = trim($conn->real_escape_string($dados['localizacao'] ?? ''));
$nivel = trim($conn->real_escape_string($dados['nivel'] ?? ''));
$tipoAula = $dados['tipoAula'] ?? '';
$pacote = $dados['pacote'] ?? '';
$preco_base = isset($dados['preco_base']) ? (int)$dados['preco_base'] : 0;
$preco_total = isset($dados['preco_total']) ? (int)$dados['preco_total'] : 0;
$diasArray = $dados['dias'] ?? [];
$horario = trim($conn->real_escape_string($dados['horario'] ?? ''));
$observacoes = trim($conn->real_escape_string($dados['dificuldade'] ?? ''));

if (empty($nome) || empty($email) || empty($contacto) || empty($nivel) || empty($tipoAula) || empty($pacote) || empty($horario)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Todos os campos obrigatórios devem ser preenchidos.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Email inválido.']);
    exit;
}
if (strlen(preg_replace('/\s+/', '', $contacto)) < 9) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Contacto inválido (mínimo 9 dígitos).']);
    exit;
}
if (!in_array($tipoAula, ['presencial', 'domicilio'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo de aula inválido.']);
    exit;
}
$pacotesValidos = ['2dias', '3dias', '4dias'];
if (!in_array($pacote, $pacotesValidos)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pacote inválido.']);
    exit;
}
$limite = ($pacote == '2dias') ? 2 : (($pacote == '3dias') ? 3 : 4);
if (count($diasArray) != $limite) {
    echo json_encode(['sucesso' => false, 'mensagem' => "Selecione exatamente $limite dia(s) da semana."]);
    exit;
}

$diasString = implode(', ', $diasArray);

$sql = "INSERT INTO pedidos_explicadores 
        (nome, email, contacto, localizacao, nivel_cambridge, tipo_aula, pacote, 
         preco_base, preco_total, dias_semana, horario, observacoes, status, data_submissao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssiisss", 
    $nome, $email, $contacto, $localizacao, $nivel, $tipoAula, $pacote,
    $preco_base, $preco_total, $diasString, $horario, $observacoes
);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true, 'mensagem' => 'Pedido registado com sucesso! Entraremos em contacto brevemente.']);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>