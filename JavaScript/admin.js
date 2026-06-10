// admin.js - Painel Administrativo WebTeaching
document.addEventListener('DOMContentLoaded', function() {
    // ========== CONTROLE DE SECÇÕES (MENU LATERAL) ==========
    const menuItems = document.querySelectorAll('.sidebar ul li a');
    const secoes = {
        inicio: document.getElementById('inicio'),
        solicitacoes: document.getElementById('solicitacoes'),
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
                            <a href="#" onclick="redirecionarParaSolicitacoes(${not.id})" class="notificacao-link">Ver pedido →</a>
                        `;
                        notifList.insertBefore(item, notifList.firstChild);
                        if (not.id > ultimoIdNotif) ultimoIdNotif = not.id;
                        badgeCount++;
                    });
                    if (notifBadge) {
                        notifBadge.innerText = badgeCount;
                        notifBadge.style.display = badgeCount ? 'inline-block' : 'none';
                    }
                    const badgeMenu = document.querySelector('.sidebar ul li a[data-secao="solicitacoes"] .badge');
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
    setInterval(atualizarNotificacoes, 20000);
    atualizarNotificacoes();

    window.marcarTodasLidas = function() {
        if (notifBadge) notifBadge.style.display = 'none';
        if (notifList) notifList.innerHTML = '<div class="notificacao-vazia"><i class="fas fa-bell-slash"></i><p>Nenhuma notificação</p></div>';
        const badgeMenu = document.querySelector('.sidebar ul li a[data-secao="solicitacoes"] .badge');
        if (badgeMenu) badgeMenu.innerText = '0';
        // Notificar o servidor (opcional)
        fetch('dashboard_admin.php?ajax=marcar_notificacoes_lidas', { method: 'POST' }).catch(console.warn);
    };

    window.redirecionarParaSolicitacoes = function(pedidoId) {
        ativarSecao('solicitacoes');
        if (notifDropdown) notifDropdown.classList.remove('active');
        const linha = document.querySelector(`#solicitacoes tr[data-pedido-id="${pedidoId}"]`);
        if (linha) linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    // ========== FUNÇÕES DE APROVAÇÃO (MODAL DETALHES + APROVAÇÃO) ==========
    window.verDetalhesSolicitacao = function(pedidoId) {
        // Esta função é definida no HTML para manter a lógica unificada.
        // Se não existir no HTML, pode ser implementada aqui.
        if (typeof window.verDetalhesSolicitacaoInline !== 'undefined') {
            window.verDetalhesSolicitacaoInline(pedidoId);
        } else {
            // fallback: abrir modal de aprovação simples
            document.getElementById('aprovacao_pedido_id').value = pedidoId;
            document.getElementById('modalAprovacao')?.classList.add('active');
        }
    };

    window.aprovarSolicitacao = function() {
        // Esta função está definida no HTML, mas mantemos aqui um fallback
        const pedidoId = document.getElementById('aprovacao_pedido_id')?.value;
        const professorId = document.getElementById('selectProfessorSolicitacao')?.value;
        if (!pedidoId) { alert('Nenhum pedido selecionado.'); return; }
        if (!professorId) { alert('Selecione um professor.'); return; }
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

    // ========== MODAL DE DETALHES DO ALUNO (com calendário) ==========
    window.verDetalhesAluno = function(fichaId) {
        const modal = document.getElementById('modalDetalhesAluno');
        const bodyDiv = document.getElementById('detalhesAlunoBody');
        if (!modal || !bodyDiv) return;
        bodyDiv.innerHTML = '<div class="loading">Carregando dados do aluno...</div>';
        modal.classList.add('active');

        const mesAtual = new Date().getMonth() + 1;
        const anoAtual = new Date().getFullYear();
        fetch(`dashboard_admin.php?ajax=aluno_detalhes&ficha_id=${fichaId}&mes=${mesAtual}&ano=${anoAtual}`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) throw new Error(data.erro);
                const aluno = data.aluno;
                let html = `
                    <div class="info-grid">
                        <div><strong>Nome:</strong> ${escapeHtml(aluno.nome)}</div>
                        <div><strong>Email:</strong> ${escapeHtml(aluno.email)}</div>
                        <div><strong>Contacto:</strong> ${escapeHtml(aluno.contacto) || '—'}</div>
                        <div><strong>Nível:</strong> ${escapeHtml(aluno.nivel_cambridge) || '—'}</div>
                        <div><strong>Pacote:</strong> ${aluno.pacote}</div>
                        <div><strong>Professor:</strong> ${escapeHtml(aluno.professor_atribuido) || '—'}</div>
                        <div><strong>Status Pagamento:</strong> ${aluno.pagamento_status === 'pago' ? '✅ Pago' : '⏳ Pendente'}</div>
                    </div>
                    <hr>
                    <h4><i class="fas fa-calendar-alt"></i> Calendário de Aulas</h4>
                    <div id="calendario-aluno-${fichaId}">Carregando calendário...</div>
                `;
                bodyDiv.innerHTML = html;
                // Carregar calendário
                fetch(`dashboard_admin.php?ajax=calendario_aluno&ficha_id=${fichaId}&mes=${data.mes}&ano=${data.ano}`)
                    .then(res => res.text())
                    .then(calHtml => {
                        document.getElementById(`calendario-aluno-${fichaId}`).innerHTML = calHtml;
                    })
                    .catch(() => document.getElementById(`calendario-aluno-${fichaId}`).innerHTML = '<p>Erro ao carregar calendário.</p>');
            })
            .catch(err => { bodyDiv.innerHTML = '<p class="erro">Erro: ' + err.message + '</p>'; });
    };

    // ========== EXCLUIR ALUNO (REMOVER COMPLETAMENTE) ==========
    window.excluirAluno = function(fichaId, usuarioId, nome) {
        if (!confirm(`Tem certeza que deseja excluir permanentemente o aluno "${nome}"?\n\nTodas as suas aulas, horários, pagamentos e dados serão removidos.`)) return;
        fetch('dashboard_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `acao_excluir_aluno=1&ficha_id=${fichaId}&usuario_id=${usuarioId}`
        })
        .then(res => res.text())
        .then(data => {
            if (data.includes('excluído completamente')) {
                alert('✅ Aluno excluído com sucesso.');
                location.reload();
            } else {
                alert('❌ Erro ao excluir aluno: ' + data);
            }
        })
        .catch(err => alert('Erro: ' + err));
    };

    // ========== FUNÇÕES EXISTENTES (Fichas, Professores) ==========
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

    // Fechar modais
    window.fecharModal = function(modalId) {
        document.getElementById(modalId)?.classList.remove('active');
    };

    // Fechar modal ao clicar no overlay
    window.addEventListener('click', function(e) {
        if (e.target.classList && e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
        }
    });
});