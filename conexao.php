<?php
// -----------------------------------------------------------------------------
// conexao.php
// Camada central de acesso à base de dados:
// - mantém credenciais num único ponto;
// - cria uma ligação mysqli reutilizada nas páginas;
// - define UTF-8 para garantir acentos corretos.
// -----------------------------------------------------------------------------
// Configurações do servidor local (XAMPP padrão)
$host = "localhost";
$user = "papuser";
$pass = "pap123";       
$db   = "papdb"; 

// Criar a conexão
$conn = new mysqli($host, $user, $pass, $db);

// Verificar se houve erro na conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}


$conn->set_charset("utf8");
// Charset UTF-8 evita problemas com acentos e caracteres especiais.


?>
