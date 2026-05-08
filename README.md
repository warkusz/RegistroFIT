<div align="center">
  <table border="0" cellpadding="20" cellspacing="0" style="border: 1px solid #30363d; border-radius: 8px; background-color: #161b22;">
    <tr>
      <td align="center" valign="middle">
        <img src="Public/esja-logo.png" alt="ESJA Seixal" height="100"/>
        <br/>
        <sub><b>Escola Secundária Dr. José Afonso (ESJA)</b><br/>Seixal, Portugal</sub>
      </td>
    </tr>
  </table>
</div>

# RegistoFIT

Aplicacao web desenvolvida como projeto PAP para planeamento e acompanhamento de treinos.
<div align="center">

### Screenshots

<table border="0" cellpadding="8" cellspacing="0">
  <tr>
    <th align="center">Pagina Principal</th>
    <th align="center">Dashboard</th>
  </tr>
  <tr>
    <td align="center"><img src="assets/main.png" width="600"/></td>
    <td align="center"><img src="assets/dashboard.png" width="600"/></td>
  </tr>
  <tr>
    <th align="center">Calendário</th>
    <th align="center">Perfil</th>
  </tr>
  <tr>
    <td align="center"><img src="assets/calendario.png" width="600"/></td>
    <td align="center"><img src="assets/perfil.png" width="600"/></td>
  </tr>
</table>

</div>

## Sobre o projeto

O **RegistoFIT** permite criar planos de treino por dia, adicionar exercicios com series/repeticoes/carga, agendar treinos num calendario e acompanhar progresso pessoal atraves do perfil.

O foco do projeto e oferecer uma interface simples, rapida e organizada para o utilizador gerir os seus treinos num so sitio.

## Funcionalidades

- Autenticacao de utilizadores (registo, login e logout)
- Criacao e gestao de dias de treino
- Adicao e remocao de exercicios por dia
- Calendario mensal com marcacoes de treino
- Marcacao de treino como concluido
- Perfil com nome, foto e historico de medicoes (peso)
- Tema visual dark/red com fundo global e animacoes entre paginas

## Stack usada

- **Frontend:** HTML, CSS, Bootstrap 5, Bootstrap Icons
- **Backend:** PHP
- **Base de dados:** MySQL (via XAMPP)

## Requisitos

- [XAMPP](https://www.apachefriends.org/)
  - Apache
  - MySQL
- PHP 8+ (recomendado)

## Como correr localmente

1) PRE-REQUISITOS
- Windows
- XAMPP instalado (Apache + MySQL + phpMyAdmin)
- Navegador (Chrome/Edge)
2) COLOCAR O PROJETO NA PASTA CERTA
- Copiar a pasta do projeto para:
  C:\xampp\htdocs\RegistroFIT-main
3) INICIAR SERVICOS NO XAMPP
- Abrir “XAMPP Control Panel”
- Clicar Start em:
  - Apache
  - MySQL
- Confirmar que ambos ficam com luz verde
4) CRIAR BASE DE DADOS E UTILIZADOR (phpMyAdmin)
- Abrir no browser:
  http://localhost/phpmyadmin
- Ir ao separador SQL e executar ESTE BLOCO:
  
CREATE DATABASE IF NOT EXISTS papdb
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS 'papuser'@'localhost' IDENTIFIED BY 'pap123';
GRANT ALL PRIVILEGES ON papdb.* TO 'papuser'@'localhost';
FLUSH PRIVILEGES;
USE papdb;
CREATE TABLE IF NOT EXISTS utilizadores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  foto_perfil VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

5) NOTA IMPORTANTE SOBRE AS OUTRAS TABELAS
- As tabelas seguintes sao criadas automaticamente pela aplicacao quando o utilizador entra nas paginas:
  - workout_days
  - workout_exercises
  - workout_calendar_entries
  - user_measurements
- Ou seja, basta a tabela “utilizadores” existir para arrancar normalmente.
6) VERIFICAR FICHEIRO DE CONEXAO (SE NECESSARIO)
- Abrir:
  C:\xampp\htdocs\RegistroFIT-main\conexao.php
- Confirmar que esta assim:
  host = localhost
  user = papuser
  pass = pap123
  db   = papdb
7) ABRIR O PROJETO
- URL inicial:
  http://localhost/RegistroFIT-main/index.html
- Registar conta:
  http://localhost/RegistroFIT-main/register.php
- Login:
  http://localhost/RegistroFIT-main/login.php
8) PERMISSOES PARA FOTO DE PERFIL (IMPORTANTE)
- A aplicacao guarda fotos em:
  C:\xampp\htdocs\RegistroFIT-main\uploads\profile_photos
- Se der erro de upload, criar manualmente esta pasta e garantir permissao de escrita.
9) CHECKLIST RAPIDO (SE NAO ABRIR)
- Apache ligado? MySQL ligado?
- Projeto dentro de C:\xampp\htdocs\ ?
- Base de dados papdb criada?
- Utilizador papuser/pap123 criado e com privilegios?
- Tabela utilizadores criada?
- conexao.php com credenciais corretas?
10) CREDENCIAIS DE TESTE
- Criar conta nova em /register.php
- Fazer login com essa conta em /login.php
- O sistema redireciona para main.php e permite usar Treinos, Calendario e Perfil.

## Estrutura principal

- `index.html` - pagina inicial publica
- `funcoes.html` - apresentacao de funcionalidades
- `sobre.html` - descricao do projeto
- `login.php` / `register.php` - autenticacao
- `main.php` - gestao de plano de treino
- `calendar.php` - calendario de treinos
- `perfil.php` - perfil, foto e medicoes
- `style.css` - estilos globais
- `page-transitions.js` - animacoes entre paginas

## Melhorias futuras

- Filtros e pesquisa no historico
- Internacionalizacao (PT/EN)

## Autor

**Marcos Costa**  
Projeto PAP - Ano letivo 2025/2026
