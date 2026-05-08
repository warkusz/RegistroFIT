<?php
// -----------------------------------------------------------------------------
// logout.php
// Encerra sessão em segurança, limpa cookie e redireciona para login.
// -----------------------------------------------------------------------------
// Abre a sessão ativa para poder encerrá-la corretamente.
session_start();

// Limpa todas as variáveis de sessão.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    // Invalida o cookie de sessão no browser.
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redireciona para login com mensagem de confirmação.
header('Location: login.php?logged_out=1');
exit;
?>
