// ===== VARIÁVEIS GLOBAIS =====
let cancelamentoData = null;

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

// ===== FUNÇÃO PRINCIPAL DO CALENDÁRIO =====
function abrirModalDia(elemento) {
    console.log('🔍 Clique no calendário - elemento:', elemento);
    console.log('📊 Dataset:', elemento.dataset);
    
    const data = elemento.dataset.data;
    const status = elemento.dataset.status;
    const aulaId = elemento.dataset.aulaId;
    const podeCancelar = elemento.dataset.podeCancelar === 'true';
    const horario = elemento.dataset.horario || '';
    const isFuturo = elemento.dataset.debugIsFuturo === 'true';
    
    console.log('📅 Dados processados:', { data, status, aulaId, podeCancelar, horario, isFuturo });
    
    // Se não tem aulaId, verifica se é dia de aula
    if (!aulaId && !elemento.classList.contains('dia-aula')) {
        alert('Não há aula agendada para este dia.');
        return;
    }
    
    // Formatar data para mensagens
    const dataObj = new Date(data + 'T12:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-PT');
    
    // ===== CASO 1: Aula já realizada (verde) =====
    if (status === 'realizado') {
        console.log('✅ Aula realizada, buscando detalhes');
        if (aulaId) {
            verDetalhesAula(aulaId);
        } else {
            alert('Esta aula já foi realizada.');
        }
        return;
    }
    
    // ===== CASO 2: Aula cancelada pelo aluno (vermelho) =====
    if (status === 'cancelado_aluno') {
        alert('❌ Esta aula foi cancelada por ti.');
        return;
    }
    
    // ===== CASO 3: Aula cancelada pelo professor (laranja) =====
    if (status === 'cancelado_professor') {
        alert('🔴 Esta aula foi cancelada pelo professor. Tens 1 crédito de reposição.');
        return;
    }
    
    // ===== CASO 4: Aula agendada FUTURA (pode cancelar) =====
    if (aulaId && isFuturo && status === 'agendado') {
        console.log('📝 Aula futura agendada - abrir cancelamento');
        abrirModalCancelamento(aulaId, dataFormatada, horario);
        return;
    }
    
    // ===== CASO 5: Aula agendada PASSA (já passou mas não foi registrada) =====
    if (aulaId && !isFuturo && status === 'agendado') {
        console.log('⚠️ Aula passada com status agendado (erro)');
        alert('❌ Esta aula deveria ter sido registrada ou cancelada. Contacte o professor.');
        return;
    }
    
    // ===== CASO 6: Hoje, antes do horário =====
    const hoje = new Date();
    hoje.setHours(0,0,0,0);
    const dataAula = new Date(data);
    dataAula.setHours(0,0,0,0);
    
    if (dataAula.getTime() === hoje.getTime() && elemento.classList.contains('dia-aula')) {
        alert(`⏰ Aula agendada para hoje às ${horario}. Após o horário, o professor poderá registrá-la.`);
        return;
    }
    
    // ===== CASO 7: Data passada sem registro (cancelada automaticamente) =====
    if (elemento.classList.contains('dia-aula') && !status && dataAula < hoje) {
        alert('❌ Esta aula foi cancelada automaticamente porque não foi registrada pelo professor.');
        return;
    }
    
    console.log('⚠️ Caso não tratado:', { status, podeCancelar, aulaId, isFuturo });
}

// ===== FUNÇÃO PARA VER DETALHES DA AULA (COM TABELA) =====
function verDetalhesAula(aulaId) {
    console.log('📖 Buscando detalhes da aula:', aulaId);
    
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
        .then(result => {
            if (!result.success) {
                alert('❌ Erro: ' + result.message);
                fecharModalDetalhes();
                return;
            }
            
            const aula = result.data;
            const dataHora = new Date(aula.data_hora);
            const data = dataHora.toLocaleDateString('pt-PT');
            const hora = dataHora.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
            
            // Status em português
            let statusTexto = '';
            let statusCor = '';
            switch(aula.status) {
                case 'realizado': 
                    statusTexto = '✅ Realizado'; 
                    statusCor = '#27ae60';
                    break;
                case 'cancelado_aluno': 
                    statusTexto = '❌ Cancelado (aluno)'; 
                    statusCor = '#e74c3c';
                    break;
                case 'cancelado_professor': 
                    statusTexto = '❌ Cancelado (prof)'; 
                    statusCor = '#e67e22';
                    break;
                default:
                    statusTexto = aula.status;
                    statusCor = '#95a5a6';
            }
            
            // Cabeçalho com informações básicas
            let html = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Data</div>
                            <div style="font-weight: 600;">${data}</div>
                        </div>
                        <div>
                            <div style="color: #7f8c8d; font-size: 0.85rem;">Hora</div>
                            <div style="font-weight: 600;">${hora}</div>
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
            
            // Tabela de disciplinas (se houver itens)
            if (aula.itens && aula.itens.length > 0) {
                html += `
                    <h4 style="margin: 20px 0 10px; color: #2c3e50;">
                        <i class="fas fa-graduation-cap" style="margin-right: 8px;"></i>
                        Disciplinas
                    </h4>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <thead style="background: #2c3e50; color: white;">
                                <tr>
                                    <th style="padding: 12px; text-align: left;">Disciplina</th>
                                    <th style="padding: 12px; text-align: left;">Conteúdo Abordado</th>
                                    <th style="padding: 12px; text-align: left;">Dificuldades</th>
                                    <th style="padding: 12px; text-align: left;">Observações</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
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
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                // Fallback para aulas sem itens
                html += `
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #7f8c8d;">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Esta aula não possui disciplinas detalhadas.</p>
                        ${aula.conteudo_abordado ? `
                            <p><strong>Conteúdo:</strong> ${aula.conteudo_abordado}</p>
                        ` : ''}
                        ${aula.dificuldades_identificadas ? `
                            <p><strong>Dificuldades:</strong> ${aula.dificuldades_identificadas}</p>
                        ` : ''}
                    </div>
                `;
            }
            
            // Observações gerais
            if (aula.observacoes_professor) {
                html += `
                    <div style="margin-top: 20px; background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                        <h5 style="margin: 0 0 8px 0; color: #1976d2;">
                            <i class="fas fa-clipboard"></i> Observações Gerais
                        </h5>
                        <p style="margin: 0; color: #2c3e50;">${aula.observacoes_professor}</p>
                    </div>
                `;
            }
            
            document.getElementById('modal-body-conteudo').innerHTML = html;
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro ao carregar detalhes da aula');
            fecharModalDetalhes();
        });
}

function fecharModalDetalhes() {
    fecharModal('modalDetalhes');
}

// ===== FUNÇÃO PARA ABRIR MODAL DE CANCELAMENTO =====
function abrirModalCancelamento(aulaId, data, horario) {
    console.log('📝 Abrindo modal de cancelamento:', { aulaId, data, horario });
    
    if (!aulaId) {
        alert('❌ Erro: ID da aula não identificado');
        return;
    }
    
    cancelamentoData = { aulaId, data, horario };
    
    // Preencher informações no modal
    document.getElementById('cancel_data').textContent = data;
    document.getElementById('cancel_hora').textContent = horario;
    
    // Limpar formulário
    document.getElementById('motivo_cancelamento').value = '';
    document.getElementById('motivo_outro').style.display = 'none';
    document.getElementById('motivo_outro').value = '';
    
    abrirModal('modalCancelarAluno');
}

// ===== FUNÇÃO PARA CONFIRMAR CANCELAMENTO =====
function confirmarCancelamentoAluno() {
    const motivoSelect = document.getElementById('motivo_cancelamento').value;
    const motivoOutro = document.getElementById('motivo_outro').value;
    
    if (!cancelamentoData || !cancelamentoData.aulaId) {
        alert('❌ Erro: Dados do cancelamento não encontrados');
        return;
    }
    
    // Validar motivo
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
    
    if (!confirm(`Tem certeza que deseja cancelar a aula do dia ${cancelamentoData.data}?`)) {
        return;
    }
    
    fecharModal('modalCancelarAluno');
    mostrarLoading('Cancelando aula...');
    
    fetch('processar_cancelamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao: 'cancelar',
            aula_id: cancelamentoData.aulaId,
            tipo: 'aluno',
            motivo: motivo
        })
    })
    .then(response => response.json())
    .then(data => {
        removerLoading();
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(error => {
        removerLoading();
        console.error('Erro:', error);
        alert('❌ Erro ao cancelar aula. Tente novamente.');
    });
}

// ===== FUNÇÕES AUXILIARES =====
function mostrarLoading(mensagem) {
    const loadingMsg = document.createElement('div');
    loadingMsg.id = 'loading';
    loadingMsg.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; display:flex; align-items:center; justify-content:center;';
    loadingMsg.innerHTML = `<div style="background:white; padding:30px; border-radius:10px; text-align:center;"><i class="fas fa-spinner fa-spin fa-3x" style="color:#3498db;"></i><p style="margin-top:20px;">${mensagem}</p></div>`;
    document.body.appendChild(loadingMsg);
}

function removerLoading() {
    const loading = document.getElementById('loading');
    if (loading) loading.remove();
}

// ===== CONFIGURAÇÕES =====
function configurarSelectMotivos() {
    const select = document.getElementById('motivo_cancelamento');
    const outro = document.getElementById('motivo_outro');
    if (select && outro) {
        select.addEventListener('change', () => {
            outro.style.display = select.value === 'Outro' ? 'block' : 'none';
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
            // Atualizar visualmente
            const item = document.querySelector(`.notificacao-item[data-id="${notificacaoId}"]`);
            if (item) {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            }
            
            // Atualizar contador
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
            // Atualizar visualmente
            document.querySelectorAll('.notificacao-item.nao-lida').forEach(item => {
                item.classList.remove('nao-lida');
                item.classList.add('lida');
            });
            
            // Atualizar contador
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

// ===== SEÇÕES =====
function mostrarSecao(id, event) {
    if (event) event.preventDefault();
    document.querySelectorAll('.dashboard-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(id)?.classList.remove('hidden');
    
    document.querySelectorAll('.sidebar ul li a').forEach(l => l.classList.remove('active'));
    if (event?.target) event.target.closest('a')?.classList.add('active');
}

// ===== FUNÇÕES DO MENU MOBILE - CORRIGIDAS =====
function abrirSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        console.log('✅ Sidebar aberta');
    }
}

function fecharSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = 'auto';
        console.log('✅ Sidebar fechada');
    }
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Dashboard do Aluno iniciado');
    console.log('📊 Notificações não lidas:', window.notificacoesNaoLidas);
    
    configurarSelectMotivos();
    
    // ===== MENU MOBILE - CORRIGIDO =====
    const menuToggle = document.getElementById('menuToggle');
    const menuClose = document.getElementById('menuClose');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Botão de abrir menu (hambúrguer)
    if (menuToggle) {
        menuToggle.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🍔 Menu hambúrguer clicado');
            abrirSidebar();
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
            fecharSidebar();
            return false;
        };
        console.log('✅ Botão menu close configurado');
    }
    
    // Overlay (fundo escuro)
    if (overlay) {
        overlay.onclick = function(e) {
            e.preventDefault();
            console.log('🔲 Overlay clicado');
            fecharSidebar();
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
                setTimeout(fecharSidebar, 150);
            }
        });
    });
    
    // Fechar sidebar ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            fecharSidebar();
        }
    });
    
    // Fechar sidebar com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharSidebar();
        }
    });
    
    // Fechar modais com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModalDetalhes();
            fecharModal('modalAula');
            fecharModal('modalCancelarAluno');
        }
    });
    
    // Fechar modal ao clicar fora
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active');
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
    
    // Seção inicial
    const linkBoasVindas = document.getElementById('link-boas-vindas');
    if (linkBoasVindas) {
        setTimeout(() => linkBoasVindas.click(), 100);
    }
    
    // Verificar visibilidade do botão menu em mobile
    if (window.innerWidth <= 768) {
        const menuToggle = document.getElementById('menuToggle');
        if (menuToggle) {
            menuToggle.style.display = 'flex';
        }
    }
});

// DEBUG: Adicionar evento de clique diretamente nos dias (fallback)
setTimeout(() => {
    document.querySelectorAll('.calendario-dia').forEach(dia => {
        dia.removeEventListener('click', window.diaClickHandler);
        dia.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('✅ Clique capturado por fallback!');
            abrirModalDia(this);
        });
    });
    console.log('🔧 Fallback de clique adicionado aos dias do calendário');
}, 500);