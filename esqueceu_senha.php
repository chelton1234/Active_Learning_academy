<?php
// esqueceu_senha.php - Recuperação de senha
session_start();

// Sistema de idiomas (igual ao login)
if (!isset($_SESSION['idioma'])) {
    $_SESSION['idioma'] = 'pt';
}
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    $_SESSION['idioma'] = $_GET['lang'];
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit();
}

$textos = [
    'pt' => [
        'titulo' => 'Recuperar Senha',
        'email' => 'E-mail:',
        'enviar' => 'Enviar Link',
        'voltar' => 'Voltar ao login',
        'mensagem_enviado' => 'Link de recuperação enviado para o e-mail cadastrado. Verifique a caixa de entrada (e o spam).',
        'mensagem_erro' => 'Este e-mail não está registado no sistema.',
        'mensagem_falha' => 'Erro ao enviar e-mail. Tente novamente.',
        'placeholder_email' => 'seu@email.com',
        'selecionar_idioma' => 'Selecionar idioma',
        'servicos' => 'Serviços',
        'sobre_nos' => 'Sobre Nós',
        'login' => 'Login',
        'registro' => 'Registro',
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane'
    ],
    'en' => [
        'titulo' => 'Reset Password',
        'email' => 'Email:',
        'enviar' => 'Send Link',
        'voltar' => 'Back to login',
        'mensagem_enviado' => 'Recovery link sent to your email. Check your inbox (and spam).',
        'mensagem_erro' => 'This email is not registered.',
        'mensagem_falha' => 'Error sending email. Try again.',
        'placeholder_email' => 'your@email.com',
        'selecionar_idioma' => 'Select language',
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

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/env.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensagem = "";
$mensagem_tipo = "";
$link_exibido = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    if (empty($email)) {
        $mensagem = "Digite o seu e-mail.";
        $mensagem_tipo = "erro";
    } else {
        $conn = new mysqli("localhost", "root", "", "sistema_login");
        if ($conn->connect_error) {
            $mensagem = "Erro de conexão.";
            $mensagem_tipo = "erro";
        } else {
            $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $nome = $user['nome'];
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email VARCHAR(100) NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expira_em DATETIME NOT NULL,
                    usado TINYINT(1) DEFAULT 0,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $conn->query("DELETE FROM password_resets WHERE email = '$email'");
                
                $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expira_em) VALUES (?, ?, ?)");
                $stmt2->bind_param("sss", $email, $token, $expira);
                if ($stmt2->execute()) {
                    $link = "http://" . $_SERVER['HTTP_HOST'] . "/Active_Learning_Academy/redefinir_senha.php?token=" . urlencode($token);
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USER;
                        $mail->Password   = SMTP_PASS;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = SMTP_PORT;
                        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                        $mail->addAddress($email, $nome);
                        $mail->isHTML(true);
                        $mail->Subject = "Redefinição de senha - Active Learning Academy";
                        $mail->Body    = "<h2>Olá $nome,</h2><p>Clique no link para redefinir a sua senha (válido por 1 hora):</p><p><a href='$link'>$link</a></p><p>Se não foi você, ignore este e-mail.</p>";
                        $mail->AltBody = "Olá $nome,\n\nRedefina a sua senha acessando: $link\n\nSe não foi você, ignore.";
                        $mail->send();
                        $mensagem = $t['mensagem_enviado'];
                        $mensagem_tipo = "sucesso";
                    } catch (Exception $e) {
                        $mensagem = $t['mensagem_falha'] . " " . $mail->ErrorInfo;
                        $mensagem_tipo = "erro";
                        $link_exibido = $link;
                    }
                } else {
                    $mensagem = $t['mensagem_falha'];
                    $mensagem_tipo = "erro";
                }
                $stmt2->close();
            } else {
                $mensagem = $t['mensagem_erro'];
                $mensagem_tipo = "erro";
            }
            $stmt->close();
            $conn->close();
        }
    }
}
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
    <?php include 'includes/header_login.php'; ?> <!-- Se preferir, pode copiar o header do login directamente aqui -->
    
    <!-- CÓDIGO DO HEADER (IGUAL AO LOGIN) -->
    <header class="main-header">
        <div class="container header-container">
            <div class="logo-container">
                <a href="home.html">
                    <img src="img/logo.jpeg" alt="Active Learning Academy Logo" class="site-logo" />
                </a>
            </div>
            <div class="header-right">
                <div class="language-wrapper" id="languageWrapper">
                    <div class="language-selector" id="languageSelector">
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
        <div class="mobile-menu" id="mobileMenu">
            <div class="menu-header"><div class="menu-logo"><img src="img/logo.jpeg" alt="Active Learning Academy"></div><button class="close-menu" id="closeMenu"><img src="img/close.svg" alt="Fechar"></button></div>
            <nav class="mobile-nav"><ul><li><a href="servicos.php"><?= $t['servicos'] ?></a></li><li><a href="sobreNos.php"><?= $t['sobre_nos'] ?></a></li><li><a href="Login.php" class="active"><?= $t['login'] ?></a></li><li><a href="Sign-in.php" class="btn-register"><?= $t['registro'] ?></a></li></ul></nav>
        </div>
        <div class="menu-overlay" id="menuOverlay"></div>
    </header>

    <div class="content-wrapper">
        <div class="login-container">
            <h1><?= $t['titulo'] ?></h1>
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $mensagem_tipo == 'sucesso' ? 'success' : 'danger' ?>">
                    <?= htmlspecialchars($mensagem) ?>
                    <?php if ($link_exibido): ?>
                        <div class="link-fallback" style="margin-top:10px; word-break:break-all;">
                            <strong>Link alternativo:</strong> <a href="<?= $link_exibido ?>"><?= $link_exibido ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="esqueceu_senha.php">
                <div class="form-group">
                    <label for="email"><?= $t['email'] ?></label>
                    <input type="email" id="email" name="email" required placeholder="<?= $t['placeholder_email'] ?>">
                </div>
                <button type="submit" class="btn-login"><?= $t['enviar'] ?></button>
                <div class="form-links">
                    <a href="login.php"><?= $t['voltar'] ?></a>
                </div>
            </form>
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