<?php
session_start();
require_once 'conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (!empty($email) && !empty($senha)) {
        // Busca apenas usuários que estejam com o status 'aprovado'
        $sql = "SELECT * FROM usuarios WHERE email = :email AND status = 'aprovado' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Valida se o usuário existe, se a senha confere e se está aprovado
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo']; // 'professor', 'aluno' ou 'admin'

            // Redireciona de acordo com o perfil
            if ($usuario['tipo'] == 'admin') {
                header("Location: painel_admin.php");
            } elseif ($usuario['tipo'] == 'professor') {
                header("Location: painel_professor.php");
            } else {
                header("Location: painel_aluno.php");
            }
            exit;
        } else {
            // Verifica se o e-mail existe mas está pendente para dar uma mensagem mais clara
            $stmtCheck = $pdo->prepare("SELECT status FROM usuarios WHERE email = :email LIMIT 1");
            $stmtCheck->bindValue(':email', $email);
            $stmtCheck->execute();
            $userCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($userCheck && $userCheck['status'] != 'aprovado') {
                $erro = "Seu cadastro ainda está pendente de aprovação pela administração.";
            } else {
                $erro = "E-mail ou senha inválidos!";
            }
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
        }

        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (min-width: 992px) {
            .a4-desktop-login {
                width: 210mm;
                max-width: 100%;
                min-height: 140mm;
                background: white;
                padding: 40mm 30mm;
                box-shadow: 0 0 15px rgba(0,0,0,0.15);
                border-radius: 6px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }

        @media (max-width: 991px) {
            .card-mobile-login {
                width: 100%;
                max-width: 400px;
                padding: 20px;
                background: white;
                border-radius: 6px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
        }

        .logo-img {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }

        .btn-cepdap {
            background-color: var(--cepdap-green);
            color: white;
        }
        .btn-cepdap:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="a4-desktop-login card-mobile-login">
            
            <div class="text-center mb-4">
                <img src="logo.png" alt="Logo CEPDAP" class="logo-img">
                <h3 class="fw-bold text-primary">Portal Pedagógico</h3>
                <p class="text-muted small">Faça login para acessar o sistema de materiais</p>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger text-center py-2" role="alert">
                    <?= $erro; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">E-mail Institucional</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu.email@escola.com">
                </div>

                <div class="mb-4">
                    <label for="senha" class="form-label fw-semibold">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required placeholder="********">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-cepdap btn-lg">Entrar no Sistema</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="text-muted small mb-1">Ainda não possui uma conta?</p>
                <a href="cadastro.php" class="text-decoration-none fw-semibold text-primary">Criar cadastro no sistema</a>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>