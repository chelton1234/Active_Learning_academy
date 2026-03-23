<?php
session_start();

// Sistema de idiomas (EXATAMENTE IGUAL ÀS OUTRAS PÁGINAS)
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

// Textos traduzidos para SOBRE NÓS
$textos = [
    'pt' => [
        'titulo' => 'Sobre Nós - Active Learning Academy',
        'nav_home' => 'Home',
        'nav_servicos' => 'Serviços',
        'nav_sobre' => 'Sobre Nós',
        'nav_login' => 'Login',
        'nav_registro' => 'Registro',
        'hero_titulo' => 'Sobre Nós',
        'hero_descricao' => 'Conheça a missão, visão e os profissionais por trás da nossa plataforma de reforço escolar.',
        'missao_titulo' => 'Missão',
        'missao_texto' => 'Proporcionar apoio académico de qualidade a alunos que seguem o currículo Cambridge em Moçambique, contribuindo para o seu sucesso escolar e desenvolvimento pessoal.',
        'visao_titulo' => 'Visão',
        'visao_texto' => 'Ser a principal referência nacional no apoio educacional para o currículo Cambridge, através de inovação, inclusão e excelência no ensino.',
        'grupo_titulo' => 'Grupo-Alvo',
        'grupo_texto' => 'Alunos do ensino primário, secundário e em preparação para exames internacionais como IGCSE, AS e A-Level.',
        'professores_titulo' => 'Quem São os Nossos Professores',
        'professores_texto1' => 'O nosso corpo docente é formado por jovens recém-graduados em Ensino de Inglês pela Universidade Pedagógica e outros formadores experientes da mesma área. Para as disciplinas de Ciências (Física, Química, Biologia e Matemática), selecionamos os melhores finalistas da Universidade Eduardo Mondlane. Todos os professores passam por um rigoroso processo de seleção que avalia conhecimentos técnicos, comunicação e compromisso com a educação.',
        'professores_texto2' => 'Esta plataforma é fruto de uma parceria académica entre faculdades públicas moçambicanas com o objetivo de fortalecer a base dos estudantes e ampliar o acesso a apoio pedagógico de qualidade, especialmente nas áreas mais exigentes do currículo Cambridge.',
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Selecionar idioma'
    ],
    'en' => [
        'titulo' => 'About Us - Active Learning Academy',
        'nav_home' => 'Home',
        'nav_servicos' => 'Services',
        'nav_sobre' => 'About Us',
        'nav_login' => 'Login',
        'nav_registro' => 'Register',
        'hero_titulo' => 'About Us',
        'hero_descricao' => 'Learn about our mission, vision and the professionals behind our tutoring platform.',
        'missao_titulo' => 'Mission',
        'missao_texto' => 'To provide quality academic support to students following the Cambridge curriculum in Mozambique, contributing to their academic success and personal development.',
        'visao_titulo' => 'Vision',
        'visao_texto' => 'To become the national reference in educational support for the Cambridge curriculum, through innovation, inclusion and teaching excellence.',
        'grupo_titulo' => 'Target Group',
        'grupo_texto' => 'Primary and secondary school students, as well as those preparing for international exams such as IGCSE, AS and A-Level.',
        'professores_titulo' => 'Our Teachers',
        'professores_texto1' => 'Our teaching staff consists of recent graduates in English Teaching from Pedagogical University and other experienced trainers in the same field. For Science subjects (Physics, Chemistry, Biology and Mathematics), we select the best final-year students from Eduardo Mondlane University. All teachers undergo a rigorous selection process that evaluates technical knowledge, communication skills and commitment to education.',
        'professores_texto2' => 'This platform is the result of an academic partnership between Mozambican public faculties with the goal of strengthening students\' foundation and expanding access to quality pedagogical support, especially in the most demanding areas of the Cambridge curriculum.',
        'direitos' => 'All rights reserved.',
        'desenvolvido' => 'Developed By Eng:Chelton Mucivane',
        'selecionar_idioma' => 'Select language'
    ]
];

$idioma_atual = $_SESSION['idioma'];
$t = $textos[$idioma_atual];
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_atual; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $t['titulo']; ?></title>
  <link rel="stylesheet" href="css/sobrenos.css">
  <script src="JavaScript/SobreNos.js" defer></script>
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
            <li><a href="home.html"><?php echo $t['nav_home']; ?></a></li>
            <li><a href="servicos.php"><?php echo $t['nav_servicos']; ?></a></li>
            <li><a href="sobreNos.php" class="active"><?php echo $t['nav_sobre']; ?></a></li>
            <li><a href="Login.php"><?php echo $t['nav_login']; ?></a></li>
            <li><a href="Sign-in.php" class="btn-register"><?php echo $t['nav_registro']; ?></a></li>
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
          <li><a href="servicos.html"><?php echo $t['nav_servicos']; ?></a></li>
          <li><a href="sobreNos.php" class="active"><?php echo $t['nav_sobre']; ?></a></li>
          <li><a href="Login.php"><?php echo $t['nav_login']; ?></a></li>
          <li><a href="Sign-in.php" class="btn-register"><?php echo $t['nav_registro']; ?></a></li>
        </ul>
      </nav>
    </div>
    
    <!-- OVERLAY -->
    <div class="menu-overlay" id="menuOverlay"></div>
  </header>

  <!-- HERO SECTION -->
  <section class="hero">
    <h1><?php echo $t['hero_titulo']; ?></h1>
    <p><?php echo $t['hero_descricao']; ?></p>
  </section>

  <!-- SOBRE NÓS SECTION -->
  <section class="sobre-nos">
    <div class="sobre-container">
      <h2><?php echo $t['missao_titulo']; ?></h2>
      <p><?php echo $t['missao_texto']; ?></p>

      <h2><?php echo $t['visao_titulo']; ?></h2>
      <p><?php echo $t['visao_texto']; ?></p>

      <h2><?php echo $t['grupo_titulo']; ?></h2>
      <p><?php echo $t['grupo_texto']; ?></p>

      <h2><?php echo $t['professores_titulo']; ?></h2>
      <p><?php echo $t['professores_texto1']; ?></p>

      <p><?php echo $t['professores_texto2']; ?></p>
    </div>
  </section>

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