// home.js - FUNCIONALIDADES EXCLUSIVAS DA PÁGINA HOME
console.log('🏠 home.js - Exclusivo para Home.html');

// ========== CONFIGURAÇÕES DA HOME ==========
const HOME_CONFIG = {
    carouselSpeed: 20,    // Velocidade do carrossel
    autoScroll: true,     // Rolagem automática
    touchEnabled: true,   // Suporte a touch
    defaultLanguage: 'pt' // Idioma padrão
};

// ========== TRADUÇÕES ESPECÍFICAS DA HOME ==========
const HOME_TRANSLATIONS = {
    'pt': {
        // Header Home
        'home': 'Home',
        'servicos': 'Serviços',
        'sobre_nos': 'Sobre Nós',
        'login': 'Login',
        'registro': 'Registro',
        'selecionar_idioma': 'Selecionar idioma',
        
        // Hero Section
        'heroTitle': 'Impulsionando o Sucesso Académico no Currículo Cambridge',
        'heroDesc': 'Somos a ponte entre os desafios e o sucesso dos estudantes moçambicanos no currículo internacional Cambridge.',
        'exploreServices': 'Explorar Serviços',
        
        // Como Ajudamos
        'howHelp': 'Como Podemos Ajudar',
        'helpDesc': 'Ligamos alunos com explicadores experientes e formados no currículo Cambridge.<br>Os nossos professores oferecem acompanhamento personalizado, adaptado às dificuldades específicas de cada estudante.<br>Seja online, ao domicílio ou em centros de estudo, oferecemos apoio contínuo para garantir progresso real e confiança no processo de aprendizagem.',
        
        // Dificuldades
        'difficultiesTitle': 'Qual é o problema que o seu filho enfrenta no currículo Cambridge?',
        'diff1': 'Falta de domínio da língua inglesa',
        'diff1Desc': 'Os alunos enfrentam dificuldades por não dominarem o inglês.',
        'diff2': 'Adaptação a metodologias estrangeiras',
        'diff2Desc': 'O modelo de ensino exige mais autonomia e pensamento crítico.',
        'diff3': 'Acesso limitado a recursos didáticos',
        'diff3Desc': 'Muitos alunos não têm livros, internet ou materiais adequados.',
        'diff4': 'Falta de apoio personalizado',
        'diff4Desc': 'Nem todos recebem a atenção necessária para superar dificuldades.',
        'diff5': 'Pressão para altos desempenhos',
        'diff5Desc': 'O currículo Cambridge exige excelência, o que pode causar stress.',
        'diff6': 'Falta de professores especializados',
        'diff6Desc': 'Há escassez de docentes formados para o padrão Cambridge.',
        'diff7': 'Necessidades Educativas Especiais',
        'diff7Desc': 'Estudantes com necessidades especiais não recebem suporte adequado.',
        
        // Soluções
        'solutionsTitle': 'Temos Soluções para:',
        'primary': 'Primary',
        'primaryDesc': 'Foco no desenvolvimento das competências básicas com reforço em leitura, escrita, matemática e compreensão.',
        'lowerSecondary': 'Lower Secondary',
        'lowerSecondaryDesc': 'Consolidação dos conhecimentos fundamentais com suporte em ciências, línguas e raciocínio lógico.',
        'upperSecondary': 'Upper Secondary',
        'upperSecondaryDesc': 'Preparação direcionada para exames IGCSE, AS & A Levels, com foco na performance e autonomia de estudo.',
        
        // Pacotes
        'packagesTitle': 'Pacotes Disponíveis',
        'basicPackage': 'Pacote Básico',
        'basicDesc': 'Inclui 4 aulas por mês.<br>Ideal para revisão de conteúdos ou dificuldades específicas.',
        'intermediatePackage': 'Pacote Intermédio',
        'intermediateDesc': 'Inclui 8 aulas por mês.<br>Recomendado para acompanhamento regular de matérias escolares.',
        'intensivePackage': 'Pacote Intensivo',
        'intensiveDesc': 'Inclui 12+ aulas por mês.<br>Preparação intensiva para exames Cambridge.',
        
        // Contacto
        'contactTitle': 'Contacte-nos',
        'contactDesc': 'Tem dúvidas ou gostaria de saber mais sobre os nossos serviços?<br>Fale connosco diretamente no WhatsApp.',
        'whatsappTalk': 'Fale no WhatsApp',
        
        // Footer
        'rights': 'Todos os direitos reservados',
        'developedBy': 'Desenvolvido Por'
    },
    
    'en': {
        // Header Home
        'home': 'Home',
        'servicos': 'Services',
        'sobre_nos': 'About Us',
        'login': 'Login',
        'registro': 'Register',
        'selecionar_idioma': 'Select language',
        
        // Hero Section
        'heroTitle': 'Driving Academic Success in the Cambridge Curriculum',
        'heroDesc': 'We are the bridge between challenges and success for Mozambican students in the Cambridge international curriculum.',
        'exploreServices': 'Explore Services',
        
        // Como Ajudamos
        'howHelp': 'How We Can Help',
        'helpDesc': 'We connect students with experienced tutors trained in the Cambridge curriculum.<br>Our teachers provide personalized support, tailored to the specific difficulties of each student.<br>Whether online, at home or in study centers, we offer continuous support to ensure real progress and confidence in the learning process.',
        
        // Dificuldades
        'difficultiesTitle': 'What problem does your child face in the Cambridge curriculum?',
        'diff1': 'Lack of English language proficiency',
        'diff1Desc': 'Students struggle due to not mastering English.',
        'diff2': 'Adaptation to foreign methodologies',
        'diff2Desc': 'The teaching model requires more autonomy and critical thinking.',
        'diff3': 'Limited access to teaching resources',
        'diff3Desc': 'Many students lack books, internet or adequate materials.',
        'diff4': 'Lack of personalized support',
        'diff4Desc': 'Not everyone receives the necessary attention to overcome difficulties.',
        'diff5': 'Pressure for high performance',
        'diff5Desc': 'The Cambridge curriculum requires excellence, which can cause stress.',
        'diff6': 'Lack of specialized teachers',
        'diff6Desc': 'There is a shortage of teachers trained for the Cambridge standard.',
        'diff7': 'Special Educational Needs',
        'diff7Desc': 'Students with special needs do not receive adequate support.',
        
        // Soluções
        'solutionsTitle': 'We Have Solutions for:',
        'primary': 'Primary',
        'primaryDesc': 'Focus on developing basic skills with reinforcement in reading, writing, mathematics and comprehension.',
        'lowerSecondary': 'Lower Secondary',
        'lowerSecondaryDesc': 'Consolidation of fundamental knowledge with support in sciences, languages and logical reasoning.',
        'upperSecondary': 'Upper Secondary',
        'upperSecondaryDesc': 'Targeted preparation for IGCSE, AS & A Levels exams, focusing on performance and study autonomy.',
        
        // Pacotes
        'packagesTitle': 'Available Packages',
        'basicPackage': 'Basic Package',
        'basicDesc': 'Includes 4 classes per month.<br>Ideal for content review or specific difficulties.',
        'intermediatePackage': 'Intermediate Package',
        'intermediateDesc': 'Includes 8 classes per month.<br>Recommended for regular monitoring of school subjects.',
        'intensivePackage': 'Intensive Package',
        'intensiveDesc': 'Includes 12+ classes per month.<br>Intensive preparation for Cambridge exams.',
        
        // Contacto
        'contactTitle': 'Contact Us',
        'contactDesc': 'Have questions or want to know more about our services?<br>Talk to us directly on WhatsApp.',
        'whatsappTalk': 'Talk on WhatsApp',
        
        // Footer
        'rights': 'All rights reserved',
        'developedBy': 'Developed By'
    }
};

// ========== VARIÁVEIS GLOBAIS ==========
let currentHomeLanguage = HOME_CONFIG.defaultLanguage;
let isMenuOpen = false;
let autoScrollInterval = null;

// ========== INICIALIZAÇÃO DA HOME ==========
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Home.html completamente carregada');
    
    // 1. Inicializar sistema de idiomas
    initLanguageSystem();
    
    // 2. Inicializar menu
    initHomeMenu();
    
    // 3. Inicializar carrossel
    initHomeCarousel();
    
    // 4. Adicionar estilos dinâmicos
    addDynamicStyles();
    
    console.log('🎉 Home.js inicializado com sucesso!');
});

// ========== SISTEMA DE IDIOMAS CORRIGIDO ==========
function initLanguageSystem() {
    console.log('🌐 Iniciando sistema de idiomas da Home');
    
    // 1. Obter idioma atual
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');
    
    // Prioridade: URL > localStorage > padrão
    if (urlLang && (urlLang === 'pt' || urlLang === 'en')) {
        currentHomeLanguage = urlLang;
        localStorage.setItem('homeLanguage', currentHomeLanguage);
        console.log(`🌐 Idioma da URL: ${currentHomeLanguage.toUpperCase()}`);
    } else if (localStorage.getItem('homeLanguage')) {
        currentHomeLanguage = localStorage.getItem('homeLanguage');
        console.log(`🌐 Idioma do localStorage: ${currentHomeLanguage.toUpperCase()}`);
    } else {
        currentHomeLanguage = HOME_CONFIG.defaultLanguage;
        console.log(`🌐 Idioma padrão: ${currentHomeLanguage.toUpperCase()}`);
    }
    
    // 2. Aplicar traduções imediatamente
    applyTranslations(currentHomeLanguage);
    
    // 3. Configurar o botão de idioma
    setupLanguageButton();
    
    // 4. Configurar os links de idioma
    setupLanguageLinks();
}

// ========== FUNÇÃO PARA APLICAR TRADUÇÕES ==========
function applyTranslations(lang) {
    console.log(`🔄 Aplicando traduções: ${lang.toUpperCase()}`);
    
    const translations = HOME_TRANSLATIONS[lang];
    if (!translations) {
        console.error(`❌ Traduções não encontradas para: ${lang}`);
        return;
    }
    
    // Traduzir elementos com data-i18n
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (translations[key]) {
            element.innerHTML = translations[key];
        }
    });
    
    // Atualizar atributo lang do HTML
    document.documentElement.lang = lang;
    
    console.log(`✅ Traduções aplicadas para: ${lang.toUpperCase()}`);
}

// ========== CONFIGURAR BOTÃO DE IDIOMA ==========
function setupLanguageButton() {
    const languageSelector = document.getElementById('languageSelector');
    const languageBtn = document.querySelector('.language-btn');
    
    if (!languageSelector || !languageBtn) {
        console.error('❌ Elementos do seletor de idioma não encontrados');
        return;
    }
    
    console.log('🔧 Configurando botão de idioma...');
    
    // Atualizar texto do botão
    const flagSpan = languageBtn.querySelector('.language-flag');
    const textSpan = languageBtn.querySelector('.language-text');
    
    if (currentHomeLanguage === 'en') {
        if (flagSpan) flagSpan.textContent = '🇬🇧';
        if (textSpan) textSpan.textContent = 'EN';
    } else {
        if (flagSpan) flagSpan.textContent = '🇵🇹';
        if (textSpan) textSpan.textContent = 'PT';
    }
    
    // Mostrar/ocultar dropdown ao clicar
    languageBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        
        const isActive = languageSelector.classList.contains('active');
        
        // Fechar todos os dropdowns abertos
        document.querySelectorAll('.language-selector.active').forEach(el => {
            if (el !== languageSelector) {
                el.classList.remove('active');
            }
        });
        
        // Alternar este dropdown
        languageSelector.classList.toggle('active');
        
        console.log(`🔽 Dropdown de idioma: ${languageSelector.classList.contains('active') ? 'ABERTO' : 'FECHADO'}`);
    });
    
    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!languageSelector.contains(e.target)) {
            languageSelector.classList.remove('active');
        }
    });
    
    // Fechar dropdown com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && languageSelector.classList.contains('active')) {
            languageSelector.classList.remove('active');
        }
    });
    
    console.log('✅ Botão de idioma configurado');
}

// ========== CONFIGURAR LINKS DE IDIOMA ==========
function setupLanguageLinks() {
    const languageLinks = document.querySelectorAll('.language-option');
    
    console.log(`🔗 Configurando ${languageLinks.length} links de idioma...`);
    
    languageLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const lang = this.getAttribute('data-lang') || 'pt';
            
            if (lang !== currentHomeLanguage) {
                console.log(`🔄 Alterando idioma para: ${lang.toUpperCase()}`);
                
                // Atualizar idioma
                currentHomeLanguage = lang;
                localStorage.setItem('homeLanguage', lang);
                
                // Fechar dropdown
                const languageSelector = document.getElementById('languageSelector');
                if (languageSelector) {
                    languageSelector.classList.remove('active');
                }
                
                // Recarregar página com novo idioma
                const url = new URL(window.location);
                url.searchParams.set('lang', lang);
                window.location.href = url.toString();
            } else {
                console.log(`ℹ️ Idioma já está em: ${lang.toUpperCase()}`);
                const languageSelector = document.getElementById('languageSelector');
                if (languageSelector) {
                    languageSelector.classList.remove('active');
                }
            }
        });
    });
    
    console.log('✅ Links de idioma configurados');
}

// ========== MENU DA HOME ==========
function initHomeMenu() {
    console.log('📱 Iniciando menu da Home');
    
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenu = document.getElementById('closeMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    
    if (!hamburger || !mobileMenu) {
        console.log('⚠️ Menu da Home não encontrado');
        return;
    }
    
    // Função para abrir/fechar menu
    function toggleMenu() {
        isMenuOpen = !isMenuOpen;
        
        if (isMenuOpen) {
            // Abrir menu
            mobileMenu.classList.add('active');
            if (menuOverlay) menuOverlay.classList.add('active');
            document.body.classList.add('menu-open');
            document.body.style.overflow = 'hidden';
        } else {
            // Fechar menu
            mobileMenu.classList.remove('active');
            if (menuOverlay) menuOverlay.classList.remove('active');
            document.body.classList.remove('menu-open');
            document.body.style.overflow = '';
        }
    }
    
    // Event Listeners
    hamburger.addEventListener('click', function(e) {
        e.preventDefault();
        toggleMenu();
    });
    
    if (closeMenu) {
        closeMenu.addEventListener('click', function(e) {
            e.preventDefault();
            toggleMenu();
        });
    }
    
    if (menuOverlay) {
        menuOverlay.addEventListener('click', function(e) {
            e.preventDefault();
            if (isMenuOpen) toggleMenu();
        });
    }
    
    // Fechar ao clicar em links
    const mobileLinks = document.querySelectorAll('.mobile-nav a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (isMenuOpen) toggleMenu();
        });
    });
    
    // Fechar com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isMenuOpen) {
            toggleMenu();
        }
    });
    
    // Fechar ao redimensionar
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && isMenuOpen) {
            toggleMenu();
        }
    });
    
    console.log('✅ Menu da Home configurado');
}

// ========== CARROSSEL DA HOME ==========
function initHomeCarousel() {
    console.log('🎠 Iniciando carrossel da Home');
    
    const carouselTrack = document.querySelector('#carousel-track');
    if (!carouselTrack) {
        console.log('⚠️ Carrossel não encontrado na Home');
        return;
    }
    
    const cards = carouselTrack.querySelectorAll('.card');
    if (cards.length === 0) {
        console.log('⚠️ Nenhum card encontrado no carrossel');
        return;
    }
    
    // Configurações
    let scrollPosition = 0;
    const cardWidth = cards[0].offsetWidth + 20; // Largura + gap
    const totalWidth = cardWidth * cards.length;
    
    // Duplicar cards para loop infinito
    cards.forEach(card => {
        const clone = card.cloneNode(true);
        clone.classList.add('clone');
        carouselTrack.appendChild(clone);
    });
    
    // Função de rolagem automática
    function startAutoScroll() {
        if (!HOME_CONFIG.autoScroll) return;
        
        autoScrollInterval = setInterval(() => {
            scrollPosition += 1;
            
            // Reset suave quando chegar ao final
            if (scrollPosition >= totalWidth) {
                scrollPosition = 0;
                carouselTrack.style.transition = 'none';
                carouselTrack.style.transform = `translateX(0)`;
                void carouselTrack.offsetWidth; // Forçar reflow
                carouselTrack.style.transition = 'transform 0.3s ease';
            }
            
            carouselTrack.style.transform = `translateX(-${scrollPosition}px)`;
        }, HOME_CONFIG.carouselSpeed);
    }
    
    // Controlar rolagem
    function stopAutoScroll() {
        clearInterval(autoScrollInterval);
    }
    
    // Iniciar
    startAutoScroll();
    
    // Interação do usuário
    carouselTrack.addEventListener('mouseenter', stopAutoScroll);
    carouselTrack.addEventListener('mouseleave', startAutoScroll);
    
    // Suporte touch
    if (HOME_CONFIG.touchEnabled) {
        let touchStartX = 0;
        let isDragging = false;
        
        carouselTrack.addEventListener('touchstart', function(e) {
            touchStartX = e.touches[0].clientX;
            stopAutoScroll();
            isDragging = true;
        });
        
        carouselTrack.addEventListener('touchmove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            
            const touchX = e.touches[0].clientX;
            const diff = touchStartX - touchX;
            
            scrollPosition = Math.max(0, Math.min(scrollPosition + diff * 0.5, totalWidth));
            carouselTrack.style.transform = `translateX(-${scrollPosition}px)`;
            
            touchStartX = touchX;
        });
        
        carouselTrack.addEventListener('touchend', function() {
            isDragging = false;
            setTimeout(startAutoScroll, 2000);
        });
    }
    
    console.log(`✅ Carrossel configurado com ${cards.length} cards`);
}

// ========== ESTILOS DINÂMICOS ==========
function addDynamicStyles() {
    // Verificar se os estilos já foram adicionados
    if (document.getElementById('dynamic-home-styles')) {
        return;
    }
    
    const style = document.createElement('style');
    style.id = 'dynamic-home-styles';
    style.textContent = `
        /* Estilos para o dropdown de idiomas */
        .language-selector {
            position: relative;
        }
        
        .language-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            min-width: 160px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1002;
            overflow: hidden;
            display: block !important;
        }
        
        .language-selector.active .language-dropdown {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .language-option {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            color: #333;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
        }
        
        .language-option:hover {
            background-color: #f5f5f5;
        }
        
        .language-option.active {
            background-color: #e8f4ff;
            color: #003366;
            font-weight: 600;
        }
        
        /* Menu mobile */
        .mobile-menu {
            display: block !important;
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mobile-menu.active {
            transform: translateX(0);
        }
        
        .menu-overlay {
            display: none;
        }
        
        .menu-overlay.active {
            display: block;
        }
        
        /* Carrossel */
        .carousel-track {
            display: flex;
            transition: transform 0.3s ease;
        }
        
        body.menu-open {
            overflow: hidden !important;
        }
    `;
    
    document.head.appendChild(style);
    console.log('🎨 Estilos dinâmicos adicionados');
}

// ========== FUNÇÕES UTILITÁRIAS ==========

// Detectar dispositivo móvel
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Aplicar classes para dispositivos
if (isMobileDevice()) {
    document.body.classList.add('mobile-device');
    console.log('📱 Dispositivo móvel detectado');
}

// Debug helper
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    window.homeDebug = {
        setLanguage: function(lang) {
            if (lang === 'pt' || lang === 'en') {
                currentHomeLanguage = lang;
                localStorage.setItem('homeLanguage', lang);
                applyTranslations(lang);
                console.log(`Idioma alterado para: ${lang.toUpperCase()}`);
            }
        },
        getCurrentLanguage: function() {
            return currentHomeLanguage;
        },
        reloadPage: function() {
            window.location.reload();
        }
    };
    console.log('🐛 Debug helper disponível: window.homeDebug');
}

// Log quando a página está totalmente carregada
window.addEventListener('load', function() {
    console.log('🏁 Página Home totalmente carregada e interativa');
});