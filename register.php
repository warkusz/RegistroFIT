<?php
// -----------------------------------------------------------------------------
// register.php
// Criação de conta: valida campos, impede emails repetidos e guarda password
// com hash para segurança.
// -----------------------------------------------------------------------------
// Ligação à BD para validar e criar novos utilizadores.
include('conexao.php');

$erro = '';
$sucesso = '';

if (isset($_POST['btn-registar'])) {
    // Processa registo com validação básica e verificação de email único.
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['password'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } else {
        // Verifica se o email já existe
        $stmtCheck = $conn->prepare("SELECT id FROM utilizadores WHERE email = ? LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("s", $email);
            $stmtCheck->execute();
            $stmtCheck->store_result();
            $jaExiste = $stmtCheck->num_rows > 0;
            $stmtCheck->close();

            if ($jaExiste) {
                $erro = 'Este email já está registado.';
            } else {
                // Palavra-passe é sempre guardada em hash por segurança.
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmtInsert = $conn->prepare("INSERT INTO utilizadores (nome, email, senha) VALUES (?, ?, ?)");

                if ($stmtInsert) {
                    $stmtInsert->bind_param("sss", $nome, $email, $senhaHash);
                    if ($stmtInsert->execute()) {
                        $sucesso = 'Conta criada com sucesso! Faça login.';
                    } else {
                        $erro = 'Erro ao criar conta. Tente novamente.';
                    }
                    $stmtInsert->close();
                } else {
                    $erro = 'Erro ao preparar o registo.';
                }
            }
        } else {
            $erro = 'Erro ao validar o email.';
        }
    }
}
?>

<!doctype html>
<html lang="pt">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Registar Conta - PAP</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="icon" type="image/png" href="images/2307827.png" />
        <link rel="stylesheet" href="style.css" />
    </head>

    <body class="d-flex flex-column min-vh-100 bg-dark text-white">
         <!-- Header público do site -->
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
                            src="images/2307827.png"
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


        <main class="flex-grow-1 d-flex align-items-center justify-content-center w-100">
            <!-- Formulário de criação de conta -->
            <div class="card shadow-lg" style="width: 100%; max-width: 400px">
                <div class="card-body p-4">
                    <form id="registerForm" method="POST" action="register.php">
                        <h1 class="h3 mb-3 fw-normal text-center">Criar Conta</h1>
                        <?php if ($erro !== ''): ?>
                        <div class="alert alert-danger py-2" role="alert">
                            <?php echo htmlspecialchars($erro); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($sucesso !== ''): ?>
                        <div class="alert alert-success py-2" role="alert">
                            <?php echo htmlspecialchars($sucesso); ?>
                        </div>
                        <?php endif; ?>

                        <div class="form-floating mb-2">
                            <input type="text" name="nome" class="form-control" id="regNome" placeholder="Nome" required />
                            <label for="regNome">Nome</label>
                        </div>

                        <div class="form-floating mb-2">
                            <input type="email" name="email" class="form-control" id="regEmail" placeholder="Email" required />
                            <label for="regEmail">Email</label>
                        </div>

                        <div class="form-floating mb-2">
                            <input type="password" name="password" class="form-control" id="regPassword" placeholder="Palavra-passe" required />
                            <label for="regPassword">Palavra-passe</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="regConfirmPassword" placeholder="Confirmar" required />
                            <label for="regConfirmPassword">Confirmar Palavra-passe</label>
                        </div>

                        <button class="btn btn-success w-100 py-2" type="submit" name="btn-registar">
                            Registar
                        </button>
                    </form>
                </div>
            </div>
        </main>

        <footer class="footer mt-auto p-3 text-white-50 text-center">
            Marcos Costa - Projeto PAP - Ano letivo 2025/2026
        </footer>
        <script src="page-transitions.js" defer></script>
    </body>
</html>