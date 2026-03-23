/**
 * FichaAluno.js - Versão Simplificada e Corrigida
 * Gerencia a validação e envio da ficha de inscrição
 */

// ===== VARIÁVEIS GLOBAIS =====
let idiomaAtual = 'pt';
let diasSelecionados = [];
let horarios = {};
let precoBase = 0;
let valorAdicionalDomicilio = 1000;

// ===== CONSTANTES =====
const DIAS_SEMANA = {
    'segunda': { pt: 'Segunda', en: 'Monday' },
    'terca': { pt: 'Terça', en: 'Tuesday' },
    'quarta': { pt: 'Quarta', en: 'Wednesday' },
    'quinta': { pt: 'Quinta', en: 'Thursday' },
    'sexta': { pt: 'Sexta', en: 'Friday' },
    'sabado': { pt: 'Sábado', en: 'Saturday' },
    'domingo': { pt: 'Domingo', en: 'Sunday' }
};

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Ficha do Aluno inicializada');
    
    inicializarEventos();
    inicializarIdioma();
    inicializarBotoes();
    
    // Verificar parâmetros da URL (para edição)
    verificarEdicao();
});

// ===== INICIALIZAR EVENTOS DE VALIDAÇÃO =====
function inicializarEventos() {
    // === CAMPOS DE TEXTO - VALIDAÇÃO EM TEMPO REAL ===
    const camposTexto = ['nome', 'classe', 'localizacao', 'contacto_encarregado', 'escola', 'dificuldade'];
    camposTexto.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.addEventListener('input', function() {
                console.log(`📝 Campo ${id} alterado:`, this.value);
                validarFormulario();
            });
            campo.addEventListener('change', function() {
                console.log(`📝 Campo ${id} alterado (change):`, this.value);
                validarFormulario();
            });
        }
    });
    
    // === SELECT NIVEL ===
    const nivel = document.getElementById('nivel');
    if (nivel) {
        nivel.addEventListener('change', function() {
            console.log('📚 Nível selecionado:', this.value);
            validarFormulario();
        });
    }
    
    // === RADIO SEXO ===
    document.querySelectorAll('input[name="sexo"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('👤 Sexo selecionado:', this.value);
            validarFormulario();
        });
    });
    
    // === CHECKBOXES REGIME ===
    ['presencial', 'online', 'domicilio'].forEach(id => {
        const checkbox = document.getElementById(id);
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                console.log(`🏫 Regime ${id}:`, this.checked);
                validarFormulario();
                atualizarTotal();
            });
        }
    });
    
    // === PACOTES ===
    document.querySelectorAll('input[name="pacote"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('📦 Pacote selecionado:', this.value);
            
            const permiteFimSemana = this.dataset.finsemana === 'true';
            const dias = parseInt(this.dataset.dias) || 0;
            precoBase = parseInt(this.dataset.preco) || 0;
            
            // Mostrar container de dias
            const container = document.getElementById('dias-semana-container');
            if (container) container.style.display = 'block';
            
            // Mostrar/esconder fins de semana
            document.querySelectorAll('.weekend-day').forEach(day => {
                day.style.display = permiteFimSemana ? 'block' : 'none';
            });
            
            // Resetar seleções
            resetarSelecoes();
            
            // Atualizar mensagem
            const infoDiv = document.getElementById('info-dias');
            if (infoDiv) {
                infoDiv.innerHTML = `Selecione exatamente <strong>${dias}</strong> dias da semana.`;
            }
            
            // Atualizar total e validar
            atualizarTotal();
            validarFormulario();
        });
    });
    
    // === DIAS DA SEMANA ===
    document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dia = this.value;
            const pacote = document.querySelector('input[name="pacote"]:checked');
            
            if (!pacote) {
                alert('Selecione um pacote primeiro.');
                this.checked = false;
                return;
            }
            
            const maxDias = parseInt(pacote.dataset.dias) || 0;
            
            if (this.checked) {
                if (!diasSelecionados.includes(dia)) {
                    diasSelecionados.push(dia);
                }
            } else {
                diasSelecionados = diasSelecionados.filter(d => d !== dia);
                delete horarios[dia];
            }
            
            // Validar limite
            if (diasSelecionados.length > maxDias) {
                alert(`Você só pode selecionar ${maxDias} dias.`);
                this.checked = false;
                diasSelecionados = diasSelecionados.filter(d => d !== dia);
                return;
            }
            
            console.log('📅 Dias selecionados:', diasSelecionados);
            
            // Mostrar/esconder horários
            const horariosContainer = document.getElementById('horarios-container');
            if (diasSelecionados.length === maxDias) {
                horariosContainer.style.display = 'block';
                gerarCamposHorario();
            } else {
                horariosContainer.style.display = 'none';
            }
            
            validarFormulario();
        });
    });
}

// ===== GERAR CAMPOS DE HORÁRIO =====
function gerarCamposHorario() {
    const container = document.getElementById('horarios-dias');
    if (!container) return;
    
    let html = '';
    const diasDisplay = {
        'segunda': 'Segunda-feira', 'terca': 'Terça-feira', 'quarta': 'Quarta-feira',
        'quinta': 'Quinta-feira', 'sexta': 'Sexta-feira', 'sabado': 'Sábado', 'domingo': 'Domingo'
    };
    
    diasSelecionados.forEach(dia => {
        html += `
            <div class="horario-item" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">${diasDisplay[dia]}:</label>
                <select class="horario-select" data-dia="${dia}" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:5px;" onchange="salvarHorario('${dia}', this.value)">
                    <option value="">Selecione um horário</option>
                    <option value="07:00">07:00 - 08:30</option>
                    <option value="08:30">08:30 - 10:00</option>
                    <option value="10:00">10:00 - 11:30</option>
                    <option value="11:30">11:30 - 13:00</option>
                    <option value="13:00">13:00 - 14:30</option>
                    <option value="14:30">14:30 - 16:00</option>
                    <option value="16:00">16:00 - 17:30</option>
                    <option value="17:30">17:30 - 19:00</option>
                </select>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ===== SALVAR HORÁRIO =====
window.salvarHorario = function(dia, valor) {
    horarios[dia] = valor;
    console.log('⏰ Horário salvo:', dia, valor);
    validarFormulario();
};

// ===== RESETAR SELEÇÕES =====
function resetarSelecoes() {
    document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    diasSelecionados = [];
    horarios = {};
    
    const horariosContainer = document.getElementById('horarios-container');
    if (horariosContainer) horariosContainer.style.display = 'none';
    
    const horariosDiv = document.getElementById('horarios-dias');
    if (horariosDiv) horariosDiv.innerHTML = '';
}

// ===== FUNÇÃO PRINCIPAL DE VALIDAÇÃO =====
function validarFormulario() {
    console.log('🔍 Validando formulário...');
    
    // Obter valores
    const nome = document.getElementById('nome')?.value?.trim() || '';
    const classe = document.getElementById('classe')?.value?.trim() || '';
    const sexo = document.querySelector('input[name="sexo"]:checked');
    const localizacao = document.getElementById('localizacao')?.value?.trim() || '';
    const contacto = document.getElementById('contacto_encarregado')?.value?.trim() || '';
    const escola = document.getElementById('escola')?.value?.trim() || '';
    const nivel = document.getElementById('nivel')?.value;
    const dificuldade = document.getElementById('dificuldade')?.value?.trim() || '';
    const pacote = document.querySelector('input[name="pacote"]:checked');
    
    // Regimes
    const presencial = document.getElementById('presencial')?.checked;
    const online = document.getElementById('online')?.checked;
    const domicilio = document.getElementById('domicilio')?.checked;
    const regimeSelecionado = presencial || online || domicilio;
    
    // Validar dias e horários
    let diasValidos = false;
    let horariosValidos = false;
    
    if (pacote) {
        const diasNecessarios = parseInt(pacote.dataset.dias) || 0;
        diasValidos = diasSelecionados.length === diasNecessarios;
        
        if (diasValidos) {
            horariosValidos = diasSelecionados.every(d => horarios[d] && horarios[d].trim() !== '');
        }
    }
    
    // Verificar se todos os campos estão preenchidos
    const formularioValido = 
        nome !== '' &&
        classe !== '' &&
        sexo !== null &&
        localizacao !== '' &&
        contacto !== '' &&
        escola !== '' &&
        nivel !== '' &&
        nivel !== null &&
        dificuldade !== '' &&
        pacote !== null &&
        regimeSelecionado &&
        diasValidos &&
        horariosValidos;
    
    // Log detalhado
    console.log('📊 Validação:', {
        'nome': nome ? '✅' : '❌',
        'classe': classe ? '✅' : '❌',
        'sexo': sexo ? '✅' : '❌',
        'localizacao': localizacao ? '✅' : '❌',
        'contacto': contacto ? '✅' : '❌',
        'escola': escola ? '✅' : '❌',
        'nivel': nivel ? '✅' : '❌',
        'dificuldade': dificuldade ? '✅' : '❌',
        'pacote': pacote ? '✅' : '❌',
        'regime': regimeSelecionado ? '✅' : '❌',
        'dias': diasValidos ? `✅ (${diasSelecionados.length}/${pacote?.dataset.dias})` : '❌',
        'horarios': horariosValidos ? '✅' : '❌',
        'RESULTADO': formularioValido ? '✅ HABILITADO' : '❌ DESABILITADO'
    });
    
    // Atualizar botões
    const btnPagamento = document.getElementById('btnPagamento');
    const btnGuardar = document.getElementById('btnGuardar');
    
    if (btnPagamento) {
        btnPagamento.disabled = !formularioValido;
        btnPagamento.style.opacity = formularioValido ? '1' : '0.5';
        btnPagamento.style.cursor = formularioValido ? 'pointer' : 'not-allowed';
    }
    
    if (btnGuardar) {
        btnGuardar.disabled = !formularioValido;
        btnGuardar.style.opacity = formularioValido ? '1' : '0.5';
        btnGuardar.style.cursor = formularioValido ? 'pointer' : 'not-allowed';
    }
    
    return formularioValido;
}

// ===== ATUALIZAR TOTAL =====
function atualizarTotal() {
    let total = precoBase;
    
    if (document.getElementById('domicilio')?.checked) {
        total += valorAdicionalDomicilio;
    }
    
    const totalElement = document.getElementById('totalDisplay') || document.getElementById('total');
    if (totalElement) {
        totalElement.textContent = total.toLocaleString('pt-PT') + ' MT';
    }
    
    const valorTotalInput = document.getElementById('valor_total');
    if (valorTotalInput) {
        valorTotalInput.value = total;
    }
    
    return total;
}

// ===== PREPARAR DADOS PARA ENVIO =====
function prepararDados() {
    return {
        nome: document.getElementById('nome')?.value || '',
        classe: document.getElementById('classe')?.value || '',
        sexo: document.querySelector('input[name="sexo"]:checked')?.value || '',
        localizacao: document.getElementById('localizacao')?.value || '',
        contacto_encarregado: document.getElementById('contacto_encarregado')?.value || '',
        escola: document.getElementById('escola')?.value || '',
        nivel: document.getElementById('nivel')?.value || '',
        pacote: document.querySelector('input[name="pacote"]:checked')?.value || '',
        dias: diasSelecionados,
        horarios: horarios,
        presencial: document.getElementById('presencial')?.checked || false,
        online: document.getElementById('online')?.checked || false,
        domicilio: document.getElementById('domicilio')?.checked || false,
        dificuldade: document.getElementById('dificuldade')?.value || '',
        valor_total: atualizarTotal()
    };
}

// ===== FUNÇÕES DE ENVIO =====
function mostrarLoading(mensagem) {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;justify-content:center;align-items:center;z-index:9999;';
        overlay.innerHTML = `
            <div style="background:white;padding:30px;border-radius:10px;text-align:center;">
                <div style="border:5px solid #f3f3f3;border-top:5px solid #3498db;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto 20px;"></div>
                <p id="loadingMessage" style="margin:0;">${mensagem}</p>
            </div>
        `;
        document.body.appendChild(overlay);
        
        const style = document.createElement('style');
        style.textContent = '@keyframes spin {0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}';
        document.head.appendChild(style);
    }
    document.getElementById('loadingMessage').textContent = mensagem;
    overlay.style.display = 'flex';
}

function esconderLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
}

function processarPagamento() {
    if (!validarFormulario()) {
        alert('Preencha todos os campos obrigatórios primeiro.');
        return;
    }
    
    mostrarLoading('Salvando e redirecionando para pagamento...');
    
    fetch('ficha_processar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'salvar', dados: prepararDados() })
    })
    .then(res => res.json())
    .then(data => {
        esconderLoading();
        if (data.sucesso && data.ficha_id) {
            window.location.href = `pagamento_form.php?ficha_id=${data.ficha_id}`;
        } else {
            alert('Erro: ' + (data.mensagem || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        esconderLoading();
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor');
    });
}

function guardarFicha() {
    if (!validarFormulario()) {
        alert('Preencha todos os campos obrigatórios primeiro.');
        return;
    }
    
    mostrarLoading('Guardando ficha...');
    
    fetch('ficha_processar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'salvar', dados: prepararDados() })
    })
    .then(res => res.json())
    .then(data => {
        esconderLoading();
        if (data.sucesso) {
            alert('✅ Ficha guardada com sucesso!');
            window.location.href = 'dashboard.php';
        } else {
            alert('Erro: ' + (data.mensagem || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        esconderLoading();
        console.error('Erro:', error);
        alert('Erro ao conectar com o servidor');
    });
}

// ===== INICIALIZAR BOTÕES =====
function inicializarBotoes() {
    const btnPagamento = document.getElementById('btnPagamento');
    const btnGuardar = document.getElementById('btnGuardar');
    
    if (btnPagamento) {
        btnPagamento.addEventListener('click', processarPagamento);
        btnPagamento.disabled = true;
        btnPagamento.style.opacity = '0.5';
    }
    
    if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarFicha);
        btnGuardar.disabled = true;
        btnGuardar.style.opacity = '0.5';
    }
    
    // Validar inicialmente
    setTimeout(validarFormulario, 500);
}

// ===== IDIOMA =====
function inicializarIdioma() {
    const languageItems = document.querySelectorAll('.language-item');
    const currentFlag = document.getElementById('currentFlag');
    const currentLang = document.getElementById('currentLang');
    
    languageItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const lang = this.dataset.lang;
            idiomaAtual = lang;
            
            currentFlag.textContent = lang === 'pt' ? '🇵🇹' : '🇬🇧';
            currentLang.textContent = lang.toUpperCase();
            
            languageItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            traduzirPagina();
        });
    });
    
    traduzirPagina();
}

function traduzirPagina() {
    document.querySelectorAll('[data-pt][data-en]').forEach(el => {
        el.textContent = el.dataset[idiomaAtual];
    });
    
    document.querySelectorAll('[data-placeholder-pt][data-placeholder-en]').forEach(el => {
        el.placeholder = el.dataset[`placeholder-${idiomaAtual}`];
    });
}

// ===== EDIÇÃO =====
function verificarEdicao() {
    const urlParams = new URLSearchParams(window.location.search);
    const fichaId = urlParams.get('id');
    if (fichaId) {
        console.log('Modo edição:', fichaId);
        // Carregar dados da ficha para edição
    }
}

// Exportar funções globais
window.salvarHorario = salvarHorario;