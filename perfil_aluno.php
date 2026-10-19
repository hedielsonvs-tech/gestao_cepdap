<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'aluno') {
    header("Location: index.php");
    exit;
}

$id_aluno = $_SESSION['usuario_id'];
$msg = "";
$tipo_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_perfil']) && $_POST['acao_perfil'] == 'atualizar_perfil') {
    $novo_nome_social = trim($_POST['nome_social'] ?? '');
    $novo_email = trim($_POST['email'] ?? '');
    $nova_senha = $_POST['senha'] ?? '';

    if (!empty($novo_email)) {
        $stmtEmail = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
        $stmtEmail->execute([$novo_email, $id_aluno]);
        
        if ($stmtEmail->fetchColumn() > 0) {
            $msg = "Erro: Este e-mail já está sendo utilizado por outro usuário.";
            $tipo_msg = "danger";
        } else {
            $nome_foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $pasta_upload = "uploads/";
                if (!is_dir($pasta_upload)) {
                    mkdir($pasta_upload, 0777, true);
                }
                $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $nome_foto = "foto_user_" . $id_aluno . "_" . uniqid() . "." . $extensao;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $pasta_upload . $nome_foto)) {
                    $stmtAntiga = $pdo->prepare("SELECT foto FROM usuarios WHERE id = ?");
                    $stmtAntiga->execute([$id_aluno]);
                    $usrAnt = $stmtAntiga->fetch(PDO::FETCH_ASSOC);
                    if (!empty($usrAnt['foto']) && file_exists("uploads/" . $usrAnt['foto'])) {
                        unlink("uploads/" . $usrAnt['foto']);
                    }
                } else {
                    $nome_foto = null;
                }
            }

            if (!empty($nova_senha)) {
                $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);
                if ($nome_foto) {
                    $up = $pdo->prepare("UPDATE usuarios SET nome_social = ?, email = ?, senha = ?, foto = ? WHERE id = ?");
                    $up->execute([$novo_nome_social, $novo_email, $senhaHash, $nome_foto, $id_aluno]);
                } else {
                    $up = $pdo->prepare("UPDATE usuarios SET nome_social = ?, email = ?, senha = ? WHERE id = ?");
                    $up->execute([$novo_nome_social, $novo_email, $senhaHash, $id_aluno]);
                }
            } else {
                if ($nome_foto) {
                    $up = $pdo->prepare("UPDATE usuarios SET nome_social = ?, email = ?, foto = ? WHERE id = ?");
                    $up->execute([$novo_nome_social, $novo_email, $nome_foto, $id_aluno]);
                } else {
                    $up = $pdo->prepare("UPDATE usuarios SET nome_social = ?, email = ? WHERE id = ?");
                    $up->execute([$novo_nome_social, $novo_email, $id_aluno]);
                }
            }

            $stmtBuscaNome = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
            $stmtBuscaNome->execute([$id_aluno]);
            $resNome = $stmtBuscaNome->fetch(PDO::FETCH_ASSOC);
            $nomeCivilAtual = $resNome['nome'] ?? '';

            $_SESSION['usuario_nome'] = !empty($novo_nome_social) ? $novo_nome_social : $nomeCivilAtual;
            
            $msg = "Perfil atualizado com sucesso!";
            $tipo_msg = "success";
        }
    } else {
        $msg = "Preencha o campo obrigatório de E-mail.";
        $tipo_msg = "danger";
    }
}

$stmtAluno = $pdo->prepare("
    SELECT u.*, t.nome as turma_nome, t.turno, c.nome as curso_nome 
    FROM usuarios u
    LEFT JOIN turmas t ON u.id_turma = t.id
    LEFT JOIN cursos c ON u.id_curso = c.id
    WHERE u.id = ?
");
$stmtAluno->execute([$id_aluno]);
$aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

$cursoNomeLower = "";
if (isset($aluno['curso_nome'])) {
    $cursoNomeLower = mb_strtolower(trim($aluno['curso_nome']));
}

$corNavbar = 'linear-gradient(135deg, #495057 0%, #6c757d 100%)';
$corAvatarBorda = '#6c757d';

if (strpos($cursoNomeLower, 'informatica') !== false || strpos($cursoNomeLower, 'informática') !== false) {
    $corNavbar = 'linear-gradient(135deg, #1e7e34 0%, #28a745 100%)';
    $corAvatarBorda = '#28a745';
} elseif (strpos($cursoNomeLower, 'administracao') !== false || strpos($cursoNomeLower, 'administração') !== false) {
    $corNavbar = 'linear-gradient(135deg, #003366 0%, #0056b3 100%)';
    $corAvatarBorda = '#0056b3';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Editar Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: <?= $corNavbar; ?>;
            --avatar-border: <?= $corAvatarBorda; ?>;
        }
        body { background-color: #f4f6f9; }

        @media (min-width: 992px) {
            .a4-desktop {
                width: 210mm;
                min-height: 297mm;
                margin: 20px auto;
                background: white;
                padding: 15mm 20mm;
                box-shadow: 0 0 15px rgba(0,0,0,0.15);
                border-radius: 4px;
            }
        }

        @media (max-width: 991.98px) {
            body { background-color: #ffffff; }
            .container { padding-left: 10px; padding-right: 10px; }
            .mobile-wrapper { background: #ffffff; padding: 10px 0; box-shadow: none; border-radius: 0; width: 100%; margin: 0; }
        }

        .logo-img { max-width: 45px; height: auto; }
        .navbar-curso { background: var(--cepdap-navbar) !important; transition: all 0.3s ease; }
        .foto-aluno-perfil { border-radius: 50%; width: 80px; height: 80px; object-fit: cover; border: 3px solid var(--avatar-border); }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark navbar-curso shadow-sm py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="painel_aluno.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Editar Perfil</span>
            </a>
            <a href="painel_aluno.php" class="btn btn-outline-light btn-sm text-nowrap">Voltar ao Painel</a>
        </div>
    </nav>

    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small" role="alert"><?= htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <div class="card p-4 border shadow-sm mx-auto" style="max-width: 600px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0">Editar Meu Perfil</h5>
                    <a href="painel_aluno.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
                </div>
                
                <form action="perfil_aluno.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao_perfil" value="atualizar_perfil">

                    <div class="mb-3 text-center">
                        <div class="mb-2">
                            <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                                <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Foto" class="foto-aluno-perfil shadow-sm">
                            <?php else: ?>
                                <div class="foto-aluno-perfil d-inline-flex align-items-center justify-content-center bg-secondary text-white fs-1 shadow-sm">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        <label class="form-label small fw-bold">Alterar Foto de Perfil</label>
                        <input type="file" class="form-control form-control-sm" name="foto" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nome Civil (Completo) </label>
                        <input type="text" class="form-control form-control-sm bg-light text-muted" value="<?= htmlspecialchars($aluno['nome']); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nome Social <span class="text-muted fw-normal">(Opcional)</span></label>
                        <input type="text" class="form-control form-control-sm" name="nome_social" value="<?= htmlspecialchars($aluno['nome_social'] ?? ''); ?>" placeholder="Como prefere ser chamado(a)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">E-mail</label>
                        <input type="email" class="form-control form-control-sm" name="email" value="<?= htmlspecialchars($aluno['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nova Senha <span class="text-muted fw-normal">(Deixe em branco se não quiser alterar)</span></label>
                        <input type="password" class="form-control form-control-sm" name="senha" placeholder="********">
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100">Salvar Alterações</button>
                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>