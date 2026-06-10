<?php
// redefinir_senha.php - Redefinição de senha via token (com validação robusta)
session_start();

// Sistema de idiomas (igual ao login)
if (!isset($_SESSION['idioma'])) $_SESSION['idioma'] = 'pt';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    $_SESSION['idioma'] = $_GET['lang'];
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit();
}
$textos = [
    'pt' => [
        'titulo' => 'Redefinir Senha',
        'nova_senha' => 'Nova Senha:',
        'confirmar' => 'Confirmar Nova Senha:',
        'redefinir' => 'Redefinir Senha',
        'voltar' => 'Voltar ao login',
        'minimo' => 'A senha deve ter pelo menos 6 caracteres.',
        'nao_coincide' => '❌ As senhas não coincidem. Digite a mesma senha.',
        'sucesso' => '✅ Senha alterada com sucesso! Redirecionando...',
        'erro' => 'Erro ao atualizar a senha. Tente novamente.',
        'token_invalido' => 'Link inválido ou expirado. Solicite uma nova recuperação.',
        'solicitar_novo' => 'Solicitar novo link',
        'servicos' => 'Serviços',
        'sobre_nos' => 'Sobre Nós',
        'login' => 'Login',
        'registro' => 'Registro',
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane'
    ],
    'en' => [
        'titulo' => 'Reset Password',
        'nova_senha' => 'New Password:',
        'confirmar' => 'Confirm New Password:',
        'redefinir' => 'Reset Password',
        'voltar' => 'Back to login',
        'minimo' => 'Password must be at least 6 characters.',
        'nao_coincide' => '❌ Passwords do not match.',
        'sucesso' => '✅ Password changed successfully! Redirecting...',
        'erro' => 'Error updating password. Try again.',
        'token_invalido' => 'Invalid or expired link. Request a new recovery.',
        'solicitar_novo' => 'Request new link',
        'servicos' => 'Services',
        'sobre_nos' => 'About Us',
        'login' => 'Login',
        'registro' => 'Register',
        'direitos' => 'All rights reserved.',
        'desenvolvido' => 'Developed By Eng:Chelton Mucivane'
    ]
];
$idioma = $_SESSION['idioma'];
$t = $textos[$idioma];

$mensagem = "";
$token_valido = false;
$email = "";

// Se não houver token, redirecciona para esqueceu_senha.php
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: esqueceu_senha.php");
    exit;
}

$token = $_GET['token'];
$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) die("Erro de conexão.");

$stmt = $conn->prepare("SELECT email, expira_em, usado FROM password_resets WHERE token = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();
$stmt->close();

if (!$reset || $reset['usado'] == 1 || strtotime($reset['expira_em']) < time()) {
    $mensagem = $t['token_invalido'];
} else {
    $token_valido = true;
    $email = $reset['email'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valido) {
    $nova = trim($_POST["nova_senha"]);
    $confirma = trim($_POST["confirmar_senha"]);
    
    if (strlen($nova) < 6) {
        $mensagem = $t['minimo'];
    } elseif ($nova !== $confirma) {
        $mensagem = $t['nao_coincide'];
    } else {
        $hash = password_hash($nova, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->bind_param("ss", $hash, $email);
        if ($stmt->execute()) {
            $conn->query("UPDATE password_resets SET usado = 1 WHERE token = '$token'");
            $mensagem = $t['sucesso'];
            echo "<meta http-equiv='refresh' content='2;url=login.php'>";
        } else {
            $mensagem = $t['erro'];
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="<?= $idioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['titulo'] ?> - Active Learning Academy</title>
    <link rel="stylesheet" href="css/login.css">
    <script src="JavaScript/login.js" defer></script>
</head>
<body>
    <!-- HEADER IDÊNTICO AO DO LOGIN (copie o mesmo bloco do login.php) -->
    <header class="main-header">
        <div class="container header-container">
            <div class="logo-container"><a href="home.html"><img src="img/logo.jpeg" alt="Active Learning Academy Logo" class="site-logo" /></a></div>
            <div class="header-right">
                <div class="language-wrapper">
                    <div class="language-selector">
                        <button class="language-btn">
                            <?php if ($idioma == 'pt'): ?>
                                <span class="language-flag">🇵🇹</span><span class="language-text">PT</span>
                            <?php else: ?>
                                <span class="language-flag">🇬🇧</span><span class="language-text">EN</span>
                            <?php endif; ?>
                            <span class="language-arrow">▼</span>
                        </button>
                        <div class="language-dropdown">
                            <a href="?lang=pt" class="language-option <?= $idioma == 'pt' ? 'active' : '' ?>"><span class="language-flag">🇵🇹</span><span>Português</span></a>
                            <a href="?lang=en" class="language-option <?= $idioma == 'en' ? 'active' : '' ?>"><span class="language-flag">🇬🇧</span><span>English</span></a>
                        </div>
                    </div>
                </div>
                <nav class="desktop-nav">
                    <ul class="nav-links">
                        <li><a href="servicos.php"><?= $t['servicos'] ?></a></li>
                        <li><a href="sobreNos.html"><?= $t['sobre_nos'] ?></a></li>
                        <li><a href="Login.php" class="active"><?= $t['login'] ?></a></li>
                        <li><a href="Sign-in.php" class="btn-register"><?= $t['registro'] ?></a></li>
                    </ul>
                </nav>
                <button class="hamburger mobile-only" id="hamburger"><img src="img/menu.svg" alt="Menu" class="menu-icon"></button>
            </div>
        </div>
        <div class="mobile-menu" id="mobileMenu"><div class="menu-header"><div class="menu-logo"><img src="img/logo.jpeg" alt="Active Learning Academy"></div><button class="close-menu" id="closeMenu"><img src="img/close.svg" alt="Fechar"></button></div><nav class="mobile-nav"><ul><li><a href="servicos.php"><?= $t['servicos'] ?></a></li><li><a href="sobreNos.php"><?= $t['sobre_nos'] ?></a></li><li><a href="Login.php" class="active"><?= $t['login'] ?></a></li><li><a href="Sign-in.php" class="btn-register"><?= $t['registro'] ?></a></li></ul></nav></div>
        <div class="menu-overlay" id="menuOverlay"></div>
    </header>

    <div class="content-wrapper">
        <div class="login-container">
            <h1><?= $t['titulo'] ?></h1>
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= strpos($mensagem, '✅') !== false ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($mensagem) ?>
                    <?php if ($mensagem == $t['token_invalido']): ?>
                        <div style="margin-top:10px;"><a href="esqueceu_senha.php"><?= $t['solicitar_novo'] ?></a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($token_valido && strpos($mensagem, 'sucesso') === false): ?>
                <p>Redefina a senha para o e‑mail: <strong><?= htmlspecialchars($email) ?></strong></p>
                <form method="post">
                    <div class="form-group">
                        <label for="nova_senha"><?= $t['nova_senha'] ?></label>
                        <input type="password" id="nova_senha" name="nova_senha" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha"><?= $t['confirmar'] ?></label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                    <button type="submit" class="btn-login"><?= $t['redefinir'] ?></button>
                </form>
            <?php endif; ?>
            <div class="form-links"><a href="login.php"><?= $t['voltar'] ?></a></div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2025 Active Learning Academy - <?= $t['direitos'] ?><br><?= $t['desenvolvido'] ?></p>
        </div>
    </footer>
    <script src="JavaScript/login.js"></script>
</body>
</html>