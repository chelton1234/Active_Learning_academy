<?php
session_start();

// Sistema de idiomas
if (!isset($_SESSION['idioma'])) {
    $_SESSION['idioma'] = 'pt'; // Português por padrão
}

// Trocar idioma se solicitado
if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'])) {
    $_SESSION['idioma'] = $_GET['lang'];
    // Recarrega a página sem o parâmetro GET
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $url");
    exit();
}

// Textos traduzidos
$textos = [
    'pt' => [
        'titulo' => 'Entrar na Plataforma',
        'email' => 'Email:',
        'senha' => 'Senha:',
        'entrar' => 'Entrar',
        'esqueceu_senha' => 'Esqueceu a senha?',
        'criar_conta' => 'Criar conta',
        'placeholder_email' => 'seu@email.com',
        'placeholder_senha' => 'Digite sua senha',
        'servicos' => 'Serviços',
        'sobre_nos' => 'Sobre Nós',
        'login' => 'Login',
        'registro' => 'Registro',
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Selecionar idioma'
    ],
    'en' => [
        'titulo' => 'Login to Platform',
        'email' => 'Email:',
        'senha' => 'Password:',
        'entrar' => 'Login',
        'esqueceu_senha' => 'Forgot password?',
        'criar_conta' => 'Create account',
        'placeholder_email' => 'your@email.com',
        'placeholder_senha' => 'Enter your password',
        'servicos' => 'Services',
        'sobre_nos' => 'About Us',
        'login' => 'Login',
        'registro' => 'Register',
        'direitos' => 'All rights reserved.',
        'desenvolvido' => 'Developed By Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Select language'
    ]
];

$idioma_atual = $_SESSION['idioma'];
$t = $textos[$idioma_atual];

// Mensagens de erro do login
$erro_login = $_SESSION['erro_login'] ?? '';
$email_valor = $_SESSION['email_temp'] ?? '';
unset($_SESSION['erro_login'], $_SESSION['email_temp']);
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_atual; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $idioma_atual == 'pt' ? 'Login - Active Learning Academy' : 'Login - Active Learning Academy'; ?></title>
    <link rel="stylesheet" href="css/login.css">
    <script src="JavaScript/login.js" defer></script>
</head>
<body>

    <!-- HEADER COM IDIOMA SEMPRE VISÍVEL -->
    <header class="main-header">
        <div class="container header-container">
            <!-- 1. LOGO DA EMPRESA -->
            <div class="logo-container">
                <a href="home.html">
                    <img src="img/logo.jpeg" alt="Active Learning Academy Logo" class="site-logo" />
                </a>
            </div>
            
            <!-- CONTEÚDO DO LADO DIREITO -->
            <div class="header-right">
                <!-- 2. SELECTOR DE IDIOMA (SEMPRE VISÍVEL) -->
                <div class="language-wrapper" id="languageWrapper">
                    <div class="language-selector" id="languageSelector">
                        <button class="language-btn" aria-label="<?php echo $t['selecionar_idioma']; ?>">
                            <?php if ($idioma_atual == 'pt'): ?>
                                <span class="language-flag">🇵🇹</span>
                                <span class="language-text">PT</span>
                            <?php else: ?>
                                <span class="language-flag">🇬🇧</span>
                                <span class="language-text">EN</span>
                            <?php endif; ?>
                            <span class="language-arrow">▼</span>
                        </button>
                        <div class="language-dropdown">
                            <a href="?lang=pt" class="language-option <?php echo $idioma_atual == 'pt' ? 'active' : ''; ?>">
                                <span class="language-flag">🇵🇹</span>
                                <span>Português</span>
                            </a>
                            <a href="?lang=en" class="language-option <?php echo $idioma_atual == 'en' ? 'active' : ''; ?>">
                                <span class="language-flag">🇬🇧</span>
                                <span>English</span>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- MENU DESKTOP (APENAS DESKTOP) -->
                <nav class="desktop-nav">
                    <ul class="nav-links">
                        <li><a href="servicos.html"><?php echo $t['servicos']; ?></a></li>
                        <li><a href="sobreNos.html"><?php echo $t['sobre_nos']; ?></a></li>
                        <li><a href="Login.php" class="active"><?php echo $t['login']; ?></a></li>
                        <li><a href="Sign-in.php" class="btn-register"><?php echo $t['registro']; ?></a></li>
                    </ul>
                </nav>
                
                <!-- 3. MENU HAMBÚRGUER (APENAS MOBILE) -->
                <button class="hamburger mobile-only" id="hamburger" aria-label="Abrir menu">
                    <img src="img/menu.svg" alt="Menu" class="menu-icon">
                </button>
            </div>
        </div>
        
        <!-- MENU MOBILE LATERAL -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="menu-header">
                <div class="menu-logo">
                    <img src="img/logo.jpeg" alt="Active Learning Academy">
                </div>
                <button class="close-menu" id="closeMenu" aria-label="Fechar menu">
                    <img src="img/close.svg" alt="Fechar">
                </button>
            </div>
            
            <nav class="mobile-nav">
                <ul>
                    <li><a href="servicos.php"><?php echo $t['servicos']; ?></a></li>
                    <li><a href="sobreNos.php"><?php echo $t['sobre_nos']; ?></a></li>
                    <li><a href="Login.php" class="active"><?php echo $t['login']; ?></a></li>
                    <li><a href="Sign-in.php" class="btn-register"><?php echo $t['registro']; ?></a></li>
                </ul>
            </nav>
        </div>
        
        <!-- OVERLAY -->
        <div class="menu-overlay" id="menuOverlay"></div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="content-wrapper">
        <div class="login-container">
            <h1><?php echo $t['titulo']; ?></h1>
            
            <?php if ($erro_login): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <?php echo $erro_login; ?>
            </div>
            <?php endif; ?>
            
            <form class="login-form" action="login_processar.php" method="post">
                <div class="form-group">
                    <label for="email"><?php echo $t['email']; ?></label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email_valor); ?>"
                           placeholder="<?php echo $t['placeholder_email']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="senha"><?php echo $t['senha']; ?></label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="<?php echo $t['placeholder_senha']; ?>">
                </div>
                
                <button type="submit" class="btn-login"><?php echo $t['entrar']; ?></button>
                
                <div class="form-links">
                    <a href="redefinir_senha.php"><?php echo $t['esqueceu_senha']; ?></a>
                    <span class="separator">|</span>
                    <a href="Sign-in.php"><?php echo $t['criar_conta']; ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <p>&copy; 2025 Active Learning Academy - <?php echo $t['direitos']; ?>
                <br><?php echo $t['desenvolvido']; ?>
            </p>
        </div>
    </footer>

</body>
</html>