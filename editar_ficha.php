<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("⚠️ Acesso negado. Faça login primeiro.");
}

$usuario_id = $_SESSION['usuario_id'];

// ⚡ Receber ID da ficha pela URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("⚠️ ID da ficha inválido.");
}
$ficha_id = (int)$_GET['id'];

$conn = new mysqli("localhost", "root", "", "sistema_login");
if ($conn->connect_error) die("Erro de conexão: " . $conn->connect_error);

// Buscar ficha específica do aluno
$sql = "SELECT * FROM fichas WHERE id = ? AND usuario_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $ficha_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$ficha = $result->fetch_assoc();
$stmt->close();

if (!$ficha) die("⚠️ Nenhuma ficha encontrada para edição.");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Ficha de Inscrição</title>
<link rel="stylesheet" href="Css/FichaAluno.css">
<style>
  #btnPagamento { display: none; } /* botão de pagamento escondido na edição */
</style>
</head>
<body>

<main class="formulario-inscricao">
<form id="formFicha" method="POST" action="atualizar_ficha.php">
    <h2 class="centro">Editar Ficha de Inscrição</h2>
    <!-- ID da ficha vai junto no POST -->
    <input type="hidden" name="ficha_id" value="<?= (int)$ficha['id'] ?>">

    <section class="form-section">
        <h3>Dados Pessoais</h3>
        <div><label>Nome Completo:</label>
            <input name="nome" type="text" required value="<?= htmlspecialchars($ficha['nome']) ?>"></div>
        <div><label>Idade:</label>
            <input name="idade" type="number" required value="<?= (int)$ficha['idade'] ?>"></div>
        <div><label>Classe / Ano:</label>
            <input name="classe" type="text" required value="<?= htmlspecialchars($ficha['classe']) ?>"></div>
        <div>
            <label>Sexo:</label>
            <label><input type="radio" name="sexo" value="m" <?= $ficha['sexo']=='m'?'checked':'' ?>> Masculino</label>
            <label><input type="radio" name="sexo" value="f" <?= $ficha['sexo']=='f'?'checked':'' ?>> Feminino</label>
        </div>
        <div><label>Data de Nascimento:</label>
            <input name="data_nascimento" type="date" value="<?= htmlspecialchars($ficha['data_nascimento']) ?>"></div>
        <div><label>Província:</label>
            <select name="provincia">
            <?php 
            $provincias = ["Maputo Cidade","Maputo Província","Gaza","Inhambane","Sofala","Manica","Tete","Zambézia","Nampula","Niassa","Cabo Delgado"];
            foreach($provincias as $prov){
                $selected = ($ficha['provincia']==$prov)?"selected":"";
                echo "<option $selected>$prov</option>";
            }
            ?>
            </select>
        </div>
        <div><label>Contacto do Encarregado:</label>
            <input name="contacto_encarregado" type="tel" value="<?= htmlspecialchars($ficha['contacto_encarregado']) ?>"></div>
        <div><label>Escola:</label>
            <input name="escola" type="text" value="<?= htmlspecialchars($ficha['escola']) ?>"></div>
        <div>
            <label><input type="checkbox" name="internet_casa" <?= $ficha['internet_casa']?'checked':'' ?>> Tem internet em casa?</label>
        </div>
    </section>

    <section class="form-section">
        <h3>Regime & Pacote</h3>
        <div>
            <label>Nível de Ensino:</label>
            <select name="nivel" required>
                <option value="">Selecionar</option>
                <option value="primary" <?= $ficha['nivel']=='primary'?'selected':'' ?>>Ensino Primário</option>
                <option value="secondary" <?= $ficha['nivel']=='secondary'?'selected':'' ?>>Ensino Secundário</option>
                <option value="cambridge" <?= $ficha['nivel']=='cambridge'?'selected':'' ?>>Cambridge Primary</option>
            </select>
        </div>
        <div>
            <label>Pacote de Aulas:</label>
            <select name="pacote" required>
                <option value="">Selecionar</option>
                <option value="2dias" <?= $ficha['pacote']=='2dias'?'selected':'' ?>>2 dias/semana - 1500 MT</option>
                <option value="3dias" <?= $ficha['pacote']=='3dias'?'selected':'' ?>>3 dias/semana - 2500 MT</option>
                <option value="todos" <?= $ficha['pacote']=='todos'?'selected':'' ?>>Todos os dias - 3500 MT</option>
            </select>
        </div>
        <fieldset>
            <legend>Regime das Aulas</legend>
            <label><input type="checkbox" name="regime_presencial" value="1" <?= $ficha['regime_presencial']?'checked':'' ?>> Presencial</label>
            <label><input type="checkbox" name="regime_online" value="1" <?= $ficha['regime_online']?'checked':'' ?>> Online</label>
            <label><input type="checkbox" name="regime_hibrido" value="1" <?= $ficha['regime_hibrido']?'checked':'' ?>> Ao Domicílio (+1000 MT)</label>
        </fieldset>
        <div>
            <label>Dificuldades:</label>
            <textarea name="dificuldade"><?= htmlspecialchars($ficha['dificuldade']) ?></textarea>
        </div>
    </section>

    <div class="centro">
        <button type="submit" id="btnGuardar">💾 Atualizar Ficha</button>
    </div>
</form>
</main>

</body>
</html>
