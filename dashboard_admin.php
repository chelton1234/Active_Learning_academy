<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Evita cache após logout
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verifica admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error);

// Garantir que a coluna pacote_valido_ate aceite NULL (para evitar erros de data inválida)
$conn->query("ALTER TABLE fichas MODIFY pacote_valido_ate DATE NULL");

$mensagem = "";
$mensagem_professor = "";

// ==================== GESTÃO DE PROFESSORES ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_professor'])) {
    $acao_professor = $_POST['acao_professor'];

    if ($acao_professor === 'adicionar') {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = !empty($_POST['senha']) ? password_hash($_POST['senha'], PASSWORD_DEFAULT) : null;
        $especialidade = trim($_POST['especialidade']);
        $telefone = trim($_POST['telefone']);
        $disponivel = $_POST['disponivel'] === 'sim' ? 'sim' : 'nao';

        // Verifica se email já existe
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        if($stmt_check->num_rows > 0){
            $mensagem_professor = "Erro: email já cadastrado!";
            $stmt_check->close();
        } else {
            $stmt_check->close();

            // Cria usuário com tipo docente
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'docente')");
            $stmt->bind_param("sss", $nome, $email, $senha);
            if ($stmt->execute()) {
                $usuario_id = $stmt->insert_id;
                $stmt->close();

                // Cria registro de professor
                $stmt2 = $conn->prepare("INSERT INTO professores (usuario_id, especialidade, telefone, disponivel, criado_em) VALUES (?, ?, ?, ?, NOW())");
                $stmt2->bind_param("isss", $usuario_id, $especialidade, $telefone, $disponivel);
                $stmt2->execute();
                $stmt2->close();

                $mensagem_professor = "Professor adicionado com sucesso!";
            } else {
                $mensagem_professor = "Erro ao criar usuário: " . $stmt->error;
                $stmt->close();
            }
        }
    }

    elseif ($acao_professor === 'excluir' && !empty($_POST['professor_id'])) {
        $professor_id = (int)$_POST['professor_id'];

        $res = $conn->query("SELECT usuario_id FROM professores WHERE id=$professor_id");
        if ($res && $res->num_rows > 0) {
            $usuario_id = $res->fetch_assoc()['usuario_id'];

            $conn->query("DELETE FROM professores WHERE id=$professor_id");
            $conn->query("DELETE FROM usuarios WHERE id=$usuario_id");

            $mensagem_professor = "Professor excluído com sucesso!";
        }
    }

    elseif ($acao_professor === 'editar' && !empty($_POST['professor_id'])) {
        $professor_id = (int)$_POST['professor_id'];
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = !empty($_POST['senha']) ? password_hash($_POST['senha'], PASSWORD_DEFAULT) : null;
        $especialidade = trim($_POST['especialidade']);
        $telefone = trim($_POST['telefone']);
        $disponivel = $_POST['disponivel'] === 'sim' ? 'sim' : 'nao';

        // Atualiza usuário
        if ($senha) {
            $stmt = $conn->prepare("UPDATE usuarios u JOIN professores p ON u.id=p.usuario_id 
                                    SET u.nome=?, u.email=?, u.senha=? WHERE p.id=?");
            $stmt->bind_param("sssi", $nome, $email, $senha, $professor_id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios u JOIN professores p ON u.id=p.usuario_id 
                                    SET u.nome=?, u.email=? WHERE p.id=?");
            $stmt->bind_param("ssi", $nome, $email, $professor_id);
        }
        $stmt->execute();
        $stmt->close();

        // Atualiza professor
        $stmt2 = $conn->prepare("UPDATE professores SET especialidade=?, telefone=?, disponivel=? WHERE id=?");
        $stmt2->bind_param("sssi", $especialidade, $telefone, $disponivel, $professor_id);
        $stmt2->execute();
        $stmt2->close();

        $mensagem_professor = "Professor atualizado com sucesso!";
    }
}

// ==================== LISTAS ====================

// Listar professores para combobox
$result_professores_combo = $conn->query("SELECT p.id AS professor_id, u.nome 
    FROM professores p JOIN usuarios u ON u.id=p.usuario_id ORDER BY u.nome ASC");

// ========== ATUALIZAÇÃO DE FICHA (CORRIGIDA DEFINITIVAMENTE) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ficha_id']) && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    $professor = $_POST['professor'] ?? '';
    $aulas_agendadas = $_POST['aulas_agendadas'] ?? '';
    $pacote_confirmado = substr($_POST['pacote_confirmado'] ?? '', 0, 50);
    $aulas_restantes = (int)($_POST['aulas_restantes'] ?? 0);
    $ficha_validada = isset($_POST['ficha_validada']) ? 1 : 0;
    $ficha_id = (int)$_POST['ficha_id'];

    // --- VALIDAÇÃO DEFINITIVA DA DATA ---
    $pacote_valido_ate = null;
    $data_raw = trim($_POST['pacote_valido_ate'] ?? '');
    
    // Log para debug (verá no log do servidor)
    error_log("[DEBUG] Data recebida: '" . $data_raw . "'");

    // Só aceita se estiver exatamente no formato YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_raw)) {
        $date = DateTime::createFromFormat('Y-m-d', $data_raw);
        if ($date && $date->format('Y-m-d') === $data_raw) {
            $pacote_valido_ate = $data_raw;
        }
    }

    // Se ainda for nulo e for um ano isolado (ex: 2026) -> ignora (mantém NULL)
    if ($pacote_valido_ate === null && preg_match('/^\d{4}$/', $data_raw)) {
        error_log("[DEBUG] Ano isolado ignorado: $data_raw");
        $pacote_valido_ate = null;
    }

    // --- QUERY CONDICIONAL ---
    if ($pacote_valido_ate === null) {
        $stmt = $conn->prepare("UPDATE fichas 
            SET professor_atribuido=?, aulas_agendadas=?, pacote_confirmado=?, 
                pacote_valido_ate = NULL, aulas_restantes=?, ficha_validada=? 
            WHERE id=?");
        $stmt->bind_param("sssiii", $professor, $aulas_agendadas, $pacote_confirmado, $aulas_restantes, $ficha_validada, $ficha_id);
    } else {
        $stmt = $conn->prepare("UPDATE fichas 
            SET professor_atribuido=?, aulas_agendadas=?, pacote_confirmado=?, 
                pacote_valido_ate = ?, aulas_restantes=?, ficha_validada=? 
            WHERE id=?");
        $stmt->bind_param("ssssiii", $professor, $aulas_agendadas, $pacote_confirmado, $pacote_valido_ate, $aulas_restantes, $ficha_validada, $ficha_id);
    }

    if ($stmt->execute()) {
        $mensagem = "Ficha atualizada com sucesso.";
    } else {
        $mensagem = "Erro ao atualizar ficha: " . $stmt->error;
        error_log("[ERRO] " . $stmt->error);
    }
    $stmt->close();
}

// Listar fichas
$result = $conn->query("SELECT * FROM fichas ORDER BY data_submissao DESC");

// Listar professores para tabela de gestão
$result_professores = $conn->query("SELECT p.id AS professor_id, u.nome, u.email, p.especialidade, p.telefone, p.disponivel, p.criado_em 
FROM professores p JOIN usuarios u ON u.id=p.usuario_id ORDER BY p.criado_em DESC");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Painel do Administrador</title>
<link rel="stylesheet" href="Css/admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.btn.excluir{background:#e74c3c;color:#fff;border:none;padding:5px 10px;margin-left:5px;cursor:pointer;border-radius:4px;font-size:0.9em;}
.btn.excluir:hover{background:#c0392b;}
.btn{background:#3498db;color:#fff;border:none;padding:5px 10px;cursor:pointer;border-radius:4px;}
.btn:hover{background:#2980b9;}
table{width:100%;border-collapse:collapse;font-size:0.9em;}
th,td{border:1px solid #ddd;padding:8px;vertical-align:middle;}
th{background:#f2f2f2;}
section.section{display:none;}
section.section.active{display:block;}
.alert{padding:10px;background:#2ecc71;color:#fff;margin-bottom:10px;border-radius:4px;}
.alert.erro{background:#e74c3c;}
</style>
<script>
function mostrarSecao(secao){
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    document.getElementById('boas_vindas').style.display='none';
    if(secao==='usuarios') document.getElementById('secao_usuarios').classList.add('active');
    else if(secao==='professores') document.getElementById('secao_professores').classList.add('active');
}

function excluirFicha(fichaId,botao){
    if(!confirm("Deseja realmente excluir esta ficha?")) return;
    const linha=botao.closest('tr');
    fetch('excluir_ficha.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ficha_id='+fichaId
    }).then(res=>res.text()).then(res=>{
        if(res.trim()==='ok') linha.innerHTML='<td colspan="21" style="text-align:center; color:red;">Ficha excluída<\/td>';
        else alert('Erro ao excluir ficha: '+res);
    }).catch(()=>alert('Erro ao excluir ficha.'));
}
</script>
</head>
<body>

<div class="header"><div><strong>WebTeaching</strong> - Reforço Cambridge</div><div class="top-user">Admin: <?=htmlspecialchars($_SESSION['usuario_nome']??'Administrador')?> 👤</div></div>
<div class="sidebar">
<h3>MENU DO ADMIN</h3>
<ul>
<li><a href="#" onclick="mostrarSecao('usuarios');return false;">Gestão de Usuários</a></li>
<li><a href="#" onclick="mostrarSecao('professores');return false;">Gestão de Professores</a></li>
<li><a href="logout.php">Sair</a></li>
</ul>
</div>

<div class="main">
<div id="boas_vindas" class="boas-vindas section active">
<h2>Bem-vindo ao Painel do Administrador</h2>
<p>Use o menu à esquerda para navegar entre as opções.</p>
</div>

<!-- Seção Fichas -->
<div id="secao_usuarios" class="section">
<h2>Fichas Submetidas</h2>
<?php if(!empty($mensagem)) echo '<div class="alert">'.htmlspecialchars($mensagem).'</div>'; ?>
<table>
<thead>
<tr>
<th>Nome</th><th>Idade</th><th>Classe</th><th>Sexo</th><th>Dificuldades</th>
<th>Pacote</th><th>Província</th><th>Escola</th><th>Internet</th><th>Regime</th>
<th>Professor</th><th>Aulas</th><th>Confirmado</th><th>Validade</th>
<th>Restantes</th><th>Situação</th><th>Valor Pago</th><th>Data Pagamento</th>
<th>Recibo</th><th>Validada?</th><th>Ação</th>
</tr>
</thead>
<tbody>
<?php while($row=$result->fetch_assoc()): ?>
<tr>
<form method="POST">
<td><?=htmlspecialchars($row['nome'])?></td>
<td><?=$row['idade']?></td>
<td><?=htmlspecialchars($row['classe'])?></td>
<td><?=htmlspecialchars($row['sexo'])?></td>
<td><?=htmlspecialchars($row['dificuldade'])?></td>
<td><?=htmlspecialchars($row['pacote'])?></td>
<td><?=htmlspecialchars($row['provincia'])?></td>
<td><?=htmlspecialchars($row['escola'])?></td>
<td><?=$row['internet_casa']?'Sim':'Não'?></td>
<td><?=$row['regime_presencial']?'Presencial ':''?><?=$row['regime_online']?'Online ':''?><?=$row['regime_hibrido']?'Híbrido ':''?></td>

<td>
<select name="professor" required>
<option value="">-- Selecionar --</option>
<?php
$result_professores_combo->data_seek(0);
while($prof = $result_professores_combo->fetch_assoc()):
$selected = ($row['professor_atribuido']==$prof['nome'])?'selected':'';
echo "<option value=\"".htmlspecialchars($prof['nome'])."\" $selected>".htmlspecialchars($prof['nome'])."</option>";
endwhile;
?>
</select>
</td>

<td><textarea name="aulas_agendadas"><?=htmlspecialchars($row['aulas_agendadas'])?></textarea></td>

<td>
<select name="pacote_confirmado" required>
<option value="Não" <?=$row['pacote_confirmado']=='Não'?'selected':''?>>Não</option>
<option value="Sim" <?=$row['pacote_confirmado']=='Sim'?'selected':''?>>Sim</option>
<option value="Pacote Bronze" <?=$row['pacote_confirmado']=='Pacote Bronze'?'selected':''?>>Pacote Bronze</option>
<option value="Pacote Prata" <?=$row['pacote_confirmado']=='Pacote Prata'?'selected':''?>>Pacote Prata</option>
<option value="Pacote Ouro" <?=$row['pacote_confirmado']=='Pacote Ouro'?'selected':''?>>Pacote Ouro</option>
</select>
</td>

<td><input type="date" name="pacote_valido_ate" value="<?=!empty($row['pacote_valido_ate']) ? htmlspecialchars($row['pacote_valido_ate']) : ''?>"></td>
<td><input type="number" name="aulas_restantes" value="<?= (int)$row['aulas_restantes'] ?>"></td>
<td><?=$row['pagamento_status']==='pago'?'✅ Pago':'⏳ Pendente'?></td>
<td><?=$row['valor_pago']?number_format($row['valor_pago'],2).' MT':'N/A'?></td>
<td><?=$row['data_pagamento']?date("d/m/Y H:i",strtotime($row['data_pagamento'])):'—'?></td>
<td><?php if(!empty($row['recibo_pdf'])): ?><a href="<?=htmlspecialchars($row['recibo_pdf'])?>" target="_blank">📄 Abrir</a><?php else: ?>—<?php endif;?></td>
<td><input type="checkbox" name="ficha_validada" <?=$row['ficha_validada']?'checked':''?>></td>
<td>
<input type="hidden" name="ficha_id" value="<?=$row['id']?>">
<input type="hidden" name="acao" value="salvar">
<button class="btn" type="submit">Salvar</button>
<button type="button" class="btn excluir" onclick="excluirFicha(<?=$row['id']?>,this)"><i class="fas fa-trash"></i> Excluir</button>
</td>
</form>
</tr>
<?php endwhile;?>
</tbody>
</table>
</div>

<!-- Seção Professores -->
<div id="secao_professores" class="section">
<h2>Gestão de Professores</h2>
<?php if(!empty($mensagem_professor)) echo '<div class="alert '.(strpos($mensagem_professor,'Erro')!==false?'erro':'').'">'.htmlspecialchars($mensagem_professor).'</div>'; ?>

<form method="POST" id="form_professor" style="margin-bottom:20px;">
<h3 id="titulo_form_professor">Adicionar Professor</h3>
<input type="hidden" name="acao_professor" value="adicionar" id="acao_form">
<input type="hidden" name="professor_id" value="" id="professor_id_form">
<input type="text" name="nome" placeholder="Nome" required id="nome_form">
<input type="email" name="email" placeholder="Email" required id="email_form">
<input type="password" name="senha" placeholder="Senha (deixe em branco para manter)" id="senha_form">
<input type="text" name="especialidade" placeholder="Especialidade" required id="especialidade_form">
<input type="text" name="telefone" placeholder="Telefone" required id="telefone_form">
<select name="disponivel" required id="disponivel_form">
<option value="sim">Sim</option>
<option value="nao">Não</option>
</select>
<button type="submit" class="btn" id="btn_form_professor"><i class="fas fa-plus"></i> Adicionar Professor</button>
</form>

<table>
<thead>
<tr>
<th>Nome</th><th>Email</th><th>Especialidade</th><th>Telefone</th><th>Disponível</th><th>Criado Em</th><th>Ações</th>
</tr>
</thead>
<tbody>
<?php while($row=$result_professores->fetch_assoc()): ?>
<tr>
<td><?=htmlspecialchars($row['nome'])?></td>
<td><?=htmlspecialchars($row['email'])?></td>
<td><?=htmlspecialchars($row['especialidade'])?></td>
<td><?=htmlspecialchars($row['telefone'])?></td>
<td><?=htmlspecialchars($row['disponivel'])?></td>
<td><?=date("d/m/Y H:i",strtotime($row['criado_em']))?></td>
<td>
<button type="button" class="btn" onclick="editarProfessor(<?= $row['professor_id'] ?>,'<?= htmlspecialchars(addslashes($row['nome'])) ?>','<?= htmlspecialchars(addslashes($row['email'])) ?>','<?= htmlspecialchars(addslashes($row['especialidade'])) ?>','<?= htmlspecialchars(addslashes($row['telefone'])) ?>','<?= $row['disponivel'] ?>')">
<i class="fas fa-pen"></i> Editar
</button>
<form method="POST" style="display:inline;">
<input type="hidden" name="acao_professor" value="excluir">
<input type="hidden" name="professor_id" value="<?=$row['professor_id']?>">
<button type="submit" class="btn excluir"><i class="fas fa-trash"></i> Excluir</button>
</form>
</td>
</tr>
<?php endwhile;?>
</tbody>
</table>

<script>
function editarProfessor(id,nome,email,especialidade,telefone,disponivel){
    document.getElementById('titulo_form_professor').innerText='Editar Professor';
    document.getElementById('acao_form').value='editar';
    document.getElementById('professor_id_form').value=id;
    document.getElementById('nome_form').value=nome;
    document.getElementById('email_form').value=email;
    document.getElementById('senha_form').value='';
    document.getElementById('especialidade_form').value=especialidade;
    document.getElementById('telefone_form').value=telefone;
    document.getElementById('disponivel_form').value=disponivel;
    document.getElementById('btn_form_professor').innerHTML='<i class="fas fa-save"></i> Salvar Alterações';
}
</script>
</div>

</div>
</body>
</html>