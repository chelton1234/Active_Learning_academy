// login.js
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 login.js iniciado');
    
    // ========== ELEMENTOS ==========
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenu = document.getElementById('closeMenu');
    const overlay = document.getElementById('menuOverlay');
    const body = document.body;
    
    const languageSelector = document.getElementById('languageSelector');
    const languageBtn = document.querySelector('.language-btn');
    
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
            console.log('🔽 Dropdown de idioma ' + 
                (languageSelector.classList.contains('active') ? 'ABERTO' : 'FECHADO'));
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
    
    // ========== VERIFICAÇÃO DE ÍCONES ==========
    setTimeout(() => {
        const menuIcon = document.querySelector('.hamburger img');
        const closeIcon = document.querySelector('.close-menu img');
        
        // Fallback SVG para ícones que não carregaram
        const menuSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
        </svg>`;
        
        const closeSVG = `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>`;
        
        if (menuIcon && (menuIcon.naturalWidth === 0 || !menuIcon.complete)) {
            console.log('⚠️ Ícone menu não carregou, usando fallback');
            menuIcon.outerHTML = menuSVG;
        }
        
        if (closeIcon && (closeIcon.naturalWidth === 0 || !closeIcon.complete)) {
            console.log('⚠️ Ícone close não carregou, usando fallback');
            closeIcon.outerHTML = closeSVG;
        }
    }, 1000);
    
    // ========== VALIDAÇÃO DO FORMULÁRIO ==========
    const loginForm = document.querySelector('.login-form');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const senha = document.getElementById('senha');
            
            if (!email.value.trim()) {
                e.preventDefault();
                alert('Por favor, preencha o email');
                email.focus();
                return false;
            }
            
            if (!senha.value.trim()) {
                e.preventDefault();
                alert('Por favor, preencha a senha');
                senha.focus();
                return false;
            }
        });
    }
    
    // ========== DETECTAR DISPOSITIVO ==========
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }
    
    if (isIOS()) {
        console.log('📱 Dispositivo iOS detectado');
        
        document.addEventListener('focus', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                setTimeout(() => {
                    e.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            }
        }, true);
    }
    
    console.log('🏁 login.js carregado e pronto');
});