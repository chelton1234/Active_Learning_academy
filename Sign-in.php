<?php
session_start();

// Sistema de idiomas (EXATAMENTE IGUAL AO LOGIN)
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

// Textos traduzidos para REGISTRO
$textos = [
    'pt' => [
        'titulo' => 'Criar Nova Conta',
        'email' => 'Email:',
        'senha' => 'Senha:',
        'confirmar_senha' => 'Confirmar Senha:',
        'termos' => 'Concordo com os',
        'termos_link' => 'Termos',
        'privacidade_link' => 'Política de Privacidade',
        'criar_conta' => 'Criar Conta',
        'ja_tem_conta' => 'Já tem uma conta?',
        'fazer_login' => 'Faça login aqui',
        'placeholder_email' => 'seu@email.com',
        'placeholder_senha' => 'Mínimo 6 caracteres',
        'email_ajuda' => 'Usaremos este email para contacto',
        'servicos' => 'Serviços',
        'sobre_nos' => 'Sobre Nós',
        'login' => 'Login',
        'registro' => 'Registro',
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Selecionar idioma',
        'nav_home' => 'Home'
    ],
    'en' => [
        'titulo' => 'Create New Account',
        'email' => 'Email:',
        'senha' => 'Password:',
        'confirmar_senha' => 'Confirm Password:',
        'termos' => 'I agree with the',
        'termos_link' => 'Terms',
        'privacidade_link' => 'Privacy Policy',
        'criar_conta' => 'Create Account',
        'ja_tem_conta' => 'Already have an account?',
        'fazer_login' => 'Log in here',
        'placeholder_email' => 'your@email.com',
        'placeholder_senha' => 'Minimum 6 characters',
        'email_ajuda' => 'We will use this email for contact',
        'servicos' => 'Services',
        'sobre_nos' => 'About Us',
        'login' => 'Login',
        'registro' => 'Register',
        'direitos' => 'All rights reserved.',
        'desenvolvido' => 'Developed By Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Select language',
        'nav_home' => 'Home'
    ]
];

$idioma_atual = $_SESSION['idioma'];
$t = $textos[$idioma_atual];

// Mensagens de erro/sucesso do registro
$erro = $_SESSION['erro'] ?? '';
$sucesso = $_SESSION['sucesso'] ?? '';
$email_valor = $_SESSION['email_temp'] ?? '';

unset($_SESSION['erro'], $_SESSION['sucesso'], $_SESSION['email_temp']);
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_atual; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $idioma_atual == 'pt' ? 'Registro - Active Learning Academy' : 'Register - Active Learning Academy'; ?></title>
    <link rel="stylesheet" href="css/registo.css">
    <script src="JavaScript/registo.js" defer></script>
</head>
<body>

    <!-- HEADER COM IDIOMA SEMPRE VISÍVEL (IGUAL AO LOGIN) -->
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
                        <li><a href="home.html"><?php echo $t['nav_home']; ?></a></li>
                        <li><a href="servicos.php"><?php echo $t['servicos']; ?></a></li>
                        <li><a href="sobreNos.php"><?php echo $t['sobre_nos']; ?></a></li>
                        <li><a href="Login.php"><?php echo $t['login']; ?></a></li>
                        <li><a href="Sign-in.php" class="btn-register active"><?php echo $t['registro']; ?></a></li>
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
                    <li><a href="home.html"><?php echo $t['nav_home']; ?></a></li>
                    <li><a href="servicos.html"><?php echo $t['servicos']; ?></a></li>
                    <li><a href="sobreNos.html"><?php echo $t['sobre_nos']; ?></a></li>
                    <li><a href="Login.php"><?php echo $t['login']; ?></a></li>
                    <li><a href="Sign-in.php" class="btn-register active"><?php echo $t['registro']; ?></a></li>
                </ul>
            </nav>
        </div>
        
        <!-- OVERLAY -->
        <div class="menu-overlay" id="menuOverlay"></div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="content-wrapper">
        <div class="registro-container">
            <h1><?php echo $t['titulo']; ?></h1>
            
            <?php if ($erro): ?>
            <div class="alert alert-danger">
                <span class="alert-icon">⚠️</span>
                <?php echo $erro; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <span class="alert-icon">✅</span>
                <?php echo $sucesso; ?>
            </div>
            <?php endif; ?>
            
            <form class="registro-form" action="registar.php" method="post">
                <div class="form-group">
                    <label for="email"><?php echo $t['email']; ?></label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email_valor); ?>"
                           placeholder="<?php echo $t['placeholder_email']; ?>">
                    <small class="form-help"><?php echo $t['email_ajuda']; ?></small>
                </div>
                
                <div class="form-group">
                    <label for="senha"><?php echo $t['senha']; ?></label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="<?php echo $t['placeholder_senha']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha"><?php echo $t['confirmar_senha']; ?></label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required 
                           placeholder="<?php echo $t['placeholder_senha']; ?>">
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="termos" name="termos" required>
                    <label for="termos">
                        <?php echo $t['termos']; ?>
                        <a href="#"><?php echo $t['termos_link']; ?></a> e 
                        <a href="#"><?php echo $t['privacidade_link']; ?></a>
                    </label>
                </div>
                
                <button type="submit" class="btn-registro"><?php echo $t['criar_conta']; ?></button>
                
                <div class="form-links">
                    <span><?php echo $t['ja_tem_conta']; ?></span>
                    <a href="Login.php"><?php echo $t['fazer_login']; ?></a>
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