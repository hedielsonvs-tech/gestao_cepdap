<?php
session_start();
require_once 'conexao.php';

// Verifica se é professor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'professor') {
    header("Location: index.php");
    exit;
}

$id_professor = $_SESSION['usuario_id'];
$msg = "";
$tipo_msg = "";

// Processar Atualização de Cadastro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $nome_social = trim($_POST['nome_social'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    $nome_foto = null;

    if (!empty($nome) && !empty($email)) {
        
        // Verifica se o e-mail já pertence a outro usuário
        $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?");
        $stmtDup->execute([$email, $id_professor]);
        
        if ($stmtDup->fetchColumn() > 0) {
            $msg = "Erro: Este e-mail já está sendo utilizado por outro cadastro.";
            $tipo_msg = "danger";
        } else {
            // Tratamento de upload de foto de perfil (se houver)
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $pasta_upload = "uploads/";
                if (!is_dir($pasta_upload)) {
                    mkdir($pasta_upload, 0777, true);
                }
                $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $nome_foto = "foto_user_" . $id_professor . "_" . time() . "." . $extensao;
                move_uploaded_file($_FILES['foto']['tmp_name'], $pasta_upload . $nome_foto);
            }

            // Validação e alteração de senha (se preenchida)
            if (!empty($nova_senha)) {
                if ($nova_senha === $confirmar_senha) {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    
                    if ($nome_foto) {
                        // Atualiza com foto e com senha nova
                        $stmtUpd = $pdo->prepare("UPDATE usuarios SET nome = ?, nome_social = ?, cpf = ?, email = ?, senha = ?, foto = ? WHERE id = ?");
                        $stmtUpd->execute([$nome, $nome_social, $cpf, $email, $senha_hash, $nome_foto, $id_professor]);
                    } else {
                        // Atualiza apenas com senha nova
                        $stmtUpd = $pdo->prepare("UPDATE usuarios SET nome = ?, nome_social = ?, cpf = ?, email = ?, senha = ? WHERE id = ?");
                        $stmtUpd->execute([$nome, $nome_social, $cpf, $email, $senha_hash, $id_professor]);
                    }
                } else {
                    $msg = "Erro: As senhas novas não coincidem.";
                    $tipo_msg = "danger";
                }
            } else {
                // Sem alteração de senha
                if ($nome_foto) {
                    // Atualiza com foto sem alterar senha
                    $stmtUpd = $pdo->prepare("UPDATE usuarios SET nome = ?, nome_social = ?, cpf = ?, email = ?, foto = ? WHERE id = ?");
                    $stmtUpd->execute([$nome, $nome_social, $cpf, $email, $nome_foto, $id_professor]);
                } else {
                    // Atualiza dados básicos
                    $stmtUpd = $pdo->prepare("UPDATE usuarios SET nome = ?, nome_social = ?, cpf = ?, email = ? WHERE id = ?");
                    $stmtUpd->execute([$nome, $nome_social, $cpf, $email, $id_professor]);
                }
            }

            if (empty($msg) || $tipo_msg !== "danger") {
                $_SESSION['usuario_nome'] = $nome; // Atualiza sessão
                $msg = "Cadastro atualizado com sucesso!";
                $tipo_msg = "success";
            }
        }
    } else {
        $msg = "Os campos Nome e E-mail são obrigatórios.";
        $tipo_msg = "danger";
    }
}

// Busca os dados atuais do professor na tabela usuarios
$stmtProfessor = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmtProfessor->execute([$id_professor]);
$professor = $stmtProfessor->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Meu Cadastro</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: linear-gradient(135deg, #3f2b96 0%, #a8c0ff 100%);
            --header-gradient: linear-gradient(135deg, rgba(63,43,150,0.08) 0%, rgba(168,192,255,0.18) 100%);
            --header-border: #3f2b96;
            --primary-indigo: #3f2b96;
        }
        body { background-color: #f4f6f9; color: #334155; }

        @media (min-width: 992px) {
            .a4-desktop {
                width: 210mm;
                min-height: 297mm;
                margin: 20px auto;
                background: white;
                padding: 15mm 20mm;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                border-radius: 8px;
            }
        }

        @media (max-width: 991.98px) {
            body { background-color: #ffffff; }
            .container { padding-left: 10px; padding-right: 10px; }
            .mobile-wrapper { background: #ffffff; padding: 10px 0; box-shadow: none; border-radius: 0; width: 100%; margin: 0; }
        }

        .logo-img { max-width: 45px; height: auto; }
        
        .navbar-professor {
            background: var(--cepdap-navbar) !important;
            transition: all 0.3s ease;
        }

        .header-card {
            background: var(--header-gradient);
            border-left: 5px solid var(--header-border);
            border-radius: 8px;
        }

        .btn-indigo-custom {
            background-color: var(--primary-indigo);
            color: #ffffff;
            font-weight: 600;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-indigo-custom:hover {
            background-color: #312178;
            color: #ffffff;
            box-shadow: 0 3px 8px rgba(63, 43, 150, 0.25);
        }

        .avatar-preview {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-indigo);
            box-shadow: 0 4px 10px rgba(63,43,150,0.15);
        }
    </style>
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar navbar-dark navbar-professor shadow-sm py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0 text-truncate" href="painel_professor.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1 flex-shrink-0">
                <span class="fs-6 fs-md-5 fw-bold">Painel do Professor(a)</span>
            </a>
            <div>
                <a href="painel_professor.php" class="btn btn-outline-light btn-sm text-nowrap fw-medium px-3">⬅️ Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <!-- Cabeçalho da Página -->
            <div class="header-card p-3 mb-3 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);">Gestão de Perfil</span>
                    <h4 class="text-dark fw-bold fs-5 mb-0">Meu Cadastro / Dados Pessoais</h4>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <!-- Formulário de Edição de Cadastro -->
            <div class="card p-4 border shadow-sm mb-4 bg-light rounded-3">
                
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div>
                        <?php if (!empty($professor['foto']) && file_exists("uploads/" . $professor['foto'])): ?>
                            <img src="uploads/<?= htmlspecialchars($professor['foto']); ?>" alt="Foto" class="avatar-preview">
                        <?php else: ?>
                            <div class="avatar-preview bg-secondary d-flex align-items-center justify-content-center text-white fs-3 fw-bold">
                                <?= mb_strtoupper(mb_substr($professor['nome'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($professor['nome']); ?></h5>
                        <span class="badge bg-secondary text-uppercase" style="font-size: 0.7rem;">Perfil: <?= htmlspecialchars($professor['tipo']); ?></span>
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Membro desde: <?= date('d/m/Y', strtotime($professor['criado_em'])); ?></small>
                    </div>
                </div>

                <form action="perfil_professor.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Nome Completo -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Nome Completo</label>
                            <input type="text" class="form-control form-control-sm" name="nome" value="<?= htmlspecialchars($professor['nome']); ?>" required>
                        </div>

                        <!-- Nome Social -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Nome Social (Opcional)</label>
                            <input type="text" class="form-control form-control-sm" name="nome_social" value="<?= htmlspecialchars($professor['nome_social'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <!-- CPF -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">CPF</label>
                            <input type="text" class="form-control form-control-sm" name="cpf" value="<?= htmlspecialchars($professor['cpf'] ?? ''); ?>" placeholder="000.000.000-00">
                        </div>

                        <!-- E-mail -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">E-mail de Acesso</label>
                            <input type="email" class="form-control form-control-sm" name="email" value="<?= htmlspecialchars($professor['email']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Alterar Foto de Perfil (Opcional)</label>
                        <input type="file" class="form-control form-control-sm" name="foto" accept="image/*">
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-primary mb-3 fs-6">🔒 Alteração de Senha (Preencha apenas se desejar alterar)</h6>

                    <div class="row">
                        <!-- Nova Senha -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Nova Senha</label>
                            <input type="password" class="form-control form-control-sm" name="nova_senha" placeholder="Mínimo de caracteres">
                        </div>

                        <!-- Confirmar Senha -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Confirmar Nova Senha</label>
                            <input type="password" class="form-control form-control-sm" name="confirmar_senha" placeholder="Repita a nova senha">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-indigo-custom btn-sm w-100 py-2 mt-2">
                        💾 Salvar Alterações do Cadastro
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>