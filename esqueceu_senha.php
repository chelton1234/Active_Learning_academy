<?php
session_start();
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];

    $conn = new mysqli("localhost", "root", "", "sistema_login");
    if ($conn->connect_error) {
        die("Erro de conexão: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $_SESSION["recuperar_email"] = $email;
        header("Location: redefinir_senha.php");
        exit;
    } else {
        $mensagem = "❌ Email não encontrado.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link rel="stylesheet" href="Css/Login.css">
</head>
<body>
    <main class="login-container">
        <h2>Recuperar Senha</h2>
        <form method="post" action="esqueceu_senha.php">
            <label for="email">Digite seu email:</label>
            <input type="email" name="email" required>
            <button type="submit">Verificar</button>
            <?php if (!empty($mensagem)): ?>
                <p style="color:red;"><?= $mensagem ?></p>
            <?php endif; ?>
        </form>
    </main>
</body>
</html>
