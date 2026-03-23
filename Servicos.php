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
        // Header
        'home' => 'Home',
        'servicos' => 'Serviços',
        'sobre_nos' => 'Sobre Nós',
        'login' => 'Login',
        'registro' => 'Registro',
        'selecionar_idioma' => 'Selecionar idioma',
        
        // Carrossel
        'titulo_servicos' => 'Serviços Disponíveis',
        
        // Níveis de ensino
        'primary_title' => 'Primary (Year 1–6)',
        'primary_item1' => 'Reforço em Língua Inglesa',
        'primary_item2' => 'Matemática básica e raciocínio lógico',
        'primary_item3' => 'Ciências naturais introdutórias',
        'primary_item4' => 'Leitura e escrita guiada',
        'primary_item5' => 'Preparação para Checkpoint Primário',
        
        'lower_title' => 'Lower Secondary (Year 7–9)',
        'lower_item1' => 'Inglês Académico e Expressão Escrita',
        'lower_item2' => 'Matemática Intermédia',
        'lower_item3' => 'Ciências: Física, Química e Biologia introdutórias',
        'lower_item4' => 'Preparação para Cambridge Lower Secondary Checkpoint',
        'lower_item5' => 'Aulas de suporte em formato online',
        
        'upper_title' => 'Upper Secondary (IGCSE – Year 10–11)',
        'upper_item1' => 'Revisão intensiva por disciplina',
        'upper_item2' => 'Simulados de exames IGCSE',
        'upper_item3' => 'Sessões de dúvidas e explicações temáticas',
        'upper_item4' => 'Orientação sobre estratégias de estudo',
        'upper_item5' => 'Aulas personalizadas com professores certificados',
        
        // Dificuldades
        'dificuldades_text' => 'Está com dificuldades? <br>Deixe-nos entender os seus desafios e encontraremos o professor ideal para si!',
        'dificuldades_btn' => 'Encontrar Explicador',
        
        // Regimes
        'regimes_titulo' => 'Regimes de Aulas',
        'regime_online' => 'Aulas Online',
        'regime_online_desc' => 'Aulas por videoconferência conforme disponibilidade.',
        'regime_domicilio' => 'Aulas ao Domicílio',
        'regime_domicilio_desc' => 'Um professor desloca-se até à casa do aluno.',
        'regime_estabelecimento' => 'Aulas no Estabelecimento',
        'regime_estabelecimento_desc' => 'Aulas no nosso centro de reforço escolar.',
        
        // Pacotes
        'pacotes_titulo' => 'Pacotes de Reforço Escolar',
        'pacote_basico' => 'Pacote Básico',
        'pacote_basico_item1' => '2 aulas por semana (1h cada)',
        'pacote_basico_item2' => 'Acesso a materiais digitais',
        'pacote_basico_item3' => 'Relatório mensal de desempenho',
        'pacote_basico_preco' => 'Preço: 1.500 MT/mês',
        
        'pacote_intermedio' => 'Pacote Intermédio',
        'pacote_intermedio_item1' => '3 aulas por semana (1h30 cada)',
        'pacote_intermedio_item2' => 'Suporte individualizado',
        'pacote_intermedio_item3' => 'Simulados mensais',
        'pacote_intermedio_preco' => 'Preço: 2.500 MT/mês',
        
        'pacote_premium' => 'Pacote Premium',
        'pacote_premium_item1' => '5 aulas por semana (2h cada)',
        'pacote_premium_item2' => 'Mentoria contínua',
        'pacote_premium_item3' => 'Relatórios quinzenais e acompanhamento dos pais',
        'pacote_premium_preco' => 'Preço: 4.000 MT/mês',
        
        // Footer
        'direitos' => 'Todos os direitos reservados.',
        'desenvolvido' => 'Desenvolvido Por Eng:Chelton Mucivane'
    ],
    
    'en' => [
        // Header
        'home' => 'Home',
        'servicos' => 'Services',
        'sobre_nos' => 'About Us',
        'login' => 'Login',
        'registro' => 'Register',
        'selecionar_idioma' => 'Select language',
        
        // Carrossel
        'titulo_servicos' => 'Available Services',
        
        // Níveis de ensino
        'primary_title' => 'Primary (Year 1–6)',
        'primary_item1' => 'English Language Reinforcement',
        'primary_item2' => 'Basic Mathematics and Logical Reasoning',
        'primary_item3' => 'Introductory Natural Sciences',
        'primary_item4' => 'Guided Reading and Writing',
        'primary_item5' => 'Primary Checkpoint Preparation',
        
        'lower_title' => 'Lower Secondary (Year 7–9)',
        'lower_item1' => 'Academic English and Written Expression',
        'lower_item2' => 'Intermediate Mathematics',
        'lower_item3' => 'Sciences: Introductory Physics, Chemistry and Biology',
        'lower_item4' => 'Cambridge Lower Secondary Checkpoint Preparation',
        'lower_item5' => 'Online Support Classes',
        
        'upper_title' => 'Upper Secondary (IGCSE – Year 10–11)',
        'upper_item1' => 'Intensive Revision by Subject',
        'upper_item2' => 'IGCSE Exam Practice Tests',
        'upper_item3' => 'Thematic Doubt Sessions and Explanations',
        'upper_item4' => 'Study Strategies Guidance',
        'upper_item5' => 'Personalized Classes with Certified Teachers',
        
        // Dificuldades
        'dificuldades_text' => 'Having difficulties? <br>Let us understand your challenges and we will find the perfect teacher for you!',
        'dificuldades_btn' => 'Describe Difficulties',
        
        // Regimes
        'regimes_titulo' => 'Class Formats',
        'regime_online' => 'Online Classes',
        'regime_online_desc' => 'Videoconference classes according to availability.',
        'regime_domicilio' => 'Home Tutoring',
        'regime_domicilio_desc' => 'A teacher goes to the student\'s home.',
        'regime_estabelecimento' => 'In-Person Classes',
        'regime_estabelecimento_desc' => 'Classes at our tutoring center.',
        
        // Pacotes
        'pacotes_titulo' => 'Tutoring Packages',
        'pacote_basico' => 'Basic Package',
        'pacote_basico_item1' => '2 classes per week (1h each)',
        'pacote_basico_item2' => 'Access to digital materials',
        'pacote_basico_item3' => 'Monthly performance report',
        'pacote_basico_preco' => 'Price: 1.500 MT/month',
        
        'pacote_intermedio' => 'Intermediate Package',
        'pacote_intermedio_item1' => '3 classes per week (1h30 each)',
        'pacote_intermedio_item2' => 'Individualized support',
        'pacote_intermedio_item3' => 'Monthly practice tests',
        'pacote_intermedio_preco' => 'Price: 2.500 MT/month',
        
        'pacote_premium' => 'Premium Package',
        'pacote_premium_item1' => '5 classes per week (2h each)',
        'pacote_premium_item2' => 'Continuous mentoring',
        'pacote_premium_item3' => 'Bi-weekly reports and parent follow-up',
        'pacote_premium_preco' => 'Price: 4.000 MT/month',
        
        // Footer
        'direitos' => 'All rights reserved.',
        'desenvolvido' => 'Developed By Eng:Chelton Mucivane'
    ]
];

$idioma_atual = $_SESSION['idioma'];
$t = $textos[$idioma_atual];
?>
<!DOCTYPE html>
<html lang="<?php echo $idioma_atual; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $idioma_atual == 'pt' ? 'Serviços - Active Learning Academy' : 'Services - Active Learning Academy'; ?></title>
  <link rel="stylesheet" href="css/Servicos.css" />
  <link rel="stylesheet" href="css/Servicos.js" />
</head>
<body>
  
  <!-- HEADER RESPONSIVO COM IDIOMA -->
  <header class="main-header">
    <div class="container header-container">
      <!-- LOGO DA EMPRESA -->
      <div class="logo-container">
        <a href="home.php">
          <img src="img/logo.jpeg" alt="Active Learning Academy Logo" class="site-logo" />
        </a>
      </div>
      
      <!-- CONTEÚDO DO LADO DIREITO -->
      <div class="header-right">
        <!-- SELECTOR DE IDIOMA -->
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
        
        <!-- MENU DESKTOP -->
        <nav class="desktop-nav">
          <ul class="nav-links">
            <li><a href="home.html"><?php echo $t['home']; ?></a></li>
            <li><a href="sobreNos.php"><?php echo $t['sobre_nos']; ?></a></li>
            <li><a href="Login.php"><?php echo $t['login']; ?></a></li>
            <li><a href="Sign-in.php" class="btn-register"><?php echo $t['registro']; ?></a></li>
          </ul>
        </nav>
        
        <!-- MENU HAMBÚRGUER -->
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
          <li><a href="home.php"><?php echo $t['home']; ?></a></li>
          <li><a href="servicos.php" class="active"><?php echo $t['servicos']; ?></a></li>
          <li><a href="sobreNos.php"><?php echo $t['sobre_nos']; ?></a></li>
          <li><a href="Login.php"><?php echo $t['login']; ?></a></li>
          <li><a href="Sign-in.php" class="btn-register"><?php echo $t['registro']; ?></a></li>
        </ul>
      </nav>
    </div>
    
    <!-- OVERLAY -->
    <div class="menu-overlay" id="menuOverlay"></div>
  </header>

  <!-- Carrossel de imagens -->
  <div class="carousel">
    <div class="carousel-track">
      <img src="img/tst.jpeg" alt="Escola 1">
      <img src="img/teste1.jpeg" alt="Escola 2">
      <img src="img/teste2.jpeg" alt="Escola 3">
      <img src="img/teste3.jpeg" alt="Escola 4">
      <img src="img/teste4.jpg" alt="Escola 5">
    </div>

    <button class="carousel-btn left">&#10094;</button>
    <button class="carousel-btn right">&#10095;</button>

    <!-- Overlay com conteúdo -->
    <div class="overlay">
      <h1><?php echo $t['titulo_servicos']; ?></h1>

      <div class="servicos por-nivel">
        <div class="categoria">
          <h2 style="color: #2980b9;"><?php echo $t['primary_title']; ?></h2>
          <ul>
            <li><?php echo $t['primary_item1']; ?></li>
            <li><?php echo $t['primary_item2']; ?></li>
            <li><?php echo $t['primary_item3']; ?></li>
            <li><?php echo $t['primary_item4']; ?></li>
            <li><?php echo $t['primary_item5']; ?></li>
          </ul>
        </div>

        <div class="categoria">
          <h2 style="color: #27ae60;"><?php echo $t['lower_title']; ?></h2>
          <ul>
            <li><?php echo $t['lower_item1']; ?></li>
            <li><?php echo $t['lower_item2']; ?></li>
            <li><?php echo $t['lower_item3']; ?></li>
            <li><?php echo $t['lower_item4']; ?></li>
            <li><?php echo $t['lower_item5']; ?></li>
          </ul>
        </div>

        <div class="categoria">
          <h2 style="color: #c0392b;"><?php echo $t['upper_title']; ?></h2>
          <ul>
            <li><?php echo $t['upper_item1']; ?></li>
            <li><?php echo $t['upper_item2']; ?></li>
            <li><?php echo $t['upper_item3']; ?></li>
            <li><?php echo $t['upper_item4']; ?></li>
            <li><?php echo $t['upper_item5']; ?></li>
          </ul>
        </div>
      </div>

      <div class="Encontrar-Professor">
        <p>
          <?php echo $t['dificuldades_text']; ?>
        </p>
        <a href="Sign-in.php" class="btn-cta"><?php echo $t['dificuldades_btn']; ?></a>
      </div>
    </div>
  </div>

  <!-- Regimes de Aulas -->
  <section class="regimes-section">
    <h2 class="regimes-title"><?php echo $t['regimes_titulo']; ?></h2>
    <div class="regimes">
      <div class="regime-card">
        <img src="img/on1.jpg" alt="Aulas Online">
        <h3><?php echo $t['regime_online']; ?></h3>
        <p><?php echo $t['regime_online_desc']; ?></p>
      </div>
      <div class="regime-card">
        <img src="img/Casa.jpeg" alt="Aulas ao domicílio">
        <h3><?php echo $t['regime_domicilio']; ?></h3>
        <p><?php echo $t['regime_domicilio_desc']; ?></p>
      </div>
      <div class="regime-card">
        <img src="img/Aga1.jpeg" alt="Aulas no Estabelecimento">
        <h3><?php echo $t['regime_estabelecimento']; ?></h3>
        <p><?php echo $t['regime_estabelecimento_desc']; ?></p>
      </div>
    </div>
  </section>

  <!-- Pacotes de Reforço -->
  <section class="pacotes-section">
    <h2 class="pacotes-title"><?php echo $t['pacotes_titulo']; ?></h2>
    <div class="pacotes">
      <div class="pacote-card">
        <h3><?php echo $t['pacote_basico']; ?></h3>
        <p><?php echo $t['pacote_basico_item1']; ?></p>
        <p><?php echo $t['pacote_basico_item2']; ?></p>
        <p><?php echo $t['pacote_basico_item3']; ?></p>
        <p><strong><?php echo $t['pacote_basico_preco']; ?></strong></p>
      </div>
      <div class="pacote-card">
        <h3><?php echo $t['pacote_intermedio']; ?></h3>
        <p><?php echo $t['pacote_intermedio_item1']; ?></p>
        <p><?php echo $t['pacote_intermedio_item2']; ?></p>
        <p><?php echo $t['pacote_intermedio_item3']; ?></p>
        <p><strong><?php echo $t['pacote_intermedio_preco']; ?></strong></p>
      </div>
      <div class="pacote-card">
        <h3><?php echo $t['pacote_premium']; ?></h3>
        <p><?php echo $t['pacote_premium_item1']; ?></p>
        <p><?php echo $t['pacote_premium_item2']; ?></p>
        <p><?php echo $t['pacote_premium_item3']; ?></p>
        <p><strong><?php echo $t['pacote_premium_preco']; ?></strong></p>
      </div>
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

  <script src="JavaScript/servicos.js"></script>
  <script src="JavaScript/servicos_responsivo.js"></script>
</body>
</html>