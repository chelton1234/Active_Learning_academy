<?php
session_start();

if (!isset($_SESSION["recuperar_email"])) {
    die("Acesso inválido.");
}

$email = $_SESSION["recuperar_email"];
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $novaSenha = $_POST["nova_senha"];
    $confirmarSenha = $_POST["confirmar_senha"];

    if ($novaSenha !== $confirmarSenha) {
        $mensagem = "❌ As senhas não coincidem.";
    } else {
        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $conn = new mysqli("localhost", "root", "", "sistema_login");

        if ($conn->connect_error) {
            die("Erro de conexão: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->bind_param("ss", $senhaHash, $email);

        if ($stmt->execute()) {
            unset($_SESSION["recuperar_email"]);
            header("Location: Login.html?reset=sucesso");
            exit;
        } else {
            $mensagem = "❌ Erro ao atualizar a senha.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link rel="stylesheet" href="Css/Login.css">
</head>
<body>
    <main class="login-container">
        <h2>Redefinir Senha</h2>
        <form method="post" action="redefinir_senha.php">
            <label for="nova_senha">Nova Senha:</label>
            <input type="password" name="nova_senha" required>

            <label for="confirmar_senha">Confirmar Senha:</label>
            <input type="password" name="confirmar_senha" required>

            <button type="submit">Salvar Nova Senha</button>

            <?php if (!empty($mensagem)): ?>
                <p style="color:red;"><?= $mensagem ?></p>
            <?php endif; ?>
        </form>
    </main>
</body>
</html>
