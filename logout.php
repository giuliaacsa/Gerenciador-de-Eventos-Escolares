<?php
session_start();

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Deletar cookies de "lembrar-me" se existirem
if (isset($_COOKIE['lembrar_email'])) {
    setcookie('lembrar_email', '', time() - 3600, '/', '', false, true);
}
if (isset($_COOKIE['lembrar_token'])) {
    setcookie('lembrar_token', '', time() - 3600, '/', '', false, true);
}

// Se deseja destruir a sessão completamente, delete também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();

// Redirecionar para a página inicial
header("Location: index.html");
exit();
?>