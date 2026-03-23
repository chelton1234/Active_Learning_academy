<?php
// Inicia a sessão para poder destruí-la
session_start();

// Remove todas as variáveis de sessão
session_unset();

// Destroi a sessão completamente
session_destroy();

// Redireciona para a página de login
header("Location: home.html");
exit;
