<?php
session_start();
require_once 'conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (!empty($email) && !empty($senha)) {
        // Busca apenas utilizadores com status 'aprovado'
        $sql = "SELECT * FROM usuarios WHERE email = :email AND status = 'aprovado' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo']; // 'professor', 'aluno' ou 'admin'

            // Redirecionamento por perfil no login convencional
            if ($usuario['tipo'] == 'admin') {
                header("Location: painel_admin.php");
            } elseif ($usuario['tipo'] == 'professor') {
                header("Location: painel_professor.php");
            } else {
                header("Location: painel_aluno.php"); // Redireciona para o Painel Geral do Aluno
            }
            exit;
        } else {
            $stmtCheck = $pdo->prepare("SELECT status FROM usuarios WHERE email = :email LIMIT 1");
            $stmtCheck->bindValue(':email', $email);
            $stmtCheck->execute();
            $userCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($userCheck && $userCheck['status'] != 'aprovado') {
                $erro = "O seu registo ainda está pendente de aprovação pela administração.";
            } else {
                $erro = "E-mail ou palavra-passe incorretos!";
            }
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Portal Académico e Pedagógico</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-primary: #3f2b96;
            --cepdap-secondary: #a8c0ff;
            --cepdap-success: #198754;
            --cepdap-bg: #B0E0E6;
        }

        body {
            background-color: var(--cepdap-bg);
            min-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            margin: 0;
        }

        @media (min-width: 992px) {
            .a4-desktop-login {
                width: 210mm;
                min-height: 160mm;
                background: #B0E0E6;
                padding: 35mm 30mm;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                border-radius: 12px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                border: 1px solid #e2e8f0;
            }
        }

        @media (max-width: 991.98px) {
            body { background-color: #F0FFFF; padding: 15px; }
            .card-mobile-login {
                width: 100%;
                max-width: 420px;
                padding: 25px;
                background: #F0FFFF;
                border-radius: 12px;
                box-shadow: none;
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-img {
            max-width: 110px;
            height: auto;
            margin-bottom: 12px;
            padding: 8px;
            background: #F0FFFF;
          
       
        }

        .form-control {
            padding: 12px 16px;
            font-size: 0.95rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--cepdap-primary);
            box-shadow: 0 0 0 4px rgba(63, 43, 150, 0.1);
        }

        .btn-entrar {
            background: linear-gradient(135deg, #3f2b96 0%, #5335d1 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            color: #ffffff;
            transition: all 0.2s ease;
        }

        .btn-entrar:hover {
            background: linear-gradient(135deg, #322179 0%, #4428b0 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(63, 43, 150, 0.2);
        }

        .facial-link {
            font-size: 0.85rem;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }

        .facial-link:hover {
            color: var(--cepdap-primary);
            text-decoration: underline;
        }

        .divider {
            border-top: 1px solid #e2e8f0;
            margin: 25px 0;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center align-items-center">
        <div class="a4-desktop-login card-mobile-login">
            
            <div class="logo-container">
                <img src="logo.png" alt="Logótipo CEPDAP" class="logo-img">
                <h3 class="fw-bold text-dark fs-4 mb-1">Portal Académico</h3>
                <p class="text-muted small mb-0">Insira as suas credenciais</p>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger text-center py-2 small rounded-3 shadow-sm" role="alert">
                    <?= htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label small fw-bold text-secondary">E-mail Institucional</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="nome.aluno@escola.com">
                </div>

                <div class="mb-2">
                    <label for="senha" class="form-label small fw-bold text-secondary">Palavra-passe</label>
                    <input type="password" class="form-control" id="senha" name="senha" required placeholder="••••••••">
                </div>

                <!-- Atalho Discreto para Reconhecimento Facial -->
                <div class="mb-4 text-end">
                    <a href="login_facial.php" class="facial-link">
                        📷 Biblioteca virtual / Leitura Facial
                    </a>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-entrar shadow-sm">Aceder ao Sistema</button>
                </div>
            </form>

            <div class="divider"></div>

            <div class="text-center">
                <p class="text-muted small mb-1">Ainda não tem uma conta ativa?</p>
                <a href="cadastro.php" class="text-decoration-none fw-bold small" style="color: var(--cepdap-primary);">Criar novo registo no sistema</a>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>