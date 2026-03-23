// registo.js
document.addEventListener('DOMContentLoaded', function() {
    // ================= MENU HAMBÚRGUER =================
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');
    const menuIcon = document.querySelector('.menu-icon');
    const closeIcon = document.querySelector('.close-icon');
    
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isActive = navMenu.classList.toggle('active');
            
            // Alternar entre ícones menu/close
            if (isActive) {
                if (menuIcon) menuIcon.style.display = 'none';
                if (closeIcon) closeIcon.style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                if (menuIcon) menuIcon.style.display = 'block';
                if (closeIcon) closeIcon.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }
    
    // Fechar menu ao clicar fora (mobile)
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && navMenu && navMenu.classList.contains('active')) {
            if (!navMenu.contains(event.target) && !menuToggle.contains(event.target)) {
                closeMenu();
            }
        }
    });
    
    // Fechar menu ao pressionar ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && navMenu && navMenu.classList.contains('active')) {
            closeMenu();
        }
    });
    
    function closeMenu() {
        if (navMenu && navMenu.classList.contains('active')) {
            navMenu.classList.remove('active');
            if (menuIcon) menuIcon.style.display = 'block';
            if (closeIcon) closeIcon.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Fechar menu ao clicar em um link (mobile)
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });
    
    // ================= DROPDOWN DE IDIOMA =================
    const languageSelector = document.getElementById('languageSelector');
    const languageBtn = document.querySelector('.language-btn');
    
    if (languageSelector && languageBtn) {
        // Toggle do dropdown
        languageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            languageSelector.classList.toggle('active');
        });
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            if (!languageSelector.contains(e.target)) {
                languageSelector.classList.remove('active');
            }
        });
        
        // Fechar dropdown ao pressionar ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && languageSelector.classList.contains('active')) {
                languageSelector.classList.remove('active');
            }
        });
        
        // Fechar dropdown ao selecionar uma opção (delay para ver a seleção)
        const languageOptions = document.querySelectorAll('.language-option');
        languageOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Adicionar efeito visual de seleção
                this.style.backgroundColor = '#e8f4ff';
                
                // Fechar dropdown após um breve delay
                setTimeout(() => {
                    languageSelector.classList.remove('active');
                }, 300);
            });
        });
    }
    
    // ================= RESPONSIVIDADE =================
    window.addEventListener('resize', function() {
        // Fechar menu hambúrguer se voltar para desktop
        if (window.innerWidth > 768 && navMenu && navMenu.classList.contains('active')) {
            closeMenu();
        }
        
        // Fechar dropdown de idioma em mobile quando menu abre
        if (window.innerWidth <= 768 && navMenu && navMenu.classList.contains('active')) {
            if (languageSelector) languageSelector.classList.remove('active');
        }
    });
    
    // ================= INICIALIZAÇÃO =================
    // Garantir que ícones estão corretos no carregamento
    if (menuIcon) menuIcon.style.display = 'block';
    if (closeIcon) closeIcon.style.display = 'none';
    
    // Ajustar dropdown para mobile
    function adjustLanguageDropdown() {
        if (window.innerWidth <= 480) {
            const languageTexts = document.querySelectorAll('.language-text');
            languageTexts.forEach(text => {
                if (text.textContent.length > 2) {
                    text.textContent = text.textContent.substring(0, 2);
                }
            });
        }
    }
    
    adjustLanguageDropdown();
    window.addEventListener('resize', adjustLanguageDropdown);
});