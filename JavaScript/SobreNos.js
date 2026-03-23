// JavaScript/sobre.js
document.addEventListener('DOMContentLoaded', function() {
    // ========== ELEMENTOS ==========
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenu = document.getElementById('closeMenu');
    const overlay = document.getElementById('menuOverlay');
    const body = document.body;
    
    const languageSelector = document.getElementById('languageSelector');
    const languageBtn = languageSelector?.querySelector('.language-btn');
    
    let menuOpen = false;
    
    // ========== FUNÇÕES DO MENU ==========
    
    function toggleMenu() {
        menuOpen = !menuOpen;
        mobileMenu.classList.toggle('active');
        overlay.classList.toggle('active');
        body.classList.toggle('menu-open');
        
        if (menuOpen) {
            body.style.overflow = 'hidden';
            // Fecha dropdown de idioma se aberto
            if (languageSelector) {
                languageSelector.classList.remove('active');
            }
        } else {
            body.style.overflow = '';
        }
    }
    
    function closeMobileMenu() {
        if (menuOpen) {
            toggleMenu();
        }
    }
    
    // ========== FUNÇÕES DO IDIOMA ==========
    
    // Alterna dropdown de idioma
    if (languageBtn) {
        languageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            languageSelector.classList.toggle('active');
        });
    }
    
    // Fecha dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (languageSelector && !languageSelector.contains(e.target)) {
            languageSelector.classList.remove('active');
        }
    });
    
    // Fecha tudo com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (menuOpen) {
                closeMobileMenu();
            }
            if (languageSelector) {
                languageSelector.classList.remove('active');
            }
        }
    });
    
    // ========== EVENT LISTENERS ==========
    
    // Menu hambúrguer
    if (hamburger) {
        hamburger.addEventListener('click', toggleMenu);
    }
    
    // Botão close
    if (closeMenu) {
        closeMenu.addEventListener('click', toggleMenu);
    }
    
    // Overlay
    if (overlay) {
        overlay.addEventListener('click', toggleMenu);
    }
    
    // Links do menu mobile (fecham menu ao clicar)
    const mobileLinks = document.querySelectorAll('.mobile-nav a');
    mobileLinks.forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });
    
    // Fecha menu ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && menuOpen) {
            closeMobileMenu();
        }
    });
    
    // ========== ANIMAÇÃO DE SCROLL SUAVE ==========
    // Para links âncora dentro da página (se houver)
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // ========== ANIMAÇÃO AO SCROLL ==========
    // Anima os elementos da seção "Sobre Nós" quando entram na viewport
    function animateOnScroll() {
        const elements = document.querySelectorAll('.sobre-nos h2, .sobre-nos p');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const screenPosition = window.innerHeight / 1.2;
            
            if (elementPosition < screenPosition) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }
    
    // Inicializa elementos com estilo inicial
    const sobreElements = document.querySelectorAll('.sobre-nos h2, .sobre-nos p');
    sobreElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    });
    
    // Dispara animação no carregamento e no scroll
    window.addEventListener('load', animateOnScroll);
    window.addEventListener('scroll', animateOnScroll);
});