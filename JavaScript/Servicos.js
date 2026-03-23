// servicos.js - Carrossel automático + Menu responsivo + Sistema de idiomas
document.addEventListener('DOMContentLoaded', function() {
    console.log('Servicos.js carregado - Iniciando todas as funcionalidades');
    
    // ========== VARIÁVEIS GLOBAIS ==========
    let menuOpen = false;
    let animationId = null;
    let isAnimating = true;
    let isPaused = false;
    
    // ========== CONFIGURAÇÕES DO CARROSSEL ==========
    const CAROUSEL_CONFIG = {
        speed: 0.3,
        pauseOnHover: true,
        responsiveSpeed: true,
        autoPlay: true,
        transitionSmooth: true
    };
    
    // ========== ELEMENTOS DO MENU E IDIOMAS ==========
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenu = document.getElementById('closeMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const body = document.body;
    
    const languageSelector = document.getElementById('languageSelector');
    const languageBtn = languageSelector ? languageSelector.querySelector('.language-btn') : null;
    const languageDropdown = languageSelector ? languageSelector.querySelector('.language-dropdown') : null;
    
    // ========== ELEMENTOS DO CARROSSEL ==========
    const track = document.querySelector('.carousel-track');
    const images = document.querySelectorAll('.carousel-track img');
    const leftBtn = document.querySelector('.carousel-btn.left');
    const rightBtn = document.querySelector('.carousel-btn.right');
    
    // ========== INICIALIZAÇÃO DE TODAS AS FUNCIONALIDADES ==========
    
    function initAllFeatures() {
        console.log('Inicializando todas as funcionalidades...');
        
        // 1. Inicializar menu hambúrguer
        if (hamburger || mobileMenu) {
            initMobileMenu();
        } else {
            console.warn('Elementos do menu mobile não encontrados');
        }
        
        // 2. Inicializar selector de idiomas
        if (languageSelector) {
            initLanguageSelector();
        } else {
            console.warn('Selector de idiomas não encontrado');
        }
        
        // 3. Inicializar carrossel
        if (track && images.length > 0) {
            initCarousel();
        } else {
            console.warn('Elementos do carrossel não encontrados');
        }
        
        // 4. Adicionar estilos dinâmicos
        addDynamicStyles();
        
        console.log('Todas as funcionalidades inicializadas com sucesso!');
    }
    
    // ========== FUNÇÕES DO MENU HAMBÚRGUER ==========
    
    function initMobileMenu() {
        console.log('Inicializando menu mobile...');
        
        // Garantir que o menu está escondido inicialmente
        if (mobileMenu) {
            mobileMenu.style.display = 'block';
            mobileMenu.classList.remove('active');
        }
        
        if (menuOverlay) {
            menuOverlay.classList.remove('active');
        }
        
        // Event listener para o botão hambúrguer
        if (hamburger) {
            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMobileMenu();
            });
        }
        
        // Event listener para o botão fechar
        if (closeMenu) {
            closeMenu.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMobileMenu();
            });
        }
        
        // Event listener para o overlay
        if (menuOverlay) {
            menuOverlay.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMobileMenu();
            });
        }
        
        // Fechar menu ao clicar em links
        const mobileLinks = document.querySelectorAll('.mobile-nav a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });
        
        // Fechar menu com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && menuOpen) {
                closeMobileMenu();
            }
        });
        
        // Fechar menu ao redimensionar para desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && menuOpen) {
                closeMobileMenu();
            }
        });
    }
    
    function toggleMobileMenu() {
        menuOpen = !menuOpen;
        
        if (mobileMenu) {
            mobileMenu.classList.toggle('active');
        }
        
        if (menuOverlay) {
            menuOverlay.classList.toggle('active');
        }
        
        body.classList.toggle('menu-open');
        
        if (menuOpen) {
            body.style.overflow = 'hidden';
            // Fecha dropdown de idioma se aberto
            if (languageSelector && languageSelector.classList.contains('active')) {
                languageSelector.classList.remove('active');
            }
        } else {
            body.style.overflow = '';
        }
        
        console.log('Menu mobile:', menuOpen ? 'aberto' : 'fechado');
    }
    
    function closeMobileMenu() {
        if (menuOpen) {
            toggleMobileMenu();
        }
    }
    
    // ========== FUNÇÕES DO SELECTOR DE IDIOMAS ==========
    
    function initLanguageSelector() {
        console.log('Inicializando selector de idiomas...');
        
        if (languageBtn) {
            languageBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                
                // Alterna a classe active no selector
                languageSelector.classList.toggle('active');
                console.log('Dropdown de idiomas:', languageSelector.classList.contains('active') ? 'aberto' : 'fechado');
            });
        }
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            if (languageSelector && !languageSelector.contains(e.target)) {
                languageSelector.classList.remove('active');
            }
        });
        
        // Fechar dropdown com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && languageSelector && languageSelector.classList.contains('active')) {
                languageSelector.classList.remove('active');
            }
        });
        
        // Fechar dropdown ao selecionar uma opção
        const languageOptions = document.querySelectorAll('.language-option');
        languageOptions.forEach(option => {
            option.addEventListener('click', function() {
                console.log('Idioma selecionado:', this.querySelector('span').textContent);
                // A mudança de idioma será tratada pelo PHP via GET parameter
                // O dropdown será fechado automaticamente pelo redirecionamento
            });
        });
    }
    
    // ========== FUNÇÕES DO CARROSSEL ==========
    
    function initCarousel() {
        console.log('Inicializando carrossel...');
        
        // Variáveis do carrossel
        let position = 0;
        let currentSpeed = CAROUSEL_CONFIG.speed;
        let totalWidth = 0;
        
        // Duplica imagens para loop infinito
        function duplicateImagesForInfiniteLoop() {
            const existingClones = track.querySelectorAll('.carousel-clone');
            existingClones.forEach(clone => clone.remove());
            
            images.forEach(img => {
                const clone = img.cloneNode(true);
                clone.classList.add('carousel-clone');
                track.appendChild(clone);
            });
        }
        
        // Ajusta velocidade conforme tamanho da tela
        function adjustSpeedForResponsiveness() {
            if (!CAROUSEL_CONFIG.responsiveSpeed) return;
            
            const screenWidth = window.innerWidth;
            
            if (screenWidth < 768) {
                currentSpeed = CAROUSEL_CONFIG.speed * 0.7;
            } else {
                currentSpeed = CAROUSEL_CONFIG.speed;
            }
        }
        
        // Função de animação principal
        function animate() {
            if (!isPaused && isAnimating) {
                position -= currentSpeed;
                totalWidth = track.scrollWidth / 2;
                
                // Reset suave quando chegar ao final
                if (Math.abs(position) >= totalWidth) {
                    if (CAROUSEL_CONFIG.transitionSmooth) {
                        track.style.transition = 'transform 0.5s ease';
                        position = 0;
                        track.style.transform = `translateX(${position}px)`;
                        
                        setTimeout(() => {
                            track.style.transition = 'none';
                        }, 500);
                    } else {
                        position = 0;
                        track.style.transform = `translateX(${position}px)`;
                    }
                } else {
                    track.style.transform = `translateX(${position}px)`;
                }
            }
            
            animationId = requestAnimationFrame(animate);
        }
        
        // Inicia animação
        function startAnimation() {
            if (animationId) {
                cancelAnimationFrame(animationId);
            }
            isAnimating = true;
            isPaused = false;
            animate();
        }
        
        // Pausa animação
        function pauseAnimation() {
            isPaused = true;
        }
        
        // Retoma animação
        function resumeAnimation() {
            if (isAnimating) {
                isPaused = false;
            }
        }
        
        // Navegação manual
        function navigateLeft() {
            if (!isAnimating) return;
            
            const wasPaused = isPaused;
            pauseAnimation();
            
            position += totalWidth / images.length;
            track.style.transition = 'transform 0.3s ease';
            track.style.transform = `translateX(${position}px)`;
            
            setTimeout(() => {
                track.style.transition = 'none';
                if (!wasPaused) {
                    resumeAnimation();
                }
            }, 300);
        }
        
        function navigateRight() {
            if (!isAnimating) return;
            
            const wasPaused = isPaused;
            pauseAnimation();
            
            position -= totalWidth / images.length;
            track.style.transition = 'transform 0.3s ease';
            track.style.transform = `translateX(${position}px)`;
            
            setTimeout(() => {
                track.style.transition = 'none';
                if (!wasPaused) {
                    resumeAnimation();
                }
            }, 300);
        }
        
        // Setup do carrossel
        function setupCarousel() {
            duplicateImagesForInfiniteLoop();
            adjustSpeedForResponsiveness();
            
            totalWidth = track.scrollWidth / 2;
            
            // Botões de navegação
            if (leftBtn) {
                leftBtn.addEventListener('click', navigateLeft);
            }
            
            if (rightBtn) {
                rightBtn.addEventListener('click', navigateRight);
            }
            
            // Pausa ao passar mouse
            if (CAROUSEL_CONFIG.pauseOnHover) {
                track.addEventListener('mouseenter', pauseAnimation);
                track.addEventListener('mouseleave', resumeAnimation);
            }
            
            // Ajusta velocidade ao redimensionar
            if (CAROUSEL_CONFIG.responsiveSpeed) {
                window.addEventListener('resize', function() {
                    adjustSpeedForResponsiveness();
                    totalWidth = track.scrollWidth / 2;
                });
            }
            
            // Pausa quando página não está visível
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    pauseAnimation();
                } else if (isAnimating) {
                    resumeAnimation();
                }
            });
            
            // Navegação por teclado
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft') {
                    navigateLeft();
                } else if (e.key === 'ArrowRight') {
                    navigateRight();
                }
            });
            
            // Inicia animação
            if (CAROUSEL_CONFIG.autoPlay) {
                startAnimation();
            }
        }
        
        // Inicializar quando imagens carregarem
        function waitForImages() {
            const allImages = document.querySelectorAll('.carousel-track img');
            let loadedCount = 0;
            
            if (allImages.length === 0) {
                setupCarousel();
                return;
            }
            
            allImages.forEach(img => {
                if (img.complete) {
                    loadedCount++;
                } else {
                    img.addEventListener('load', function() {
                        loadedCount++;
                        if (loadedCount === allImages.length) {
                            setupCarousel();
                        }
                    });
                    
                    img.addEventListener('error', function() {
                        loadedCount++;
                        if (loadedCount === allImages.length) {
                            setupCarousel();
                        }
                    });
                }
            });
            
            if (loadedCount === allImages.length) {
                setupCarousel();
            }
            
            // Timeout de segurança
            setTimeout(function() {
                if (!animationId) {
                    setupCarousel();
                }
            }, 2000);
        }
        
        waitForImages();
    }
    
    // ========== ESTILOS DINÂMICOS ==========
    
    function addDynamicStyles() {
        // Verifica se os estilos já foram adicionados
        if (document.getElementById('dynamic-carousel-styles')) {
            return;
        }
        
        const style = document.createElement('style');
        style.id = 'dynamic-carousel-styles';
        style.textContent = `
            /* Estilos para o carrossel */
            .carousel-track {
                display: flex;
                will-change: transform;
            }
            
            .carousel-track img {
                flex-shrink: 0;
                width: 100vw;
                height: 100vh;
                object-fit: cover;
            }
            
            .carousel-clone {
                opacity: 1;
            }
            
            /* Botões do carrossel */
            .carousel-btn {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                background-color: rgba(0, 0, 0, 0.5);
                border: none;
                color: white;
                font-size: 2rem;
                padding: 10px 15px;
                cursor: pointer;
                z-index: 100;
                border-radius: 5px;
                transition: all 0.3s ease;
            }
            
            .carousel-btn:hover {
                background-color: rgba(0, 0, 0, 0.8);
                transform: translateY(-50%) scale(1.1);
            }
            
            .carousel-btn:active {
                transform: translateY(-50%) scale(0.95);
            }
            
            .carousel-btn.left { left: 20px; }
            .carousel-btn.right { right: 20px; }
            
            /* Responsividade dos botões */
            @media (max-width: 768px) {
                .carousel-btn {
                    font-size: 1.5rem;
                    padding: 8px 12px;
                }
                
                .carousel-btn.left { left: 10px; }
                .carousel-btn.right { right: 10px; }
            }
            
            @media (max-width: 480px) {
                .carousel-btn {
                    font-size: 1.2rem;
                    padding: 6px 10px;
                }
            }
            
            /* Estilos para o dropdown de idiomas */
            .language-selector.active .language-dropdown {
                opacity: 1 !important;
                visibility: visible !important;
                transform: translateY(0) !important;
                display: block !important;
            }
            
            .language-dropdown {
                opacity: 0 !important;
                visibility: hidden !important;
                transform: translateY(-10px) !important;
                transition: all 0.3s ease !important;
                display: none !important;
            }
            
            /* Estilos para o menu mobile */
            .mobile-menu {
                display: block !important;
                transform: translateX(100%) !important;
                transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            
            .mobile-menu.active {
                transform: translateX(0) !important;
            }
            
            .menu-overlay {
                display: none !important;
            }
            
            .menu-overlay.active {
                display: block !important;
            }
            
            /* Prevenir scroll quando menu aberto */
            body.menu-open {
                overflow: hidden !important;
            }
        `;
        
        document.head.appendChild(style);
        console.log('Estilos dinâmicos adicionados');
    }
    
    // ========== INICIALIZAÇÃO GERAL ==========
    
    // Espera um pouco para garantir que o DOM está completamente carregado
    setTimeout(function() {
        initAllFeatures();
    }, 100);
    
    // ========== DEBUG HELPER ==========
    
    // Função para debugging
    window.debugServicos = {
        toggleMenu: function() {
            if (mobileMenu) {
                toggleMobileMenu();
                return 'Menu toggled';
            }
            return 'Menu não encontrado';
        },
        
        toggleLanguage: function() {
            if (languageSelector) {
                languageSelector.classList.toggle('active');
                return 'Language dropdown toggled';
            }
            return 'Language selector não encontrado';
        },
        
        getStatus: function() {
            return {
                menuOpen: menuOpen,
                carouselAnimating: isAnimating,
                carouselPaused: isPaused,
                elements: {
                    hamburger: !!hamburger,
                    mobileMenu: !!mobileMenu,
                    languageSelector: !!languageSelector,
                    carouselTrack: !!track,
                    carouselImages: images ? images.length : 0
                }
            };
        }
    };
    
    console.log('Debug helper disponível: window.debugServicos');
});

// ========== POLYFILL para requestAnimationFrame ==========
(function() {
    if (window.requestAnimationFrame) return;
    
    let lastTime = 0;
    const vendors = ['ms', 'moz', 'webkit', 'o'];
    
    for(let x = 0; x < vendors.length && !window.requestAnimationFrame; ++x) {
        window.requestAnimationFrame = window[vendors[x]+'RequestAnimationFrame'];
        window.cancelAnimationFrame = window[vendors[x]+'CancelAnimationFrame'] || 
                                      window[vendors[x]+'CancelRequestAnimationFrame'];
    }
    
    if (!window.requestAnimationFrame) {
        window.requestAnimationFrame = function(callback) {
            const currTime = new Date().getTime();
            const timeToCall = Math.max(0, 16 - (currTime - lastTime));
            const id = window.setTimeout(function() { 
                callback(currTime + timeToCall); 
            }, timeToCall);
            lastTime = currTime + timeToCall;
            return id;
        };
    }
    
    if (!window.cancelAnimationFrame) {
        window.cancelAnimationFrame = function(id) {
            clearTimeout(id);
        };
    }
})();