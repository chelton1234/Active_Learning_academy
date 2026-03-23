<?php
$result = $conn->query("SELECT * FROM fichas ORDER BY data_submissao DESC");
?>

<h2>Gestão de Fichas de Usuários</h2>

<table>
  <thead>
    <tr>
      <th>Nome</th><th>Idade</th><th>Classe</th><th>Sexo</th><th>Dificuldades</th>
      <th>Pacote</th><th>Província</th><th>Escola</th><th>Internet</th><th>Regime</th>
      <th>Professor</th><th>Aulas Agendadas</th><th>Pacote Confirmado</th><th>Validade</th>
      <th>Aulas Restantes</th><th>Validada?</th><th>Ação</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <form method="POST" action="admin_dashboard.php?view=usuarios">
          <td><?= htmlspecialchars($row['nome']) ?></td>
          <td><?= $row['idade'] ?></td>
          <td><?= htmlspecialchars($row['classe']) ?></td>
          <td><?= htmlspecialchars($row['sexo']) ?></td>
          <td><?= htmlspecialchars($row['dificuldade']) ?></td>
          <td><?= htmlspecialchars($row['pacote']) ?></td>
          <td><?= htmlspecialchars($row['provincia']) ?></td>
          <td><?= htmlspecialchars($row['escola']) ?></td>
          <td><?= $row['internet_casa'] ? 'Sim' : 'Não' ?></td>
          <td>
            <?= $row['regime_presencial'] ? 'Presencial ' : '' ?>
            <?= $row['regime_online'] ? 'Online ' : '' ?>
            <?= $row['regime_hibrido'] ? 'Híbrido ' : '' ?>
          </td>
          <td><input type="text" name="professor" value="<?= htmlspecialchars($row['professor_atribuido']) ?>"></td>
          <td><textarea name="aulas_agendadas"><?= htmlspecialchars($row['aulas_agendadas']) ?></textarea></td>
          <td><input type="text" name="pacote_confirmado" value="<?= htmlspecialchars($row['pacote_confirmado']) ?>"></td>
          <td><input type="date" name="pacote_valido_ate" value="<?= htmlspecialchars($row['pacote_valido_ate']) ?>"></td>
          <td><input type="number" name="aulas_restantes" value="<?= (int)$row['aulas_restantes'] ?>"></td>
          <td><input type="checkbox" name="ficha_validada" <?= $row['ficha_validada'] ? 'checked' : '' ?>></td>
          <td>
            <input type="hidden" name="ficha_id" value="<?= $row['id'] ?>">
            <button class="btn" type="submit">Salvar</button>
          </td>
        </form>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
