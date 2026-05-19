// admin.js - Painel Administrativo WebTeaching
document.addEventListener('DOMContentLoaded', function() {
    // ========== CONTROLE DE SECÇÕES (MENU LATERAL) ==========
    const menuItems = document.querySelectorAll('.sidebar ul li a');
    const secoes = {
        inicio: document.getElementById('inicio'),
        aprovacoes: document.getElementById('aprovacoes'),
        alunos_activos: document.getElementById('alunos_activos'),
        professores: document.getElementById('professores'),
        fichas_antigas: document.getElementById('fichas_antigas')
    };

    function ativarSecao(secaoId) {
        Object.values(secoes).forEach(sec => sec && sec.classList.remove('active'));
        if (secoes[secaoId]) secoes[secaoId].classList.add('active');
        menuItems.forEach(item => item.classList.remove('active'));
        const activeItem = document.querySelector(`.sidebar ul li a[data-secao="${secaoId}"]`);
        if (activeItem) activeItem.classList.add('active');
    }

    menuItems.forEach(item => {
        const secaoId = item.getAttribute('data-secao');
        if (secaoId && secoes[secaoId]) {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                ativarSecao(secaoId);
            });
        }
    });

    // ========== MENU MOBILE ==========
    const menuToggle = document.getElementById('menuToggle');
    const menuClose = document.getElementById('menuClose');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function toggleMenu(open) {
        if (open) {
            sidebar?.classList.add('active');
            overlay?.classList.add('active');
        } else {
            sidebar?.classList.remove('active');
            overlay?.classList.remove('active');
        }
    }
    if (menuToggle) menuToggle.addEventListener('click', () => toggleMenu(true));
    if (menuClose) menuClose.addEventListener('click', () => toggleMenu(false));
    if (overlay) overlay.addEventListener('click', () => toggleMenu(false));

    // ========== NOTIFICAÇÕES (SINO) COM POLLING ==========
    const notifBtn = document.querySelector('.notificacoes-btn');
    const notifDropdown = document.getElementById('notificacoesDropdown');
    const notifBadge = document.querySelector('.notificacoes-badge');
    const notifList = document.getElementById('notifList');
    let ultimoIdNotif = 0;

    function atualizarNotificacoes() {
        fetch(`dashboard_admin.php?ajax=notificacoes&ultimo_id=${ultimoIdNotif}`)
            .then(res => res.json())
            .then(data => {
                if (data.novas && data.novas.length) {
                    if (!notifList) return;
                    let badgeCount = notifBadge ? parseInt(notifBadge.innerText) || 0 : 0;
                    data.novas.forEach(not => {
                        const item = document.createElement('div');
                        item.className = 'notificacao-item nao-lida';
                        item.setAttribute('data-id', not.id);
                        item.innerHTML = `
                            <div class="notificacao-titulo">Novo pedido pendente</div>
                            <div class="notificacao-mensagem">${escapeHtml(not.nome)} submeteu um pedido de explicador.</div>
                            <div class="notificacao-data">${formatarData(not.data_submissao)}</div>
                            <a href="#" onclick="redirecionarParaAprovacoes(${not.id})" class="notificacao-link">Ver pedido →</a>
                        `;
                        notifList.insertBefore(item, notifList.firstChild);
                        if (not.id > ultimoIdNotif) ultimoIdNotif = not.id;
                        badgeCount++;
                    });
                    if (notifBadge) {
                        notifBadge.innerText = badgeCount;
                        notifBadge.style.display = badgeCount ? 'inline-block' : 'none';
                    }
                    // Atualizar badge do menu "Aprovações"
                    const badgeMenu = document.querySelector('.sidebar ul li a[data-secao="aprovacoes"] .badge');
                    if (badgeMenu) badgeMenu.innerText = badgeCount;
                }
            })
            .catch(err => console.warn('Polling error', err));
    }

    if (notifBtn) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (notifDropdown) notifDropdown.classList.toggle('active');
        });
        document.addEventListener('click', () => {
            if (notifDropdown) notifDropdown.classList.remove('active');
        });
    }
    // Iniciar polling a cada 20 segundos
    setInterval(atualizarNotificacoes, 20000);
    atualizarNotificacoes();

    window.marcarTodasLidas = function() {
        if (notifBadge) notifBadge.style.display = 'none';
        if (notifList) notifList.innerHTML = '<div class="notificacao-vazia"><i class="fas fa-bell-slash"></i><p>Nenhuma notificação</p></div>';
        const badgeMenu = document.querySelector('.sidebar ul li a[data-secao="aprovacoes"] .badge');
        if (badgeMenu) badgeMenu.innerText = '0';
        // Opcional: enviar requisição para marcar como lidas no servidor
    };

    window.redirecionarParaAprovacoes = function(pedidoId) {
        ativarSecao('aprovacoes');
        if (notifDropdown) notifDropdown.classList.remove('active');
        // Opcional: rolar até o pedido na tabela
        const linha = document.querySelector(`#aprovacoes tr[data-pedido-id="${pedidoId}"]`);
        if (linha) linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // ========== MODAL DE APROVAÇÃO ==========
    window.abrirModalAprovacao = function(pedidoId) {
        document.getElementById('aprovacao_pedido_id').value = pedidoId;
        document.getElementById('modalAprovacao').classList.add('active');
    };

    window.confirmarAprovacao = function() {
        const pedidoId = document.getElementById('aprovacao_pedido_id').value;
        const professorId = document.getElementById('aprovacao_professor_id').value;
        if (!professorId) {
            alert('Selecione um professor para atribuir ao aluno.');
            return;
        }
        fetch('aprovar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pedido_id: pedidoId, professor_id: professorId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                alert('✅ ' + data.mensagem);
                location.reload();
            } else {
                alert('❌ Erro: ' + data.mensagem);
            }
        })
        .catch(err => alert('Erro ao aprovar: ' + err));
    };

    // ========== MODAL DE DETALHES DO PEDIDO (APROVAÇÕES) ==========
    window.verDetalhesPedido = function(pedidoId) {
        fetch(`detalhes_pedido.php?id=${pedidoId}`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) return alert(data.erro);
                const html = `
                    <p><strong>ID:</strong> ${data.id}</p>
                    <p><strong>Data:</strong> ${formatarData(data.data_submissao)}</p>
                    <p><strong>Nome:</strong> ${escapeHtml(data.nome)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(data.email)}</p>
                    <p><strong>Contacto:</strong> ${escapeHtml(data.contacto)}</p>
                    <p><strong>Localização:</strong> ${escapeHtml(data.localizacao) || '—'}</p>
                    <p><strong>Nível Cambridge:</strong> ${escapeHtml(data.nivel_cambridge)}</p>
                    <p><strong>Tipo de aula:</strong> ${data.tipo_aula === 'presencial' ? 'Presencial' : 'Ao domicílio'}</p>
                    <p><strong>Pacote:</strong> ${data.pacote}</p>
                    <p><strong>Total:</strong> ${data.preco_total} MT</p>
                    <p><strong>Dias da semana:</strong> ${escapeHtml(data.dias_semana)}</p>
                    <p><strong>Horário:</strong> ${escapeHtml(data.horario)}</p>
                    <p><strong>Observações:</strong> ${escapeHtml(data.observacoes) || 'Nenhuma'}</p>
                `;
                document.getElementById('detalhesPedidoBody').innerHTML = html;
                document.getElementById('modalDetalhesPedido').classList.add('active');
            })
            .catch(err => alert('Erro ao carregar detalhes: ' + err));
    };

    // ========== MODAL DE DETALHES DO ALUNO (com calendário) ==========
    window.verDetalhesAluno = function(fichaId) {
        const modal = document.getElementById('modalDetalhesAluno');
        const bodyDiv = document.getElementById('detalhesAlunoBody');
        bodyDiv.innerHTML = '<div class="loading">Carregando dados do aluno...</div>';
        modal.classList.add('active');

        // Carregar dados do aluno e calendário
        const mesAtual = new Date().getMonth() + 1;
        const anoAtual = new Date().getFullYear();
        fetch(`detalhes_aluno_ajax.php?ficha_id=${fichaId}&mes=${mesAtual}&ano=${anoAtual}`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) {
                    bodyDiv.innerHTML = `<p class="erro">${data.erro}</p>`;
                    return;
                }
                const aluno = data.aluno;
                let html = `
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">Nome:</span><span class="info-value">${escapeHtml(aluno.nome)}</span></div>
                        <div class="info-item"><span class="info-label">Email:</span><span class="info-value">${escapeHtml(aluno.email)}</span></div>
                        <div class="info-item"><span class="info-label">Contacto:</span><span class="info-value">${escapeHtml(aluno.contacto)}</span></div>
                        <div class="info-item"><span class="info-label">Nível:</span><span class="info-value">${escapeHtml(aluno.nivel_cambridge)}</span></div>
                        <div class="info-item"><span class="info-label">Pacote:</span><span class="info-value">${aluno.pacote}</span></div>
                        <div class="info-item"><span class="info-label">Professor:</span><span class="info-value">${escapeHtml(aluno.professor_atribuido) || '—'}</span></div>
                        <div class="info-item"><span class="info-label">Status Pagamento:</span><span class="info-value">${aluno.pagamento_status === 'pago' ? '✅ Pago' : '⏳ Pendente'}</span></div>
                    </div>
                    <hr>
                    <h4><i class="fas fa-calendar-alt"></i> Calendário de Aulas</h4>
                    <div class="calendario-navegacao" data-ficha-id="${fichaId}">
                        <button class="btn-navegacao" data-mes="${data.mes-1 <= 0 ? 12 : data.mes-1}" data-ano="${data.mes-1 <= 0 ? data.ano-1 : data.ano}"><i class="fas fa-chevron-left"></i> Mês anterior</button>
                        <span class="mes-atual">${mesesNomes[data.mes-1]} ${data.ano}</span>
                        <button class="btn-navegacao" data-mes="${data.mes+1 > 12 ? 1 : data.mes+1}" data-ano="${data.mes+1 > 12 ? data.ano+1 : data.ano}">Próximo mês <i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div id="calendario-aluno-${fichaId}" class="calendario-container">${data.calendario_html || '<p>Carregando calendário...</p>'}</div>
                `;
                bodyDiv.innerHTML = html;

                // Adicionar eventos de navegação do calendário
                document.querySelectorAll('.btn-navegacao').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        const novoMes = parseInt(this.getAttribute('data-mes'));
                        const novoAno = parseInt(this.getAttribute('data-ano'));
                        carregarCalendarioAluno(fichaId, novoMes, novoAno, bodyDiv);
                    });
                });
            })
            .catch(err => {
                console.error(err);
                bodyDiv.innerHTML = '<p class="erro">Erro ao carregar dados do aluno.</p>';
            });
    };

    function carregarCalendarioAluno(fichaId, mes, ano, container) {
        fetch(`detalhes_aluno_ajax.php?ficha_id=${fichaId}&mes=${mes}&ano=${ano}`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) return;
                const calDiv = document.querySelector(`#calendario-aluno-${fichaId}`);
                if (calDiv) calDiv.innerHTML = data.calendario_html || '<p>Sem aulas neste mês.</p>';
                // Atualizar título do mês
                const mesSpan = container.querySelector('.mes-atual');
                if (mesSpan) mesSpan.innerText = `${mesesNomes[mes-1]} ${ano}`;
                // Atualizar botões de navegação
                const btnPrev = container.querySelector('.btn-navegacao:first-child');
                const btnNext = container.querySelector('.btn-navegacao:last-child');
                if (btnPrev) {
                    const prevMes = mes-1 <= 0 ? 12 : mes-1;
                    const prevAno = mes-1 <= 0 ? ano-1 : ano;
                    btnPrev.setAttribute('data-mes', prevMes);
                    btnPrev.setAttribute('data-ano', prevAno);
                }
                if (btnNext) {
                    const nextMes = mes+1 > 12 ? 1 : mes+1;
                    const nextAno = mes+1 > 12 ? ano+1 : ano;
                    btnNext.setAttribute('data-mes', nextMes);
                    btnNext.setAttribute('data-ano', nextAno);
                }
            })
            .catch(err => console.error(err));
    }

    // ========== FUNÇÕES EXISTENTES ==========
    window.excluirFicha = function(fichaId, btn) {
        if (!confirm('Excluir esta ficha permanentemente?')) return;
        fetch('excluir_ficha.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ficha_id=' + fichaId
        })
        .then(res => res.text())
        .then(res => {
            if (res.trim() === 'ok') btn.closest('tr').remove();
            else alert('Erro ao excluir: ' + res);
        })
        .catch(() => alert('Erro de rede.'));
    };

    window.editarFicha = function(fichaId) {
        alert(`Edição avançada será implementada em breve. ID: ${fichaId}`);
    };

    // ========== FUNÇÕES AUXILIARES ==========
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function formatarData(dataStr) {
        const d = new Date(dataStr);
        return d.toLocaleDateString('pt-PT') + ' ' + d.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
    }

    const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    // Fechar modais
    window.fecharModal = function(modalId) {
        document.getElementById(modalId).classList.remove('active');
    };
    window.addEventListener('click', function(e) {
        if (e.target.classList && e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
        }
    });
});