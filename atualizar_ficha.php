<?php
// atualizar_ficha.php
session_start();

// Debug de erros (apenas em DEV)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1) Detectar sessão - aceitar 'usuario_id' ou 'id'
$usuario_id = null;
if (!empty($_SESSION['usuario_id'])) {
    $usuario_id = $_SESSION['usuario_id'];
} elseif (!empty($_SESSION['id'])) {
    $usuario_id = $_SESSION['id'];
}

if (!$usuario_id) {
    // Em ambiente de produção preferes redirect para login.
    // Aqui mostramos debug para facilitar a correção em desenvolvimento.
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["sucesso" => false, "mensagem" => "Sessão expirada. Faz login novamente."]);
        exit;
    }

    http_response_code(401);
    echo "<h1>Não autenticado</h1>";
    echo "<p>O script não encontrou a variável de sessão esperada. Verifica se estás mesmo logado e se a mesma chave de sessão é usada em todo o sistema.</p>";
    echo "<h3>Conteúdo de \$_SESSION</h3><pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";
    exit;
}

// --- 2) Conexão
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// --- 3) Verificar se é JSON (AJAX vindo do pagamento_form.php)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    header('Content-Type: application/json');

    $dados = json_decode(file_get_contents("php://input"), true);

    if (!$dados || empty($dados['ficha_id'])) {
        echo json_encode(["sucesso" => false, "mensagem" => "Dados insuficientes."]);
        exit;
    }

    $ficha_id = (int)$dados['ficha_id'];
    $nivel = trim($dados['nivel'] ?? '');
    $pacote = trim($dados['pacote'] ?? '');
    $presencial = !empty($dados['presencial']) ? 1 : 0;
    $online = !empty($dados['online']) ? 1 : 0;
    $hibrido = !empty($dados['domicilio']) ? 1 : 0;
    $valor_total = (float)($dados['valor_total'] ?? 0);

    // Confirma se pertence ao usuário
    $chk = $conn->prepare("SELECT id FROM fichas WHERE id = ? AND usuario_id = ? LIMIT 1");
    $chk->bind_param("ii", $ficha_id, $usuario_id);
    $chk->execute();
    $res_chk = $chk->get_result();
    if ($res_chk->num_rows === 0) {
        echo json_encode(["sucesso" => false, "mensagem" => "Ficha não pertence ao usuário."]);
        exit;
    }
    $chk->close();

    // Atualiza apenas os campos relevantes para o pagamento
    $stmt = $conn->prepare("
        UPDATE fichas
        SET classe = ?, pacote = ?, regime_presencial = ?, regime_online = ?, regime_hibrido = ?, valor_total = ?
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->bind_param("ssiiiidi", $nivel, $pacote, $presencial, $online, $hibrido, $valor_total, $ficha_id, $usuario_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["sucesso" => true]);
    exit;
}

// --- 4) Se chegou aqui, é o modo antigo (formulário normal)
$ficha_id = isset($_POST['ficha_id']) ? (int) $_POST['ficha_id'] : 0;
if ($ficha_id <= 0) {
    // ID inválido: volta ao dashboard com erro
    header("Location: dashboard.php?status=invalid_id");
    exit;
}

// Ler campos do formulário (modo tradicional)
$nome = trim($_POST['nome'] ?? '');
$idade = isset($_POST['idade']) ? (int) $_POST['idade'] : null;
$classe = trim($_POST['classe'] ?? '');
$sexo = trim($_POST['sexo'] ?? '');
$dificuldade = trim($_POST['dificuldade'] ?? '');
$data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
$provincia = trim($_POST['provincia'] ?? '');
$pacote = trim($_POST['pacote'] ?? '');
$contacto = trim($_POST['contacto_encarregado'] ?? '');
$escola = trim($_POST['escola'] ?? '');
$internet_casa = isset($_POST['internet_casa']) ? 1 : 0;
$presencial = isset($_POST['regime_presencial']) ? 1 : 0;
$online = isset($_POST['regime_online']) ? 1 : 0;
$hibrido = isset($_POST['regime_hibrido']) ? 1 : 0;

// Confirmar que a ficha pertence ao utilizador (segurança)
$chk = $conn->prepare("SELECT id FROM fichas WHERE id = ? AND usuario_id = ? LIMIT 1");
$chk->bind_param("ii", $ficha_id, $usuario_id);
$chk->execute();
$res_chk = $chk->get_result();
if ($res_chk->num_rows === 0) {
    $chk->close();
    header("Location: dashboard.php?status=not_owner");
    exit;
}
$chk->close();

// --- 5) Preparar UPDATE (modo formulário)
$sql = "UPDATE fichas SET 
  nome = ?, idade = ?, classe = ?, sexo = ?, dificuldade = ?, data_nascimento = ?, 
  provincia = ?, pacote = ?, contacto = ?, escola = ?, internet_casa = ?, 
  regime_presencial = ?, regime_online = ?, regime_hibrido = ?
  WHERE id = ? AND usuario_id = ?";

$stmt = $conn->prepare($sql);
$bind_types = "sissssssssiiiiii";

$stmt->bind_param(
    $bind_types,
    $nome,
    $idade,
    $classe,
    $sexo,
    $dificuldade,
    $data_nascimento,
    $provincia,
    $pacote,
    $contacto,
    $escola,
    $internet_casa,
    $presencial,
    $online,
    $hibrido,
    $ficha_id,
    $usuario_id
);

// --- 6) Executar e responder
try {
    $stmt->execute();
} catch (Exception $e) {
    $stmt->close();
    $conn->close();
    echo "Erro ao executar UPDATE: " . htmlspecialchars($e->getMessage());
    exit;
}

$stmt->close();
$conn->close();

// Mesmo que não haja rows afetadas, consideramos sucesso
header("Location: dashboard.php?status=success");
exit;
