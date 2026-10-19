<?php
session_start();

// Limpa todas as variáveis de sessão ativas
$_SESSION = array();

// Remove o cookie da sessão se estiver ativo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói oficialmente a sessão no servidor
session_destroy();

// Redireciona o utilizador para o ecrã de início de sessão
header("Location: index.php");
exit;
?>