// ===== VARIÁVEIS GLOBAIS =====
let disciplinasSelecionadas = [];
let contadorDisciplina = 0;

let disciplinasDadaSelecionadas = [];
let contadorDisciplinaDada = 0;

let aulaSelecionada = null;
let aulaCancelarData = null;

// ===== FUNÇÕES PARA MODAIS =====
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        console.log('✅ Modal aberto:', modalId);
    }
}

function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        console.log('✅ Modal fechado:', modalId);
    }
}

// ===== FUNÇÕES DE SEÇÃO =====
function mostrarSecao(id, event) {
    if (event) {
        event.preventDefault();
    }
    
    document.querySelectorAll("main section").forEach(sec => sec.classList.add("hidden"));
    document.getElementById(id).classList.remove("hidden");
    
    document.querySelectorAll(".sidebar ul li a").forEach(link => link.classList.remove("active"));
    if (event && event.target) {
        const link = event.target.closest('a');
        if (link) {
            link.classList.add('active');
        }
    }
    
    // Fechar sidebar em mobile após clicar
    if (window.innerWidth <= 768) {
        fecharSidebar();
    }
}

// ===== FUNÇÕES DO MENU MOBILE - EXATAMENTE IGUAL AO TESTE =====
function abrirSidebar() {
    console.log('🔵 abrirSidebar chamada');
    
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    // EXATAMENTE O MESMO CÓDIGO DO TESTE QUE FUNCIONOU
    sidebar.classList.add('active');
    overlay.classList.add('active');
    
    console.log('✅ Sidebar ABERTA - Classes:', sidebar.className);
    console.log('Transform:', window.getComputedStyle(sidebar).transform);
}

function fecharSidebar() {
    console.log('🔴 fecharSidebar chamada');
    
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    
    console.log('✅ Sidebar FECHADA');
}

// ===== FUNÇÃO PARA O CALENDÁRIO DO PROFESSOR (CORRIGIDA) =====
function abrirModalDiaProfessor(elemento) {
    const alunoId = elemento.dataset.alunoId;
    const data = elemento.dataset.data;
    const status = elemento.dataset.status;
    const aulaId = elemento.dataset.aulaId;
    const horario = elemento.dataset.horario || '';
    const podeRegistrar = elemento.dataset.podeRegistrar === 'true';
    const podeCancelar = elemento.dataset.podeCancelar === 'true';
    const canceladaAuto = elemento.dataset.canceladaAuto === 'true';
    
    console.log('🔍 Clique no calendário do professor:', { 
        alunoId, data, status, aulaId, horario, 
        podeRegistrar, podeCancelar, canceladaAuto 
    });
    
    if (!alunoId) {
        alert('Erro: ID do aluno não encontrado');
        return;
    }
    
    // Verificar se o dia está dentro do período do pacote
    if (elemento.classList.contains('fora-periodo')) {
        alert('Este dia está fora do período do pacote do aluno.');
        return;
    }
    
    // Obter nome do aluno
    const nomeAluno = obterNomeAluno(alunoId);
    
    // Criar objetos de data para comparação
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);
    
    const dataAula = new Date(data);
    dataAula.setHours(0, 0, 0, 0);
    
    // Extrair hora e minuto da aula
    const [horaAula, minutoAula] = horario.split(':').map(Number);
    const dataHoraAula = new Date(data);
    dataHoraAula.setHours(horaAula || 0, minutoAula || 0, 0, 0);
    
    const agora = new Date();
    
    // Formatar data para exibição
    const dataFormatada = dataAula.toLocaleDateString('pt-PT');
    
    // 🔴 REGRA 1: Aula já realizada → mostrar detalhes
    if (status === 'realizado') {
        console.log('✅ Aula já realizada, mostrar detalhes');
        if (aulaId) {
            verDetalhesAula(aulaId);
        }
        return;
    }
    
    // 🔴 REGRA 2: Aula cancelada → mostrar detalhes ou mensagem
    if (status === 'cancelado_aluno' || status === 'cancelado_professor') {
        console.log('❌ Aula cancelada');
        if (aulaId) {
            verDetalhesAula(aulaId);
        } else if (canceladaAuto) {
            alert('❌ Esta aula foi cancelada automaticamente por falta de registro.');
        }
        return;
    }
    
    // 🔴 REGRA 3: Aula cancelada automaticamente
    if (canceladaAuto) {
        alert('❌ Esta aula foi cancelada automaticamente por falta de registro.');
        return;
    }
    
    // 🔴 REGRA 4: Dia sem aula agendada
    if (!elemento.classList.contains('dia-aula')) {
        alert('📅 Não há aula agendada para este dia.');
        return;
    }
    
    // 🔴 REGRAS DE NEGÓCIO PARA O PROFESSOR
    
    // Se a aula já tem ID (existe no banco)
    if (aulaId) {
        // CASO 1: Aula agendada → verificar se pode cancelar ou registrar
        if (status === 'agendado') {
            // Verificar se a data é futura
            if (dataAula > hoje) {
                // Data futura → pode cancelar (ainda não chegou o dia)
                console.log('📅 Data futura com aula agendada - Abrir cancelamento');
                abrirModalCancelarAntecipado(aulaId, nomeAluno, `${dataFormatada} ${horario}`);
                return;
            }
            
            // Verificar se é hoje
            if (dataAula.getTime() === hoje.getTime()) {
                const horarioPassou = agora >= dataHoraAula;
                
                if (horarioPassou) {
                    // JÁ PASSOU DO HORÁRIO → PODE REGISTRAR A AULA DADA
                    console.log('📅 Hoje, horário já passou - Abrir REGISTRO de aula');
                    abrirModalRegistro(alunoId, nomeAluno, data, horario);
                } else {
                    // AINDA NÃO CHEGOU O HORÁRIO → PODE CANCELAR
                    console.log('📅 Hoje, antes do horário - Abrir CANCELAMENTO');
                    abrirModalCancelarAntecipado(aulaId, nomeAluno, `${dataFormatada} ${horario}`);
                }
                return;
            }
            
            // Data passada com status agendado (aula que passou sem registro)
            if (dataAula < hoje) {
                console.log('⚠️ Data passada com status agendado - PODE REGISTRAR ATRASADO');
                // Permitir registro mesmo para dias passados (aula não registrada)
                abrirModalRegistro(alunoId, nomeAluno, data, horario);
                return;
            }
        }
        
        // CASO 2: Outros status (pendente, etc.)
        alert(`Status da aula: ${status}. Contacte o suporte se necessário.`);
        return;
    }
    
    // Se NÃO tem ID, mas é dia de aula (aula prevista mas ainda não agendada no banco)
    if (elemento.classList.contains('dia-aula') && !aulaId) {
        // Data futura sem aula agendada (deveria ter, mas não tem)
        if (dataAula > hoje) {
            alert('⚠️ Aula prevista mas sem agendamento. Contacte o suporte.');
            return;
        }
        
        // Data passada sem registro (aula que deveria ter acontecido)
        if (dataAula < hoje) {
            alert('❌ Esta aula foi cancelada automaticamente por falta de registro.');
            return;
        }
        
        // Hoje sem aula agendada (erro)
        alert('⚠️ Aula prevista para hoje mas sem agendamento. Contacte o suporte.');
        return;
    }
    
    // Fallback
    console.log('📅 Caso não tratado:', { status, aulaId, podeRegistrar, podeCancelar });
    alert('Situação não prevista. Contacte o suporte.');
}

function obterNomeAluno(alunoId) {
    const alunos = window.alunosData || {};
    return alunos[alunoId] || 'Aluno';
}

// ===== FUNÇÕES PARA MODAL DE REGISTRO =====
function abrirModalRegistro(fichaId, nomeAluno, dataEspecifica = null, horarioPadrao = '') {
    console.log('Abrindo modal registro:', { fichaId, nomeAluno, dataEspecifica, horarioPadrao });
    
    limparTodasDisciplinas();
    
    document.getElementById('registro_ficha_id').value = fichaId;
    document.getElementById('nome_aluno_registro').textContent = nomeAluno;
    document.getElementById('horario_padrao_display').textContent = horarioPadrao || 'Não definido';
    
    if (dataEspecifica) {
        const [ano, mes, dia] = dataEspecifica.split('-');
        const dataObj = new Date(ano, mes-1, dia);
        const dataFormatada = dataObj.toLocaleDateString('pt-PT');
        document.getElementById('data_hora_display').textContent = dataFormatada + ' ' + (horarioPadrao || '--:--');
        document.getElementById('registro_data_hora').value = dataEspecifica + ' ' + (horarioPadrao || '00:00') + ':00';
    } else {
        const now = new Date();
        const data = now.toISOString().split('T')[0];
        const hora = now.toTimeString().substring(0, 5);
        document.getElementById('data_hora_display').textContent = `${data.split('-').reverse().join('/')} ${hora}`;
        document.getElementById('registro_data_hora').value = data + ' ' + hora + ':00';
    }
    
    document.getElementById('observacoes_gerais').value = '';
    
    abrirModal('modalRegistro');
}

function registrarAula() {
    const disciplinas = coletarDisciplinas();
    
    if (disciplinas.length === 0) {
        alert('❌ Por favor, selecione pelo menos uma disciplina e preencha o conteúdo.');
        return;
    }
    
    const fichaId = document.getElementById('registro_ficha_id').value;
    const dataHora = document.getElementById('registro_data_hora').value;
    const observacoes = document.getElementById('observacoes_gerais').value;
    
    document.getElementById('registro-form-container').style.display = 'none';
    document.getElementById('registro-loading').style.display = 'block';
    
    const dados = {
        acao_aula: 'registrar_multiplas',
        ficha_id: fichaId,
        data_hora: dataHora,
        disciplinas: disciplinas,
        observacoes_gerais: observacoes
    };
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Aula registrada com sucesso!');
            fecharModal('modalRegistro');
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
            document.getElementById('registro-form-container').style.display = 'block';
            document.getElementById('registro-loading').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao registrar aula. Tente novamente.');
        document.getElementById('registro-form-container').style.display = 'block';
        document.getElementById('registro-loading').style.display = 'none';
    });
}

// ===== FUNÇÕES DE DISCIPLINAS =====
function toggleDisciplinaRapida(elemento, disciplina) {
    elemento.classList.toggle('selecionada');
    
    if (disciplinasSelecionadas.includes(disciplina)) {
        disciplinasSelecionadas = disciplinasSelecionadas.filter(d => d !== disciplina);
        removerDisciplinaCard(disciplina);
    } else {
        disciplinasSelecionadas.push(disciplina);
        adicionarDisciplinaCard(disciplina);
    }
}

function adicionarDisciplinaCard(disciplina) {
    contadorDisciplina++;
    const container = document.getElementById('disciplinas-selecionadas-container');
    
    const cardId = `disciplina-card-${contadorDisciplina}`;
    const conteudoId = `conteudo_${contadorDisciplina}`;
    const dificuldadesId = `dificuldades_${contadorDisciplina}`;
    const observacoesId = `observacoes_${contadorDisciplina}`;
    
    const cardHtml = `
        <div class="disciplina-card" id="${cardId}" data-disciplina="${disciplina}" data-index="${contadorDisciplina}">
            <div class="disciplina-header">
                <h5>
                    <i class="fas fa-graduation-cap"></i> 
                    ${disciplina}
                </h5>
                <button type="button" class="btn-remover" onclick="removerDisciplinaPorNome('${disciplina}')">
                    <i class="fas fa-trash"></i> Remover
                </button>
            </div>
            <div class="disciplina-body">
                <div class="form-group">
                    <label class="required">Conteúdo Abordado</label>
                    <textarea id="${conteudoId}" rows="3" required
                              placeholder="Descreva o conteúdo específico de ${disciplina}..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Dificuldades Identificadas</label>
                    <textarea id="${dificuldadesId}" rows="2"
                              placeholder="Dificuldades do aluno em ${disciplina}..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea id="${observacoesId}" rows="2"
                              placeholder="Observações específicas para ${disciplina}..."></textarea>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', cardHtml);
}

function removerDisciplinaCard(disciplina) {
    const cards = document.querySelectorAll('#disciplinas-selecionadas-container .disciplina-card');
    cards.forEach(card => {
        if (card.dataset.disciplina === disciplina) {
            card.remove();
        }
    });
}

function removerDisciplinaPorNome(disciplina) {
    disciplinasSelecionadas = disciplinasSelecionadas.filter(d => d !== disciplina);
    removerDisciplinaCard(disciplina);
    
    const tags = document.querySelectorAll('#modalRegistro .disciplina-tag');
    tags.forEach(tag => {
        if (tag.textContent.includes(disciplina)) {
            tag.classList.remove('selecionada');
        }
    });
}

function limparTodasDisciplinas() {
    disciplinasSelecionadas = [];
    document.getElementById('disciplinas-selecionadas-container').innerHTML = '';
    contadorDisciplina = 0;
    
    const tags = document.querySelectorAll('#modalRegistro .disciplina-tag');
    tags.forEach(tag => tag.classList.remove('selecionada'));
}

function coletarDisciplinas() {
    const disciplinas = [];
    const cards = document.querySelectorAll('#disciplinas-selecionadas-container .disciplina-card');
    
    cards.forEach((card) => {
        const index = card.dataset.index;
        const disciplina = card.dataset.disciplina;
        const conteudo = document.getElementById(`conteudo_${index}`)?.value || '';
        const dificuldades = document.getElementById(`dificuldades_${index}`)?.value || '';
        const observacoes = document.getElementById(`observacoes_${index}`)?.value || '';
        
        if (conteudo.trim() !== '') {
            disciplinas.push({
                materia: disciplina,
                conteudo: conteudo,
                dificuldades: dificuldades,
                observacoes: observacoes
            });
        }
    });
    
    return disciplinas;
}

// ===== FUNÇÕES PARA MODAL DE CONCLUSÃO =====
function toggleDisciplinaRapidaDada(elemento, disciplina) {
    elemento.classList.toggle('selecionada');
    
    if (disciplinasDadaSelecionadas.includes(disciplina)) {
        disciplinasDadaSelecionadas = disciplinasDadaSelecionadas.filter(d => d !== disciplina);
        removerDisciplinaCardDada(disciplina);
    } else {
        disciplinasDadaSelecionadas.push(disciplina);
        adicionarDisciplinaCardDada(disciplina);
    }
}

function adicionarDisciplinaCardDada(disciplina) {
    contadorDisciplinaDada++;
    const container = document.getElementById('dada-disciplinas-container');
    
    const cardId = `dada-card-${contadorDisciplinaDada}`;
    const conteudoId = `dada_conteudo_${contadorDisciplinaDada}`;
    const dificuldadesId = `dada_dificuldades_${contadorDisciplinaDada}`;
    const observacoesId = `dada_observacoes_${contadorDisciplinaDada}`;
    
    const cardHtml = `
        <div class="disciplina-card" id="${cardId}" data-disciplina="${disciplina}" data-index="${contadorDisciplinaDada}">
            <div class="disciplina-header">
                <h5>
                    <i class="fas fa-graduation-cap"></i> 
                    ${disciplina}
                </h5>
                <button type="button" class="btn-remover" onclick="removerDisciplinaPorNomeDada('${disciplina}')">
                    <i class="fas fa-trash"></i> Remover
                </button>
            </div>
            <div class="disciplina-body">
                <div class="form-group">
                    <label class="required">Conteúdo Abordado</label>
                    <textarea id="${conteudoId}" rows="3" required
                              placeholder="Descreva o conteúdo específico de ${disciplina}..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Dificuldades Identificadas</label>
                    <textarea id="${dificuldadesId}" rows="2"
                              placeholder="Dificuldades do aluno em ${disciplina}..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Observações</label>
                    <textarea id="${observacoesId}" rows="2"
                              placeholder="Observações específicas para ${disciplina}..."></textarea>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', cardHtml);
}

function removerDisciplinaCardDada(disciplina) {
    const cards = document.querySelectorAll('#dada-disciplinas-container .disciplina-card');
    cards.forEach(card => {
        if (card.dataset.disciplina === disciplina) {
            card.remove();
        }
    });
}

function removerDisciplinaPorNomeDada(disciplina) {
    disciplinasDadaSelecionadas = disciplinasDadaSelecionadas.filter(d => d !== disciplina);
    removerDisciplinaCardDada(disciplina);
    
    const tags = document.querySelectorAll('#dada-disciplinas-rapidas .disciplina-tag');
    tags.forEach(tag => {
        if (tag.textContent.includes(disciplina)) {
            tag.classList.remove('selecionada');
        }
    });
}

function limparTodasDisciplinasDada() {
    disciplinasDadaSelecionadas = [];
    document.getElementById('dada-disciplinas-container').innerHTML = '';
    contadorDisciplinaDada = 0;
    
    const tags = document.querySelectorAll('#dada-disciplinas-rapidas .disciplina-tag');
    tags.forEach(tag => tag.classList.remove('selecionada'));
}

function coletarDisciplinasDada() {
    const disciplinas = [];
    const cards = document.querySelectorAll('#dada-disciplinas-container .disciplina-card');
    
    cards.forEach((card) => {
        const index = card.dataset.index;
        const disciplina = card.dataset.disciplina;
        const conteudo = document.getElementById(`dada_conteudo_${index}`)?.value || '';
        const dificuldades = document.getElementById(`dada_dificuldades_${index}`)?.value || '';
        const observacoes = document.getElementById(`dada_observacoes_${index}`)?.value || '';
        
        if (conteudo.trim() !== '') {
            disciplinas.push({
                materia: disciplina,
                conteudo: conteudo,
                dificuldades: dificuldades,
                observacoes: observacoes
            });
        }
    });
    
    return disciplinas;
}

// ===== FUNÇÕES DE CONCLUSÃO DE AULA =====
function marcarComoRealizada(aulaId) {
    console.log('Marcar como realizada:', aulaId);
    
    document.getElementById('dada_aula_id').value = aulaId;
    
    fetch(`buscar_aula.php?aula_id=${aulaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('dada_aluno_nome').textContent = data.aluno_nome;
                
                const dataObj = new Date(data.data_hora);
                const dataFormatada = dataObj.toLocaleDateString('pt-PT');
                const horaFormatada = dataObj.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
                document.getElementById('dada_data_hora').textContent = `${dataFormatada} ${horaFormatada}`;
                
                limparTodasDisciplinasDada();
                abrirModal('modalDada');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar aula:', error);
            alert('Erro ao carregar dados da aula.');
        });
}

function concluirAula() {
    const disciplinas = coletarDisciplinasDada();
    
    if (disciplinas.length === 0) {
        alert('❌ Por favor, selecione pelo menos uma disciplina e preencha o conteúdo.');
        return;
    }
    
    const aulaId = document.getElementById('dada_aula_id').value;
    const observacoes = document.getElementById('dada_observacoes').value;
    
    document.getElementById('dada_observacoes').disabled = true;
    
    const dados = {
        acao_aula: 'concluir',
        aula_id: aulaId,
        disciplinas: disciplinas,
        observacoes_gerais: observacoes
    };
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Aula concluída com sucesso!');
            fecharModal('modalDada');
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao concluir aula. Tente novamente.');
    });
}

// ===== FUNÇÕES DE CANCELAMENTO =====
function cancelarAula(aulaId, tipo = 'professor') {
    let mensagem = '';
    if (tipo === 'aluno') {
        mensagem = 'Cancelar como cancelamento do aluno? (Não consome aula)';
    } else {
        mensagem = 'Cancelar como cancelamento do professor? (Gera crédito de reposição)';
    }
    
    if (!confirm(mensagem)) return;
    
    const dados = {
        acao_aula: 'cancelar',
        aula_id: aulaId,
        tipo_cancelamento: tipo
    };
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao cancelar aula. Tente novamente.');
    });
}

// ===== FUNÇÃO PARA ABRIR MODAL DE CANCELAMENTO ANTECIPADO =====
function abrirModalCancelarAntecipado(aulaId, alunoNome, dataHora) {
    console.log('Abrindo cancelamento antecipado:', { aulaId, alunoNome, dataHora });
    
    if (!aulaId) {
        alert('❌ Erro: ID da aula não fornecido');
        return;
    }
    
    aulaCancelarData = {
        aulaId: aulaId,
        alunoNome: alunoNome,
        dataHora: dataHora
    };
    
    // Verificar se o modal existe
    if (!document.getElementById('modalCancelarAntecipado')) {
        alert('Erro: Modal de cancelamento não encontrado. Recarregue a página.');
        return;
    }
    
    document.getElementById('cancelar_aluno_nome').textContent = alunoNome;
    document.getElementById('cancelar_data_hora').textContent = dataHora;
    document.getElementById('cancelar_aula_id').value = aulaId;
    
    // Resetar formulário
    document.getElementById('motivo_cancelamento').value = '';
    document.getElementById('motivo_outro').style.display = 'none';
    document.getElementById('motivo_outro').value = '';
    
    abrirModal('modalCancelarAntecipado');
}

// ===== FUNÇÃO PARA CONFIRMAR CANCELAMENTO ANTECIPADO =====
function confirmarCancelamentoAntecipado() {
    const aulaId = document.getElementById('cancelar_aula_id').value;
    const motivoSelect = document.getElementById('motivo_cancelamento').value;
    const motivoOutro = document.getElementById('motivo_outro').value;
    
    console.log('Confirmando cancelamento:', { aulaId, motivoSelect });
    
    if (!aulaId) {
        alert('❌ Erro: ID da aula não identificado');
        return;
    }
    
    let motivo = motivoSelect;
    if (motivoSelect === 'Outro') {
        if (!motivoOutro.trim()) {
            alert('❌ Por favor, descreva o motivo do cancelamento.');
            return;
        }
        motivo = motivoOutro;
    } else if (!motivoSelect) {
        alert('❌ Por favor, selecione um motivo para o cancelamento.');
        return;
    }
    
    if (!confirm('Tem certeza que deseja cancelar esta aula? O aluno será notificado imediatamente.')) {
        return;
    }
    
    fecharModal('modalCancelarAntecipado');
    
    // Mostrar loading
    const loadingMsg = document.createElement('div');
    loadingMsg.id = 'cancelar-loading';
    loadingMsg.style.position = 'fixed';
    loadingMsg.style.top = '0';
    loadingMsg.style.left = '0';
    loadingMsg.style.width = '100%';
    loadingMsg.style.height = '100%';
    loadingMsg.style.background = 'rgba(0,0,0,0.5)';
    loadingMsg.style.zIndex = '2000';
    loadingMsg.style.display = 'flex';
    loadingMsg.style.alignItems = 'center';
    loadingMsg.style.justifyContent = 'center';
    loadingMsg.innerHTML = '<div style="background: white; padding: 30px; border-radius: 10px; text-align: center;"><i class="fas fa-spinner fa-spin fa-3x" style="color: #3498db;"></i><p style="margin-top: 20px;">Cancelando aula...</p></div>';
    document.body.appendChild(loadingMsg);
    
    const dados = {
        acao_aula: 'cancelar_antecipado',
        aula_id: aulaId,
        motivo: motivo
    };
    
    fetch('processar_cancelamento_professor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('cancelar-loading')?.remove();
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(error => {
        document.getElementById('cancelar-loading')?.remove();
        console.error('Erro:', error);
        alert('❌ Erro ao cancelar aula. Tente novamente.');
    });
}

// ===== FUNÇÃO PARA VER DETALHES DA AULA (MELHORADA) =====
function verDetalhesAula(aulaId) {
    console.log('Ver detalhes da aula ID:', aulaId);
    
    if (!aulaId) {
        alert('ID da aula não fornecido');
        return;
    }
    
    // Mostrar loading
    document.getElementById('modal-body-conteudo').innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: #3498db;"></i>
            <p style="margin-top: 20px;">Carregando detalhes...</p>
        </div>
    `;
    abrirModal('modalDetalhes');
    
    fetch(`buscar_aula.php?aula_id=${aulaId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('❌ Erro: ' + data.message);
                fecharModal('modalDetalhes');
                return;
            }
            
            const aula = data.data;
            const dataHora = new Date(aula.data_hora);
            const dataStr = dataHora.toLocaleDateString('pt-PT');
            const horaStr = dataHora.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
            
            // Traduzir status e definir cores
            let statusTexto = '', statusCor = '';
            switch (aula.status) {
                case 'realizado':
                    statusTexto = '✅ Realizada';
                    statusCor = '#27ae60';
                    break;
                case 'cancelado_aluno':
                    statusTexto = '❌ Cancelada pelo aluno';
                    statusCor = '#e74c3c';
                    break;
                case 'cancelado_professor':
                    statusTexto = '❌ Cancelada pelo professor';
                    statusCor = '#e67e22';
                    break;
                case 'agendado':
                    statusTexto = '⏳ Agendada';
                    statusCor = '#f39c12';
                    break;
                case 'pendente_professor':
                    statusTexto = '⏰ Pendente (professor)';
                    statusCor = '#f39c12';
                    break;
                default:
                    statusTexto = aula.status;
                    statusCor = '#95a5a6';
            }
            
            let html = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Data</div>
                            <div style="font-weight: 600;">${dataStr}</div>
                        </div>
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Hora</div>
                            <div style="font-weight: 600;">${horaStr}</div>
                        </div>
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Professor</div>
                            <div style="font-weight: 600;">${aula.professor_nome || 'Não atribuído'}</div>
                        </div>
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Status</div>
                            <div style="font-weight: 600; color: ${statusCor};">${statusTexto}</div>
                        </div>
                    </div>
                </div>
            `;
            
            // Se a aula foi cancelada, mostrar motivo e quem cancelou
            if (aula.status === 'cancelado_aluno' || aula.status === 'cancelado_professor') {
                const cancelador = aula.status === 'cancelado_aluno' ? 'Aluno' : 'Professor';
                // O motivo está na coluna observacoes_professor
                let motivo = aula.observacoes_professor || 'Motivo não informado';
                // Remover possíveis prefixos (como "❌ Cancelada pelo aluno. Motivo: ")
                if (motivo.includes('Motivo:')) {
                    const match = motivo.match(/Motivo:\s*(.+)/);
                    if (match) motivo = match[1];
                }
                html += `
                    <div style="background: #ffe6e6; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid ${statusCor};">
                        <strong><i class="fas fa-ban"></i> Cancelado por: ${cancelador}</strong><br>
                        <strong>Motivo:</strong> ${motivo}
                    </div>
                `;
            }
            
            // Disciplinas registadas (se houver)
            if (aula.itens && aula.itens.length > 0) {
                html += `<h4 style="margin: 20px 0 10px; color: #2c3e50;"><i class="fas fa-graduation-cap"></i> Disciplinas</h4>`;
                html += `<div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <thead style="background: #2c3e50; color: white;">
                                    <tr>
                                        <th style="padding: 12px; text-align: left;">Disciplina</th>
                                        <th style="padding: 12px; text-align: left;">Conteúdo Abordado</th>
                                        <th style="padding: 12px; text-align: left;">Dificuldades</th>
                                        <th style="padding: 12px; text-align: left;">Observações</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                aula.itens.forEach((item, index) => {
                    const bgColor = index % 2 === 0 ? '#f8f9fa' : 'white';
                    html += `
                        <tr style="background: ${bgColor}; border-bottom: 1px solid #e9ecef;">
                            <td style="padding: 12px; font-weight: 600; color: #2c3e50;">${item.disciplina || '—'}</td>
                            <td style="padding: 12px;">${item.conteudo_abordado || '—'}</td>
                            <td style="padding: 12px;">${item.dificuldades_identificadas || '—'}</td>
                            <td style="padding: 12px;">${item.observacoes_professor || '—'}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table></div>`;
            } else if (aula.status === 'realizado') {
                html += `<p style="margin-top: 10px;"><em>Nenhuma disciplina registada para esta aula.</em></p>`;
            }
            
            // Observações gerais (se não for cancelada, para não repetir)
            if (aula.observacoes_professor && aula.status !== 'cancelado_aluno' && aula.status !== 'cancelado_professor') {
                html += `
                    <div style="margin-top: 20px; background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                        <h5 style="margin: 0 0 8px 0; color: #1976d2;"><i class="fas fa-clipboard"></i> Observações Gerais</h5>
                        <p style="margin: 0; color: #2c3e50;">${aula.observacoes_professor}</p>
                    </div>
                `;
            }
            
            document.getElementById('modal-body-conteudo').innerHTML = html;
        })
        .catch(error => {
            console.error('Erro ao buscar detalhes da aula:', error);
            alert('Erro ao carregar detalhes da aula.');
            fecharModal('modalDetalhes');
        });
}

// ===== FUNÇÕES DE VISUALIZAÇÃO =====
function verAulasAluno(fichaId, nomeAluno) {
    console.log('Ver aulas do aluno:', { fichaId, nomeAluno });
    
    document.getElementById('nome_aluno_aulas').textContent = nomeAluno;
    
    fetch(`carregar_aulas_aluno.php?ficha_id=${fichaId}&t=${new Date().getTime()}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('conteudo_aulas_aluno').innerHTML = data;
        })
        .catch(error => {
            console.error('Erro ao carregar aulas:', error);
            document.getElementById('conteudo_aulas_aluno').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Erro ao carregar as aulas.
                </div>
            `;
        });
    
    abrirModal('modalAulasAluno');
}

// ===== FUNÇÃO: VER AULAS CANCELADAS AUTOMATICAMENTE =====
function verAulasCanceladas() {
    const urlParams = new URLSearchParams(window.location.search);
    const mes = urlParams.get('mes') || new Date().getMonth() + 1;
    const ano = urlParams.get('ano') || new Date().getFullYear();
    
    // Criar modal se não existir
    if (!document.getElementById('modalCanceladasAuto')) {
        const modalCanceladas = document.createElement('div');
        modalCanceladas.id = 'modalCanceladasAuto';
        modalCanceladas.className = 'modal';
        modalCanceladas.innerHTML = `
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Aulas Canceladas Automaticamente</h3>
                    <button class="close" onclick="fecharModal('modalCanceladasAuto')">&times;</button>
                </div>
                <div id="conteudo-canceladas-auto" style="padding: 20px; text-align: center;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: #3498db;"></i>
                    <p>Carregando...</p>
                </div>
            </div>
        `;
        document.body.appendChild(modalCanceladas);
    }
    
    fetch(`carregar_canceladas_auto.php?mes=${mes}&ano=${ano}&t=${new Date().getTime()}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('conteudo-canceladas-auto').innerHTML = data;
        })
        .catch(error => {
            console.error('Erro ao carregar aulas canceladas:', error);
            document.getElementById('conteudo-canceladas-auto').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i> Erro ao carregar dados.
                </div>
            `;
        });
    
    abrirModal('modalCanceladasAuto');
}

// ===== FUNÇÃO PARA MOSTRAR NOTIFICAÇÃO DE AULAS CANCELADAS =====
function mostrarNotificacaoCanceladas(total) {
    if (total > 0 && !document.getElementById('notificacao-canceladas')) {
        const notificacao = document.createElement('div');
        notificacao.id = 'notificacao-canceladas';
        notificacao.className = 'alert alert-warning';
        notificacao.style.marginTop = '20px';
        notificacao.style.position = 'relative';
        notificacao.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Atenção:</strong> ${total} aula(s) foram canceladas automaticamente neste mês por falta de registro.
            <button onclick="verAulasCanceladas()" class="btn btn-sm btn-warning" style="margin-left: 10px;">
                <i class="fas fa-eye"></i> Ver aulas
            </button>
            <button onclick="this.parentElement.remove()" style="position: absolute; right: 10px; top: 10px; background: none; border: none; color: #856404; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        const welcomeCard = document.querySelector('.welcome-card');
        if (welcomeCard) {
            welcomeCard.appendChild(notificacao);
        }
    }
}

// ===== CONFIGURAÇÃO DO SELECT DE MOTIVOS =====
function configurarSelectMotivos() {
    const motivoSelect = document.getElementById('motivo_cancelamento');
    const motivoOutro = document.getElementById('motivo_outro');
    
    if (motivoSelect && motivoOutro) {
        motivoSelect.addEventListener('change', function() {
            if (this.value === 'Outro') {
                motivoOutro.style.display = 'block';
            } else {
                motivoOutro.style.display = 'none';
            }
        });
    }
}

// ===== FUNÇÕES DE NOTIFICAÇÕES =====
function toggleNotificacoes() {
    const dropdown = document.getElementById('notificacoesDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
}

function marcarComoLida(notificacaoId) {
    fetch('notificacoes.php?acao=marcar_lida', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificacaoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`.notificacao-item[data-id="${notificacaoId}"]`);
            if (item) {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            }
            atualizarContadorNotificacoes();
        }
    });
}

function marcarTodasComoLidas() {
    fetch('notificacoes.php?acao=marcar_todas_lidas', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notificacao-item.nao-lida').forEach(item => {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            });
            atualizarContadorNotificacoes();
        }
    });
}

function atualizarContadorNotificacoes() {
    fetch('notificacoes.php?acao=listar&nao_lidas=1&limite=1')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notificacoes-badge');
            if (data.nao_lidas > 0) {
                if (badge) {
                    badge.textContent = data.nao_lidas;
                } else {
                    const btn = document.querySelector('.notificacoes-btn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notificacoes-badge';
                    newBadge.textContent = data.nao_lidas;
                    btn.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        });
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard Professor inicializado');
    console.log('📊 Dados disponíveis:', { 
        alunosData: window.alunosData,
        totalCanceladasAuto: window.totalCanceladasAuto,
        mesAtual: window.mesAtual,
        anoAtual: window.anoAtual,
        notificacoesNaoLidas: window.notificacoesNaoLidas
    });
    
    // Mostrar seção de boas-vindas por padrão
    document.querySelectorAll("main section").forEach(sec => sec.classList.add("hidden"));
    document.getElementById("boas_vindas").classList.remove("hidden");
    
    // Ativar primeiro link do sidebar
    const primeiroLink = document.querySelector('.sidebar ul li:first-child a');
    if (primeiroLink) {
        primeiroLink.classList.add('active');
    }
    
    // ===== MENU MOBILE - EXATAMENTE IGUAL AO TESTE QUE FUNCIONOU =====
    const menuToggle = document.getElementById('menuToggle');
    const menuClose = document.getElementById('menuClose');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('sidebar');
    
    console.log('🔍 Elementos do menu:', {
        menuToggle: !!menuToggle,
        menuClose: !!menuClose,
        overlay: !!overlay,
        sidebar: !!sidebar
    });
    
    // Botão do menu hambúrguer - IGUAL AO TESTE
    if (menuToggle) {
        menuToggle.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🍔 Menu hambúrguer clicado');
            
            // EXATAMENTE O MESMO CÓDIGO DO TESTE
            sidebar.classList.add('active');
            overlay.classList.add('active');
            
            console.log('Depois - sidebar classes:', sidebar.className);
            console.log('Depois - overlay classes:', overlay.className);
            console.log('Transform sidebar:', window.getComputedStyle(sidebar).transform);
            
            return false;
        };
        console.log('✅ Botão menu toggle configurado');
    }
    
    // Botão de fechar no sidebar
    if (menuClose) {
        menuClose.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('❌ Botão fechar clicado');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            
            return false;
        };
        console.log('✅ Botão menu close configurado');
    }
    
    // Overlay (fundo escuro)
    if (overlay) {
        overlay.onclick = function(e) {
            e.preventDefault();
            console.log('🔲 Overlay clicado');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        };
        console.log('✅ Overlay configurado');
    }
    
    // Links do sidebar (fechar ao clicar em mobile)
    document.querySelectorAll('.sidebar ul li a').forEach(link => {
        if (link.getAttribute('href') === 'logout.php') {
            return;
        }
        
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                console.log('🔗 Link clicado, fechando sidebar');
                setTimeout(function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }, 150);
            }
        });
    });
    
    // Fechar sidebar ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
    
    // Fechar sidebar com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
    
    // Adicionar eventos aos dias do calendário
    document.querySelectorAll('.calendario-dia:not(.fora-periodo)').forEach(dia => {
        dia.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('📅 Dia clicado');
            abrirModalDiaProfessor(this);
        });
    });
    
    // Verificar se há notificação de aulas canceladas
    if (typeof window.totalCanceladasAuto !== 'undefined' && window.totalCanceladasAuto > 0) {
        mostrarNotificacaoCanceladas(window.totalCanceladasAuto);
    }
    
    // Configurar select de motivos
    configurarSelectMotivos();
    
    // Fechar modais ao clicar fora
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Fechar modais com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = 'auto';
        }
    });
    
    // Fechar dropdown de notificações ao clicar fora
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.notificacoes-container')) {
            const dropdown = document.getElementById('notificacoesDropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }
    });
});