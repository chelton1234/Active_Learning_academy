// Sistema de gestão da ficha de inscrição
document.addEventListener('DOMContentLoaded', function() {
  let currentLang = 'pt';
  const langSelector = document.getElementById('languageSelector');
  const currentFlag = document.getElementById('currentFlag');
  const currentLangText = document.getElementById('currentLang');
  
  const pacoteRadios = document.querySelectorAll('input[name="pacote"]');
  const diasContainer = document.getElementById('dias-semana-container');
  const diasCheckboxes = document.querySelectorAll('input[name="dias[]"]');
  const weekendDays = document.querySelectorAll('.weekend-day');
  const infoDias = document.getElementById('info-dias');
  const horariosContainer = document.getElementById('horarios-container');
  const horariosDiasDiv = document.getElementById('horarios-dias');
  const horariosJsonInput = document.getElementById('horarios_json');
  const btnGuardar = document.getElementById('btnGuardar');
  const btnPagamento = document.getElementById('btnPagamento');
  
  let maxDias = 0;
  let permiteFinsemana = false;
  const horariosDisponiveis = [
    { value: '8:00-9:30', label: '8:00 - 9:30' },
    { value: '10:00-11:30', label: '10:00 - 11:30' },
    { value: '12:00-13:30', label: '12:00 - 13:30' },
    { value: '14:00-15:30', label: '14:00 - 15:30' },
    { value: '16:00-17:30', label: '16:00 - 17:30' },
    { value: '18:00-19:30', label: '18:00 - 19:30' }
  ];
  
  let horarioSelecionado = null; // Único horário para todos os dias

  // ========== GERENCIAMENTO DE IDIOMA ==========
  // Abrir/fechar dropdown de idioma
  langSelector.querySelector('.language-btn').addEventListener('click', function(e) {
    e.stopPropagation();
    langSelector.classList.toggle('active');
  });
  
  // Fechar dropdown ao clicar fora
  document.addEventListener('click', function() {
    langSelector.classList.remove('active');
  });
  
  // Trocar idioma
  document.querySelectorAll('.language-item').forEach(option => {
    option.addEventListener('click', function(e) {
      e.preventDefault();
      const newLang = this.getAttribute('data-lang');
      if (newLang !== currentLang) {
        currentLang = newLang;
        changeLanguage(newLang);
        
        // Atualizar botão do idioma
        const flag = newLang === 'pt' ? '🇵🇹' : '🇬🇧';
        const langCode = newLang === 'pt' ? 'PT' : 'EN';
        currentFlag.textContent = flag;
        currentLangText.textContent = langCode;
        
        // Atualizar opções ativas
        document.querySelectorAll('.language-item').forEach(opt => {
          opt.classList.remove('active');
        });
        this.classList.add('active');
        
        // Fechar dropdown
        langSelector.classList.remove('active');
      }
    });
  });
  
  // Função para mudar idioma
  function changeLanguage(lang) {
    // Atualizar todos os textos com atributos data-pt/data-en
    document.querySelectorAll('[data-pt]').forEach(element => {
      const text = element.getAttribute(`data-${lang}`);
      if (text) {
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
          const placeholder = element.getAttribute(`data-placeholder-${lang}`);
          if (placeholder) element.placeholder = placeholder;
        } else if (element.tagName === 'OPTION') {
          element.textContent = text;
        } else {
          element.textContent = text;
        }
      }
    });
    
    // Atualizar mensagem de dias
    updateDiasMessages(lang);
    
    // Atualizar texto dos horários
    updateHorariosText(lang);
    
    // Salvar preferência
    localStorage.setItem('preferredLanguage', lang);
  }
  
  // Atualizar mensagens dos dias
  function updateDiasMessages(lang) {
    if (maxDias > 0 && infoDias.textContent) {
      const checkedDias = document.querySelectorAll('input[name="dias[]"]:checked').length;
      
      if (checkedDias >= maxDias) {
        infoDias.textContent = lang === 'pt' 
          ? `✓ ${maxDias} dias selecionados` 
          : `✓ ${maxDias} days selected`;
      } else {
        infoDias.textContent = lang === 'pt'
          ? `Selecione mais ${maxDias - checkedDias} dia(s)`
          : `Select ${maxDias - checkedDias} more day(s)`;
      }
    }
  }
  
  // Atualizar texto dos horários
  function updateHorariosText(lang) {
    const horarioLabel = lang === 'pt' 
      ? '⏰ Escolha o horário para todas as aulas:' 
      : '⏰ Choose time slot for all classes:';
    
    document.querySelector('#horarios-container label').textContent = horarioLabel;
  }
  
  // Carregar idioma salvo
  const savedLang = localStorage.getItem('preferredLanguage');
  if (savedLang && (savedLang === 'pt' || savedLang === 'en')) {
    currentLang = savedLang;
    const flag = savedLang === 'pt' ? '🇵🇹' : '🇬🇧';
    const langCode = savedLang === 'pt' ? 'PT' : 'EN';
    currentFlag.textContent = flag;
    currentLangText.textContent = langCode;
    
    document.querySelectorAll('.language-item').forEach(opt => {
      opt.classList.remove('active');
      if (opt.getAttribute('data-lang') === savedLang) {
        opt.classList.add('active');
      }
    });
    
    changeLanguage(savedLang);
  }
  
  // ========== SISTEMA DE PACOTES E DIAS ==========
  // Selecionar pacote
  pacoteRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      maxDias = parseInt(this.getAttribute('data-dias'));
      permiteFinsemana = this.getAttribute('data-finsemana') === 'true';
      
      // Mostrar ou esconder fins de semana
      weekendDays.forEach(day => {
        day.style.display = permiteFinsemana ? 'block' : 'none';
      });
      
      diasContainer.style.display = 'block';
      horariosContainer.style.display = 'none'; // Esconder horários inicialmente
      
      infoDias.textContent = currentLang === 'pt' 
        ? `Selecione ${maxDias} dia(s) da semana${permiteFinsemana ? ' (incluindo fins de semana)' : ''}` 
        : `Select ${maxDias} day(s) of the week${permiteFinsemana ? ' (including weekends)' : ''}`;
      
      // Limpar seleções anteriores
      diasCheckboxes.forEach(cb => {
        cb.checked = false;
        cb.disabled = false;
      });
      
      // Limpar horário selecionado
      horarioSelecionado = null;
      limparSelecaoHorarios();
      
      updateDiasSelection();
      updateTotal();
    });
  });
  
  // Controlar seleção de dias
  diasCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      updateDiasSelection();
      
      // Mostrar horários quando todos os dias estiverem selecionados
      const checkedDias = document.querySelectorAll('input[name="dias[]"]:checked');
      if (checkedDias.length === maxDias) {
        criarSelecaoHorarios();
        horariosContainer.style.display = 'block';
      } else {
        horariosContainer.style.display = 'none';
        horarioSelecionado = null; // Resetar horário se dias mudarem
      }
    });
  });
  
  function updateDiasSelection() {
    const checkedDias = document.querySelectorAll('input[name="dias[]"]:checked');
    
    // Se atingiu o limite, desabilitar os não selecionados
    if (checkedDias.length >= maxDias) {
      diasCheckboxes.forEach(cb => {
        if (!cb.checked) {
          cb.disabled = true;
        }
      });
      infoDias.textContent = currentLang === 'pt'
        ? `✓ ${maxDias} dias selecionados`
        : `✓ ${maxDias} days selected`;
      infoDias.className = 'days-info selecionado';
    } else {
      // Habilitar todos se não atingiu o limite
      diasCheckboxes.forEach(cb => {
        cb.disabled = false;
      });
      infoDias.textContent = currentLang === 'pt'
        ? `Selecione mais ${maxDias - checkedDias.length} dia(s)`
        : `Select ${maxDias - checkedDias.length} more day(s)`;
      infoDias.className = 'days-info';
    }
    
    // Habilitar/desabilitar botão Guardar
    const pacoteSelecionado = document.querySelector('input[name="pacote"]:checked');
    const todosCamposPreenchidos = pacoteSelecionado && 
                                   checkedDias.length === maxDias && 
                                   horarioSelecionado !== null;
    
    btnGuardar.disabled = !todosCamposPreenchidos;
  }
  
  // ========== SISTEMA DE HORÁRIOS ==========
  function criarSelecaoHorarios() {
    horariosDiasDiv.innerHTML = '';
    
    // Criar container para seleção única de horário
    const container = document.createElement('div');
    container.className = 'horario-unico-container';
    
    // Aviso sobre horário único
    const aviso = document.createElement('p');
    aviso.className = 'horario-aviso';
    aviso.textContent = currentLang === 'pt' 
      ? 'Este horário será aplicado a todos os dias selecionados.'
      : 'This time slot will be applied to all selected days.';
    aviso.style.color = '#666';
    aviso.style.marginBottom = '15px';
    aviso.style.fontSize = '14px';
    
    container.appendChild(aviso);
    
    // Container para opções de horário
    const horariosGrid = document.createElement('div');
    horariosGrid.className = 'horarios-grid';
    
    // Criar opções de horário
    horariosDisponiveis.forEach(horario => {
      const optionId = `horario_unico_${horario.value}`;
      
      const label = document.createElement('label');
      label.className = 'horario-option';
      label.htmlFor = optionId;
      
      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'horario_unico';
      input.id = optionId;
      input.value = horario.value;
      
      // Marcar se já foi selecionado antes
      if (horarioSelecionado === horario.value) {
        input.checked = true;
      }
      
      // Evento para salvar seleção
      input.addEventListener('change', function() {
        horarioSelecionado = this.value;
        salvarHorariosJSON();
        updateDiasSelection(); // Atualizar estado do botão Guardar
      });
      
      const span = document.createElement('span');
      span.className = 'horario-label';
      span.textContent = horario.label;
      
      label.appendChild(input);
      label.appendChild(span);
      horariosGrid.appendChild(label);
    });
    
    container.appendChild(horariosGrid);
    horariosDiasDiv.appendChild(container);
  }
  
  function limparSelecaoHorarios() {
    // Limpar todos os radio buttons de horário
    const radios = document.querySelectorAll('input[name="horario_unico"]');
    radios.forEach(radio => {
      radio.checked = false;
    });
    
    // Limpar JSON
    horariosJsonInput.value = '';
  }
  
  function salvarHorariosJSON() {
    const checkedDias = document.querySelectorAll('input[name="dias[]"]:checked');
    
    if (checkedDias.length === maxDias && horarioSelecionado !== null) {
      // Criar objeto com horário para cada dia selecionado
      const horariosParaSalvar = {};
      checkedDias.forEach(checkbox => {
        const dia = checkbox.value;
        horariosParaSalvar[dia] = horarioSelecionado;
      });
      
      horariosJsonInput.value = JSON.stringify(horariosParaSalvar);
    } else {
      horariosJsonInput.value = '';
    }
  }
  
  // ========== SISTEMA DE PAGAMENTO ==========
  // Atualizar total do carrinho
  function updateTotal() {
    const pacoteSelecionado = document.querySelector('input[name="pacote"]:checked');
    const domicilio = document.getElementById('domicilio');
    
    let total = 0;
    
    if (pacoteSelecionado) {
      total = parseInt(pacoteSelecionado.getAttribute('data-preco'));
      
      if (domicilio.checked) {
        total += 1000;
      }
    }
    
    // Atualizar carrinho
    document.getElementById('total').textContent = total + ' MT';
    document.getElementById('valor_total').value = total;
  }
  
  // Eventos para regime de aulas
  document.getElementById('domicilio').addEventListener('change', updateTotal);
  document.getElementById('presencial').addEventListener('change', updateTotal);
  document.getElementById('online').addEventListener('change', updateTotal);
  
  // ========== FUNÇÃO PARA SALVAR FICHA VIA AJAX ==========
  function salvarFichaParaPagamento() {
    // Validar todos os campos obrigatórios
    const camposObrigatorios = [
        { id: 'nome', nome: 'Nome Completo' },
        { id: 'classe', nome: 'Classe' },
        { id: 'localizacao', nome: 'Localização' },
        { id: 'contacto_encarregado', nome: 'Contacto do Encarregado' },
        { id: 'escola', nome: 'Escola' },
        { id: 'dificuldade', nome: 'Dificuldades' }
    ];
    
    for (const campo of camposObrigatorios) {
        const elemento = document.getElementById(campo.id);
        if (!elemento || !elemento.value.trim()) {
            alert(currentLang === 'pt'
                ? `Por favor, preencha o campo: ${campo.nome}`
                : `Please fill in the field: ${campo.id}`);
            return false;
        }
    }
    
    // Verificar sexo
    const sexoSelecionado = document.querySelector('input[name="sexo"]:checked');
    if (!sexoSelecionado) {
        alert(currentLang === 'pt'
            ? 'Por favor, selecione o sexo.'
            : 'Please select gender.');
        return false;
    }
    
    // Verificar pacote
    const pacoteSelecionado = document.querySelector('input[name="pacote"]:checked');
    if (!pacoteSelecionado) {
        alert(currentLang === 'pt'
            ? 'Por favor, selecione um pacote.'
            : 'Please select a package.');
        return false;
    }
    
    // Verificar dias
    const diasSelecionados = document.querySelectorAll('input[name="dias[]"]:checked');
    if (diasSelecionados.length !== maxDias) {
        alert(currentLang === 'pt'
            ? `Por favor, selecione ${maxDias} dia(s) para o pacote.`
            : `Please select ${maxDias} day(s) for the package.`);
        return false;
    }
    
    // Verificar horário
    if (horarioSelecionado === null) {
        alert(currentLang === 'pt'
            ? 'Por favor, selecione um horário para as aulas.'
            : 'Please select a time slot for the classes.');
        return false;
    }
    
    // Salvar horários antes de enviar
    salvarHorariosJSON();
    if (!horariosJsonInput.value) {
        alert(currentLang === 'pt'
            ? 'Erro ao processar horários. Por favor, verifique suas seleções.'
            : 'Error processing time slots. Please check your selections.');
        return false;
    }
    
    // Criar FormData com todos os dados
    const formData = new FormData(document.getElementById('formFicha'));
    
    // Mostrar loading
    btnPagamento.disabled = true;
    btnPagamento.innerHTML = currentLang === 'pt' 
        ? '⏳ Processando...' 
        : '⏳ Processing...';
    
    // Enviar via AJAX
    return fetch('ficha_processar.php?pagamento=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(ficha_id => {
        if (ficha_id && !isNaN(parseInt(ficha_id))) {
            return ficha_id;
        } else {
            throw new Error('Erro ao salvar ficha');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert(currentLang === 'pt'
            ? 'Erro ao processar o pagamento. Por favor, tente novamente.'
            : 'Error processing payment. Please try again.');
        return null;
    })
    .finally(() => {
        // Restaurar botão
        btnPagamento.disabled = false;
        btnPagamento.innerHTML = currentLang === 'pt' 
            ? '💳 Efetuar Pagamento' 
            : '💳 Make Payment';
    });
  }
  
  // Evento para botão de pagamento
  btnPagamento.addEventListener('click', function() {
    const total = document.getElementById('valor_total').value;
    
    if (total === "0" || total === "") {
        alert(currentLang === 'pt' 
            ? 'Por favor, selecione um pacote primeiro.' 
            : 'Please select a package first.');
        return;
    }
    
    // Salvar ficha e redirecionar para pagamento
    salvarFichaParaPagamento().then(ficha_id => {
        if (ficha_id) {
            window.location.href = `pagamento_form.php?ficha_id=${ficha_id}&total=${total}`;
        }
    });
  });
  
  // Evento para envio do formulário (Guardar Inscrição)
  document.getElementById('formFicha').addEventListener('submit', function(e) {
    // Validar se o pacote e dias estão corretos
    const pacoteSelecionado = document.querySelector('input[name="pacote"]:checked');
    const diasSelecionados = document.querySelectorAll('input[name="dias[]"]:checked');
    
    if (!pacoteSelecionado || diasSelecionados.length !== maxDias) {
      e.preventDefault();
      alert(currentLang === 'pt'
        ? 'Por favor, selecione um pacote e os dias correspondentes.'
        : 'Please select a package and the corresponding days.');
      return;
    }
    
    // Verificar horário
    if (horarioSelecionado === null) {
      e.preventDefault();
      alert(currentLang === 'pt'
        ? 'Por favor, selecione um horário para as aulas.'
        : 'Please select a time slot for the classes.');
      return;
    }
    
    // Salvar horários antes de enviar
    salvarHorariosJSON();
    
    if (!horariosJsonInput.value) {
      e.preventDefault();
      alert(currentLang === 'pt'
        ? 'Erro ao processar horários. Por favor, verifique suas seleções.'
        : 'Error processing time slots. Please check your selections.');
      return;
    }
    
    // Confirmar informações
    const confirmMsg = currentLang === 'pt'
      ? `Confirma a inscrição?\nPacote: ${pacoteSelecionado.value}\nDias: ${Array.from(diasSelecionados).map(d => d.nextElementSibling.textContent).join(', ')}\nHorário: ${horarioSelecionado}\nTotal: ${document.getElementById('valor_total').value} MT`
      : `Confirm registration?\nPackage: ${pacoteSelecionado.value}\nDays: ${Array.from(diasSelecionados).map(d => d.nextElementSibling.textContent).join(', ')}\nTime: ${horarioSelecionado}\nTotal: ${document.getElementById('valor_total').value} MT`;
    
    if (!confirm(confirmMsg)) {
      e.preventDefault();
    }
  });
  
  // Inicializar botão Guardar como desabilitado
  btnGuardar.disabled = true;
  
  // Se já houver pacote selecionado (em caso de refresh)
  const pacoteInicial = document.querySelector('input[name="pacote"]:checked');
  if (pacoteInicial) {
    maxDias = parseInt(pacoteInicial.getAttribute('data-dias'));
    permiteFinsemana = pacoteInicial.getAttribute('data-finsemana') === 'true';
    
    // Mostrar fins de semana se aplicável
    weekendDays.forEach(day => {
      day.style.display = permiteFinsemana ? 'block' : 'none';
    });
    
    diasContainer.style.display = 'block';
    updateDiasSelection();
    
    // Verificar se já tem dias selecionados para mostrar horários
    const checkedDias = document.querySelectorAll('input[name="dias[]"]:checked');
    if (checkedDias.length === maxDias) {
      criarSelecaoHorarios();
      horariosContainer.style.display = 'block';
    }
  }
});