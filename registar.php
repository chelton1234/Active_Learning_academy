<?php
// registar.php
session_start();
require_once 'config.php';

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Sign-in.php');
    exit;
}

// Obter dados do formulário
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';
$termos = isset($_POST['termos']);

// Sistema de idiomas (mesmo do Sign-in.php)
$idioma = $_SESSION['idioma'] ?? 'pt';
$textos = [
    'pt' => [
        'email_obrigatorio' => 'O email é obrigatório.',
        'email_invalido' => 'Por favor, insira um email válido.',
        'email_existente' => 'Este email já está registado. Por favor, faça login.',
        'senha_curta' => 'A senha deve ter no mínimo 6 caracteres.',
        'senha_nao_coincide' => 'As senhas não coincidem.',
        'termos_obrigatorio' => 'Você deve aceitar os termos e condições.',
        'erro_registo' => 'Erro ao criar conta. Tente novamente.',
        'sucesso_registo' => 'Conta criada com sucesso! Faça login para continuar.'
    ],
    'en' => [
        'email_obrigatorio' => 'Email is required.',
        'email_invalido' => 'Please enter a valid email address.',
        'email_existente' => 'This email is already registered. Please log in.',
        'senha_curta' => 'Password must be at least 6 characters.',
        'senha_nao_coincide' => 'Passwords do not match.',
        'termos_obrigatorio' => 'You must accept the terms and conditions.',
        'erro_registo' => 'Error creating account. Please try again.',
        'sucesso_registo' => 'Account created successfully! Please log in.'
    ]
];
$t = $textos[$idioma];

// ========== VALIDAÇÕES ==========

// 1. Validar email
if (empty($email)) {
    $_SESSION['erro'] = $t['email_obrigatorio'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = $t['email_invalido'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

// 2. Verificar se email já existe
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['erro'] = $t['email_existente'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

// 3. Validar senha
if (strlen($senha) < 6) {
    $_SESSION['erro'] = $t['senha_curta'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

// 4. Validar confirmação de senha
if ($senha !== $confirmar_senha) {
    $_SESSION['erro'] = $t['senha_nao_coincide'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

// 5. Validar termos
if (!$termos) {
    $_SESSION['erro'] = $t['termos_obrigatorio'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}

// ========== CRIAR CONTA ==========

// Extrair nome do email (parte antes do @)
$nome_padrao = explode('@', $email)[0];
$nome_padrao = ucfirst(str_replace(['.', '_', '-'], ' ', $nome_padrao));

// Hash da senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, data_criacao) VALUES (?, ?, ?, 'aluno', NOW())");
    $resultado = $stmt->execute([$nome_padrao, $email, $senha_hash]);
    
    if ($resultado) {
        $_SESSION['sucesso'] = $t['sucesso_registo'];
        header('Location: Login.php?registro=sucesso');
        exit;
    } else {
        throw new Exception("Erro ao inserir no banco");
    }
} catch (Exception $e) {
    error_log("Erro no registo: " . $e->getMessage());
    $_SESSION['erro'] = $t['erro_registo'];
    $_SESSION['email_temp'] = $email;
    header('Location: Sign-in.php');
    exit;
}
?>