<?php
// login_processar.php - COM VERIFICAÇÃO DIRETA NO BANCO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// ========== VERIFICAÇÃO 1: CONEXÃO COM BANCO ==========
if (!isset($conn)) {
    die("ERRO CRÍTICO: Variável \$conn não existe no config.php");
}

try {
    // Teste simples de conexão
    $conn->query("SELECT 1");
} catch(PDOException $e) {
    die("ERRO DE CONEXÃO: " . $e->getMessage());
}

// ========== VERIFICAÇÃO 2: BANCO DE DADOS ==========
try {
    $banco = $conn->query("SELECT DATABASE()")->fetchColumn();
    if ($banco !== 'sistema_login') {
        die("ERRO: Conectado ao banco '$banco', mas deveria ser 'sistema_login'");
    }
} catch(PDOException $e) {
    die("ERRO AO VERIFICAR BANCO: " . $e->getMessage());
}

// ========== VERIFICAÇÃO 3: TABELA usuarios ==========
try {
    $tabelas = $conn->query("SHOW TABLES LIKE 'usuarios'");
    if ($tabelas->rowCount() == 0) {
        die("ERRO: Tabela 'usuarios' NÃO EXISTE no banco 'sistema_login'");
    }
} catch(PDOException $e) {
    die("ERRO AO VERIFICAR TABELA: " . $e->getMessage());
}

// ========== VERIFICAÇÃO 4: ESTRUTURA DA TABELA ==========
try {
    $colunas = $conn->query("DESCRIBE usuarios");
    $colunas_existentes = [];
    while ($col = $colunas->fetch(PDO::FETCH_ASSOC)) {
        $colunas_existentes[] = $col['Field'];
    }
    
    $colunas_necessarias = ['id', 'nome', 'email', 'senha', 'tipo'];
    $faltando = array_diff($colunas_necessarias, $colunas_existentes);
    
    if (!empty($faltando)) {
        die("ERRO: Colunas faltando na tabela usuarios: " . implode(', ', $faltando));
    }
} catch(PDOException $e) {
    die("ERRO AO VERIFICAR COLUNAS: " . $e->getMessage());
}

// ========== VERIFICAÇÃO 5: USUÁRIOS EXISTENTES ==========
try {
    $total_usuarios = $conn->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    // Comentado para não poluir, mas disponível se necessário
    // if ($total_usuarios == 0) {
    //     die("ERRO: Nenhum usuário cadastrado na tabela 'usuarios'");
    // }
} catch(PDOException $e) {
    die("ERRO AO CONTAR USUÁRIOS: " . $e->getMessage());
}

// ========== CONTINUAÇÃO DO LOGIN NORMAL ==========

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

// Receber e validar dados
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$senha = $_POST['senha'] ?? '';

// Verificar se email e senha foram fornecidos
if (!$email || !$senha) {
    $_SESSION['erro_login'] = "E-mail e senha são obrigatórios.";
    $_SESSION['email_temp'] = $_POST['email'] ?? '';
    header('Location: login.php');
    exit();
}

try {
    // ========== VERIFICAÇÃO 6: BUSCAR USUÁRIO ESPECÍFICO ==========
    $stmt = $conn->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Verificar se existem usuários com email parecido (debug)
        $stmt_parecidos = $conn->prepare("SELECT email FROM usuarios WHERE email LIKE ? LIMIT 5");
        $stmt_parecidos->execute(['%' . $email . '%']);
        $parecidos = $stmt_parecidos->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($parecidos)) {
            error_log("Email não encontrado: $email. Parecidos: " . implode(', ', $parecidos));
        }
        
        $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
        $_SESSION['email_temp'] = $email;
        header('Location: login.php');
        exit();
    }

    // ========== VERIFICAÇÃO 7: TIPO DO USUÁRIO ==========
    $tipos_validos = ['admin', 'professor', 'docente', 'aluno'];
    if (!in_array(strtolower($usuario['tipo']), $tipos_validos)) {
        error_log("Tipo de usuário inválido: " . $usuario['tipo'] . " para email: " . $email);
        // Não mostrar erro ao usuário, apenas log
    }

    // ========== VERIFICAÇÃO 8: VERIFICAR SENHA ==========
    if (!password_verify($senha, $usuario['senha'])) {
        $_SESSION['erro_login'] = "E-mail ou senha incorretos.";
        $_SESSION['email_temp'] = $email;
        header('Location: login.php');
        exit();
    }

    // ========== LOGIN BEM-SUCEDIDO ==========
    
    // Login bem-sucedido - criar sessão
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['logado'] = true;
    
    // Flags específicas para cada tipo de usuário
    $tipo_lower = strtolower($usuario['tipo']);
    $_SESSION['admin'] = ($tipo_lower === 'admin');
    $_SESSION['professor'] = ($tipo_lower === 'professor' || $tipo_lower === 'docente');
    $_SESSION['aluno'] = ($tipo_lower === 'aluno');
    
    // ========== VERIFICAÇÃO 9: REDIRECIONAMENTO ==========
    switch($tipo_lower) {
        case 'admin':
            $destino = 'dashboard_admin.php';
            break;
        case 'professor':
        case 'docente':
            $destino = 'dashboard_professor.php';
            break;
        case 'aluno':
        default:
            $destino = 'dashboard.php';
            break;
    }
    
    // Verificar se o arquivo de destino existe
    if (!file_exists($destino)) {
        error_log("Arquivo de destino não existe: $destino");
        $_SESSION['erro_login'] = "Erro de configuração: dashboard não encontrado.";
        header('Location: login.php');
        exit();
    }
    
    header('Location: ' . $destino);
    exit();
    
} catch(PDOException $e) {
    // ========== VERIFICAÇÃO 10: ERRO PDO DETALHADO ==========
    $erro_msg = "ERRO PDO: " . $e->getMessage() . "\n";
    $erro_msg .= "Código: " . $e->getCode() . "\n";
    $erro_msg .= "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    
    error_log($erro_msg);
    
    // Mostrar erro apenas em desenvolvimento
    if (ini_get('display_errors')) {
        echo "<div style='background:#f8d7da; color:#721c24; padding:20px; margin:20px; border:1px solid #f5c6cb; border-radius:5px;'>";
        echo "<h3>❌ ERRO NO BANCO DE DADOS</h3>";
        echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
        echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
        echo "</div>";
        exit();
    } else {
        $_SESSION['erro_login'] = "Erro no sistema. Tente novamente mais tarde.";
        $_SESSION['email_temp'] = $email ?? '';
        header('Location: login.php');
        exit();
    }
}
?>