<?php
// -----------------------------------------------------------------------------
// login.php
// Ecrã de autenticação: valida credenciais e cria sessão de utilizador.
// -----------------------------------------------------------------------------
// Inicia sessão para guardar o estado de autenticação.
session_start();
include('conexao.php');

$erro = '';

$checkPhotoColumn = $conn->query("SHOW COLUMNS FROM utilizadores LIKE 'foto_perfil'");
if ($checkPhotoColumn instanceof mysqli_result) {
    $hasPhotoColumn = $checkPhotoColumn->num_rows > 0;
    $checkPhotoColumn->free();
    if (!$hasPhotoColumn) {
        $conn->query("ALTER TABLE utilizadores ADD COLUMN foto_perfil VARCHAR(255) DEFAULT NULL AFTER senha");
    }
}

if (isset($_POST['btn-login'])) {
    // Processa submissão do formulário de login.
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['password'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha o email e a password.';
    } else {
        // Query segura para evitar SQL Injection
        $stmt = $conn->prepare("SELECT id, nome, email, senha, foto_perfil FROM utilizadores WHERE email = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            $dados = $resultado->fetch_assoc();
            $stmt->close();

            if ($dados) {
                // Aceita hash moderno e mantém compatibilidade com palavras-passe antigas.
                $senhaValida = password_verify($senha, $dados['senha']) || hash_equals($dados['senha'], $senha);

                if ($senhaValida) {
                    // Guarda dados essenciais na sessão para uso nas páginas protegidas.
                    $_SESSION['id'] = $dados['id'];
                    $_SESSION['nome'] = $dados['nome'];
                    $_SESSION['email'] = $dados['email'];
                    $_SESSION['foto_perfil'] = $dados['foto_perfil'] ?? '';

                    header("Location: main.php");
                    exit;
                }
            }

            $erro = 'Email ou password incorretos.';
        } else {
            $erro = 'Erro ao processar o login. Tente novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Login</title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
        <link rel="icon" type="image/png" href="/RegistroFIT-main/images/2307827.png" />
        <link rel="stylesheet" href="/RegistroFIT-main/style.css" />
    </head>

    <body class="d-flex flex-column min-vh-100 text-white">
        <!-- Header público antes da autenticação -->
        <header class="p-3 bg-dark text-white">
            <div class="container-fluid">
                <div
                    class="d-flex flex-wrap align-items-center justify-content-between"
                >
                    <a
                        href="/"
                        class="d-flex align-items-center mb-2 mb-lg-0 text-white text-decoration-none"
                    >
                        <img
                            src="/RegistroFIT-main/images/2307827.png"
                            alt="Workout Planner Logo"
                            width="62"
                            height="60"
                            class="me-2"
                        />
                    </a>
                    <ul
                        class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0"
                    >
                        <li>
                            <a
                                href="index.html"
                                class="nav-link px-2 text-white"
                                >Início</a
                            >
                        </li>
                        <li>
                            <a
                                href="funcoes.html"
                                class="nav-link px-2 text-white"
                                >Funções</a
                            >
                        </li>
                        <li>
                            <a
                                href="sobre.html"
                                class="nav-link px-2 text-white"
                                >Sobre</a
                            >
                        </li>
                    </ul>

                    <div class="text-end">
                        <a href="login.php" class="btn btn-outline-light me-2"
                            >Login</a
                        >
                        <a href="register.php" class="btn btn-warning me-2"
                            >Sign-up</a
                    </div>
                </div>
            </div>
        </header>

        <main
            class="flex-grow-1 d-flex align-items-center justify-content-center w-100"
        >
            <!-- Cartão central com formulário de login -->
            <div
                class="card shadow-lg"
                style="width: 100%; max-width: 400px"
            >
                <div class="card-body p-4">
                    <form action="login.php" method="POST">
    <h1 class="h3 mb-3 fw-normal text-center">Login</h1>
    <?php if (isset($_GET['logged_out'])): ?>
    <div class="alert alert-success py-2" role="alert">
        Sessao terminada com sucesso.
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['session_expired'])): ?>
    <div class="alert alert-warning py-2" role="alert">
        Sessao expirada. Faz login para continuar.
    </div>
    <?php endif; ?>
    <?php if ($erro !== ''): ?>
    <div class="alert alert-danger py-2" role="alert">
        <?php echo htmlspecialchars($erro); ?>
    </div>
    <?php endif; ?>

    <div class="form-floating mb-2">
        <input type="email" name="email" class="form-control" id="floatingInput" placeholder="nome@exemplo.com" required>
        <label for="floatingInput">Email</label>
    </div>

    <div class="form-floating mb-3">
        <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
        <label for="floatingPassword">Password</label>
    </div>

    <button class="btn btn-primary w-100 py-2" type="submit" name="btn-login">
        Entrar
    </button>
</form>
                </div>
            </div>
        </main>

        <footer class="footer mt-auto p-3 py-3 text-white-50 text-center">
            Marcos Costa Projeto PAP - Ano letivo 2025/2026
        </footer>
        <script src="/RegistroFIT-main/page-transitions.js" defer></script>
    </body>
</html>
