(function() {
  // ---------- Elementos DOM ----------
  const modal = document.getElementById('infoModal');
  const openBtn = document.getElementById('openModalBtn');
  const closeBtn = document.getElementById('closeModalBtn');
  const languageSelector = document.getElementById('languageSelector');
  const langBtn = document.querySelector('.language-btn');
  const langItems = document.querySelectorAll('.language-item');
  const currentFlagSpan = document.getElementById('currentFlag');
  const currentLangSpan = document.getElementById('currentLang');
  
  let currentLanguage = 'pt'; // pt ou en
  
  // Elementos do formulário
  const form = document.getElementById('contactForm');
  const nomeInput = document.getElementById('nome');
  const emailInput = document.getElementById('email');
  const contactoInput = document.getElementById('contacto');
  const localizacaoInput = document.getElementById('localizacao');
  const nivelSelect = document.getElementById('nivel');
  const tipoAulaRadios = document.querySelectorAll('input[name="tipoAula"]');
  const radioPackages = document.querySelectorAll('input[name="pacote"]');
  const diasCheckboxes = document.querySelectorAll('input[name="dias"]');
  const horarioSelect = document.getElementById('horario');
  const dificuldadeTextarea = document.getElementById('dificuldade');
  const totalSpan = document.getElementById('totalValor');
  const alertDiv = document.getElementById('alertMessage');
  const submitBtn = document.getElementById('submitBtn');
  
  // ---------- Obter número de dias permitido pelo pacote ----------
  function getDiasPermitidos() {
    let selectedRadio = null;
    radioPackages.forEach(radio => {
      if (radio.checked) selectedRadio = radio;
    });
    if (!selectedRadio) return 0;
    const valor = selectedRadio.value;
    if (valor === '2dias') return 2;
    if (valor === '3dias') return 3;
    if (valor === '4dias') return 4;
    return 0;
  }
  
  // ---------- Obter preço base a partir do atributo data-preco-base ----------
  function getBasePriceFromSelectedPackage() {
    let selectedRadio = null;
    radioPackages.forEach(radio => {
      if (radio.checked) selectedRadio = radio;
    });
    if (selectedRadio) {
      const basePrice = selectedRadio.getAttribute('data-preco-base');
      if (basePrice) return parseInt(basePrice, 10);
      const valor = selectedRadio.value;
      if (valor === '2dias') return 2000;
      if (valor === '3dias') return 3000;
      if (valor === '4dias') return 4000;
    }
    return 0;
  }
  
  // ---------- Verificar se é domicílio ----------
  function isDomicilio() {
    let domicilioSelected = false;
    tipoAulaRadios.forEach(radio => {
      if (radio.value === 'domicilio' && radio.checked) domicilioSelected = true;
    });
    return domicilioSelected;
  }
  
  // ---------- Actualizar total (base + 500 se domicílio) ----------
  function updateTotal() {
    const base = getBasePriceFromSelectedPackage();
    const adicional = isDomicilio() ? 500 : 0;
    const total = base + adicional;
    totalSpan.textContent = isNaN(total) ? 0 : total;
  }
  
  // ---------- Marca visual do pacote seleccionado ----------
  function updatePackageVisual() {
    document.querySelectorAll('.package-option').forEach(opt => {
      opt.classList.remove('selected');
    });
    radioPackages.forEach(radio => {
      if (radio.checked) {
        const parent = radio.closest('.package-option');
        if (parent) parent.classList.add('selected');
      }
    });
  }
  
  // ---------- Evento para cada checkbox: respeitar limite ----------
  function initDiasCheckboxes() {
    diasCheckboxes.forEach(cb => {
      cb.addEventListener('change', function(e) {
        const limite = getDiasPermitidos();
        let marcados = 0;
        diasCheckboxes.forEach(c => { if (c.checked) marcados++; });
        
        if (limite === 0) {
          this.checked = false;
          showAlert(currentLanguage === 'pt' ? 'Selecione primeiro um pacote de aulas (2, 3 ou 4 dias).' : 'First select a lesson package (2, 3 or 4 days).', true);
          return;
        }
        
        if (marcados > limite) {
          this.checked = false;
          const msg = currentLanguage === 'pt' 
            ? `Você selecionou apenas ${limite} dia(s) por semana. Não pode marcar mais dias.`
            : `You selected only ${limite} day(s) per week. You cannot select more days.`;
          showAlert(msg, true);
        }
      });
    });
  }
  
  // ---------- Quando o pacote muda, redefinir os checkboxes e o limite ----------
  function resetDiasPorPacote() {
    diasCheckboxes.forEach(cb => cb.checked = false);
    const limite = getDiasPermitidos();
    if (limite > 0) {
      const msg = currentLanguage === 'pt' 
        ? `Pode selecionar até ${limite} dia(s) da semana.`
        : `You can select up to ${limite} weekday(s).`;
      showAlert(msg, false);
      setTimeout(() => {
        if (alertDiv.style.display === 'block') alertDiv.style.display = 'none';
      }, 2000);
    }
  }
  
  // ---------- Inicializar pacotes e tipo de aula ----------
  function initPackageAndType() {
    radioPackages.forEach(radio => {
      radio.addEventListener('change', () => {
        updatePackageVisual();
        resetDiasPorPacote();
        updateTotal();
      });
      if (radio.checked) {
        updatePackageVisual();
        resetDiasPorPacote();
      }
    });
    
    tipoAulaRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        updateTotal();
      });
    });
    
    updateTotal();
  }
  
  // ---------- Clicar na div .package-option para marcar o rádio ----------
  function attachPackageClick() {
    const packageDivs = document.querySelectorAll('.package-option');
    packageDivs.forEach(div => {
      div.addEventListener('click', (e) => {
        const radio = div.querySelector('input[type="radio"]');
        if (radio && e.target !== radio && !radio.checked) {
          radio.checked = true;
          radio.dispatchEvent(new Event('change'));
        }
      });
    });
  }
  
  // ---------- Tradução ----------
  function setLanguage(lang) {
    currentLanguage = lang;
    if (lang === 'pt') {
      currentFlagSpan.textContent = '🇵🇹';
      currentLangSpan.textContent = 'PT';
    } else {
      currentFlagSpan.textContent = '🇬🇧';
      currentLangSpan.textContent = 'EN';
    }
    
    langItems.forEach(item => {
      if (item.getAttribute('data-lang') === lang) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });
    
    const translatableElements = document.querySelectorAll('[data-pt][data-en]');
    translatableElements.forEach(el => {
      const ptText = el.getAttribute('data-pt');
      const enText = el.getAttribute('data-en');
      if (ptText && enText) {
        if (el.tagName === 'OPTION') {
          el.textContent = lang === 'pt' ? ptText : enText;
        } else if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          if (el.placeholder) el.placeholder = lang === 'pt' ? ptText : enText;
        } else {
          el.textContent = lang === 'pt' ? ptText : enText;
        }
      }
    });
    
    const packageNames = document.querySelectorAll('.package-name');
    packageNames.forEach(pkg => {
      const ptVal = pkg.getAttribute('data-pt');
      const enVal = pkg.getAttribute('data-en');
      if (ptVal && enVal) pkg.textContent = lang === 'pt' ? ptVal : enVal;
    });
    
    const typeSpans = document.querySelectorAll('.type-option span');
    typeSpans.forEach(span => {
      const ptVal = span.getAttribute('data-pt');
      const enVal = span.getAttribute('data-en');
      if (ptVal && enVal) span.textContent = lang === 'pt' ? ptVal : enVal;
    });
    
    const weekdaySpans = document.querySelectorAll('.weekday-option span');
    weekdaySpans.forEach(span => {
      const ptVal = span.getAttribute('data-pt');
      const enVal = span.getAttribute('data-en');
      if (ptVal && enVal) span.textContent = lang === 'pt' ? ptVal : enVal;
    });
    
    const submitSpan = submitBtn.querySelector('span');
    if (submitSpan) {
      const ptSub = submitSpan.getAttribute('data-pt');
      const enSub = submitSpan.getAttribute('data-en');
      if (ptSub && enSub) submitSpan.textContent = lang === 'pt' ? ptSub : enSub;
    }
  }
  
  function initLanguageSwitcher() {
    langBtn.addEventListener('click', (e) => {
      e.preventDefault();
      languageSelector.classList.toggle('active');
    });
    langItems.forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const newLang = item.getAttribute('data-lang');
        if (newLang) setLanguage(newLang);
        languageSelector.classList.remove('active');
      });
    });
    document.addEventListener('click', (e) => {
      if (!languageSelector.contains(e.target)) {
        languageSelector.classList.remove('active');
      }
    });
    setLanguage('pt');
  }
  
  // ---------- Modal ----------
  function initModal() {
    openBtn.addEventListener('click', () => {
      modal.classList.add('active');
      alertDiv.style.display = 'none';
      alertDiv.innerHTML = '';
      alertDiv.className = 'alert-message';
    });
    closeBtn.addEventListener('click', () => {
      modal.classList.remove('active');
    });
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('active');
    });
  }
  
  // ---------- Validação e envio ----------
  function showAlert(message, isError = false) {
    alertDiv.style.display = 'block';
    alertDiv.innerHTML = message;
    alertDiv.className = `alert-message ${isError ? 'alert-danger' : 'alert-success'}`;
    setTimeout(() => {
      if (alertDiv.style.display === 'block') alertDiv.style.display = 'none';
    }, 5000);
  }
  
  function validateEmail(email) {
    return /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(email);
  }
  
  function validatePhone(phone) {
    const cleaned = phone.replace(/\s+/g, '');
    return cleaned.length >= 9;
  }
  
  // ---------- ENVIO REAL PARA O SERVIDOR (processar_pedido.php) com melhor tratamento de erro ----------
  async function submitFormData(formData) {
    const dados = {
      nome: formData.get('nome'),
      email: formData.get('email'),
      contacto: formData.get('contacto'),
      localizacao: formData.get('localizacao'),
      nivel: formData.get('nivel'),
      tipoAula: formData.get('tipoAula'),
      pacote: formData.get('pacote'),
      preco_base: parseInt(formData.get('preco_base')) || 0,
      preco_total: parseInt(formData.get('preco_total')) || 0,
      dias: formData.get('dias_semana') ? formData.get('dias_semana').split(', ') : [],
      horario: formData.get('horario'),
      dificuldade: formData.get('dificuldade')
    };
  
    const response = await fetch('processar_pedido.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ dados: dados })
    });
  
    // Se o servidor retornar erro HTTP (500, 404, etc.)
    if (!response.ok) {
      let errorText = '';
      try {
        const errorData = await response.json();
        errorText = errorData.mensagem || `HTTP ${response.status}`;
      } catch(e) {
        errorText = `Erro HTTP ${response.status}: ${response.statusText}`;
      }
      throw new Error(errorText);
    }
  
    const result = await response.json();
    return result;
  }
  
  // ---------- Evento de submissão do formulário ----------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Validações básicas
    const nome = nomeInput.value.trim();
    const email = emailInput.value.trim();
    const contacto = contactoInput.value.trim();
    const nivel = nivelSelect.value;
    
    if (!nome) {
      showAlert(currentLanguage === 'pt' ? 'Por favor, preencha o nome completo.' : 'Please enter your full name.', true);
      return;
    }
    if (!email || !validateEmail(email)) {
      showAlert(currentLanguage === 'pt' ? 'Por favor, insira um email válido.' : 'Please enter a valid email address.', true);
      return;
    }
    if (!contacto || !validatePhone(contacto)) {
      showAlert(currentLanguage === 'pt' ? 'Contacto telefónico inválido (mínimo 9 dígitos).' : 'Invalid phone number (min 9 digits).', true);
      return;
    }
    if (!nivel) {
      showAlert(currentLanguage === 'pt' ? 'Selecione o nível de ensino.' : 'Please select the education level.', true);
      return;
    }
    
    const selectedPackage = document.querySelector('input[name="pacote"]:checked');
    if (!selectedPackage) {
      showAlert(currentLanguage === 'pt' ? 'Selecione um pacote de aulas.' : 'Please select a lesson package.', true);
      return;
    }
    
    // Validar número de dias selecionados
    const limite = getDiasPermitidos();
    let marcados = 0;
    diasCheckboxes.forEach(cb => { if (cb.checked) marcados++; });
    if (marcados !== limite) {
      showAlert(currentLanguage === 'pt' 
        ? `Por favor, selecione exatamente ${limite} dia(s) da semana (de acordo com o pacote escolhido).`
        : `Please select exactly ${limite} weekday(s) according to your package.`, true);
      return;
    }
    
    const diasSelecionados = [];
    diasCheckboxes.forEach(cb => { if (cb.checked) diasSelecionados.push(cb.value); });
    
    const tipoAulaSelecionado = document.querySelector('input[name="tipoAula"]:checked').value;
    const precoTotal = totalSpan.textContent;
    
    // Criar FormData
    const formDataObj = new FormData();
    formDataObj.append('nome', nome);
    formDataObj.append('email', email);
    formDataObj.append('contacto', contacto);
    formDataObj.append('localizacao', localizacaoInput.value.trim());
    formDataObj.append('nivel', nivel);
    formDataObj.append('tipoAula', tipoAulaSelecionado);
    formDataObj.append('pacote', selectedPackage.value);
    formDataObj.append('preco_base', getBasePriceFromSelectedPackage().toString());
    formDataObj.append('preco_total', precoTotal);
    formDataObj.append('dias_semana', diasSelecionados.join(', '));
    formDataObj.append('horario', horarioSelect.value);
    formDataObj.append('dificuldade', dificuldadeTextarea.value.trim());
    
    const originalBtnContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading"></span> ' + (currentLanguage === 'pt' ? 'Enviando...' : 'Sending...');
    
    try {
      const response = await submitFormData(formDataObj);
      if (response.sucesso === true || response.success === true) {
        showAlert(response.mensagem || (currentLanguage === 'pt' ? 'Pedido enviado com sucesso!' : 'Request sent successfully!'), false);
        form.reset();
        document.querySelectorAll('.package-option').forEach(opt => opt.classList.remove('selected'));
        document.querySelector('input[name="tipoAula"][value="presencial"]').checked = true;
        diasCheckboxes.forEach(cb => cb.checked = false);
        updateTotal();
        // Fechar modal após 2 segundos (opcional)
        setTimeout(() => {
          modal.classList.remove('active');
        }, 2000);
      } else {
        const msg = response.mensagem || (currentLanguage === 'pt' ? 'Erro ao processar pedido.' : 'Error processing request.');
        showAlert(msg, true);
      }
    } catch (err) {
      console.error('Erro detalhado:', err);
      let errorMsg = currentLanguage === 'pt' ? 'Erro de comunicação com o servidor. Tente novamente mais tarde.' : 'Server communication error. Please try again later.';
      if (err.message) errorMsg += ' (' + err.message + ')';
      showAlert(errorMsg, true);
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnContent;
    }
  });
  
  // ---------- Inicialização ----------
  function init() {
    initModal();
    initLanguageSwitcher();
    initPackageAndType();
    attachPackageClick();
    initDiasCheckboxes();
    updateTotal();
  }
  
  init();
})();