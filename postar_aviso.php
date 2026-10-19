<?php
session_start();
require_once 'conexao.php';

// Verifica se é professor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'professor') {
    header("Location: index.php");
    exit;
}

$id_professor = $_SESSION['usuario_id'];
$nome_professor = $_SESSION['usuario_nome'] ?? '';

$msg = "";
$tipo_msg = "";

// Ação de Exclusão
if (isset($_GET['acao']) && $_GET['acao'] == 'excluir') {
    $id_comunicado = intval($_GET['id_aviso'] ?? 0);
    
    // Garante que o professor só exclua comunicados criados por ele
    $stmtDel = $pdo->prepare("DELETE FROM comunicados WHERE id = ? AND id_professor = ?");
    if ($stmtDel->execute([$id_comunicado, $id_professor])) {
        $msg = "Aviso/Comunicado excluído com sucesso!";
        $tipo_msg = "success";
    }
}

// Ação de Alterar Status (Ativo / Inativo)
if (isset($_GET['acao']) && $_GET['acao'] == 'status') {
    $id_comunicado = intval($_GET['id_aviso'] ?? 0);
    $novo_status = $_GET['novo_status'] === 'inativo' ? 'inativo' : 'ativo';
    
    $stmtStatus = $pdo->prepare("UPDATE comunicados SET status = ? WHERE id = ? AND id_professor = ?");
    if ($stmtStatus->execute([$novo_status, $id_comunicado, $id_professor])) {
        $msg = "Status atualizado para " . strtoupper($novo_status) . "!";
        $tipo_msg = "info";
    }
}

// Processar Inserção ou Edição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_comunicado = intval($_POST['id_comunicado'] ?? 0);
    $id_turma = !empty($_POST['id_turma']) ? intval($_POST['id_turma']) : null;
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'comunicado');
    $status = trim($_POST['status'] ?? 'ativo');

    if (!empty($titulo) && !empty($descricao)) {
        if ($id_comunicado > 0) {
            // Edição
            $stmtUpd = $pdo->prepare("
                UPDATE comunicados 
                SET id_turma = ?, titulo = ?, descricao = ?, tipo = ?, status = ? 
                WHERE id = ? AND id_professor = ?
            ");
            if ($stmtUpd->execute([$id_turma, $titulo, $descricao, $tipo, $status, $id_comunicado, $id_professor])) {
                $msg = "Aviso atualizado com sucesso!";
                $tipo_msg = "success";
            } else {
                $msg = "Erro ao atualizar aviso.";
                $tipo_msg = "danger";
            }
        } else {
            // Nova Inserção (data_postagem gerada automaticamente pelo banco/NOW())
            $stmtIns = $pdo->prepare("
                INSERT INTO comunicados (id_turma, id_professor, titulo, descricao, tipo, status, data_postagem) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            if ($stmtIns->execute([$id_turma, $id_professor, $titulo, $descricao, $tipo, $status])) {
                $msg = "Aviso publicado com sucesso!";
                $tipo_msg = "success";
            } else {
                $msg = "Erro ao publicar aviso.";
                $tipo_msg = "danger";
            }
        }
    } else {
        $msg = "Preencha pelo menos o Título e a Descrição.";
        $tipo_msg = "danger";
    }
}

// Se estiver em modo de edição, busca os dados do comunicado
$editando = false;
$dados_edicao = ['id' => '', 'id_turma' => '', 'titulo' => '', 'descricao' => '', 'tipo' => 'comunicado', 'status' => 'ativo'];
if (isset($_GET['acao']) && $_GET['acao'] == 'editar') {
    $id_edit = intval($_GET['id_aviso'] ?? 0);
    $stmtEd = $pdo->prepare("SELECT * FROM comunicados WHERE id = ? AND id_professor = ?");
    $stmtEd->execute([$id_edit, $id_professor]);
    $resEd = $stmtEd->fetch(PDO::FETCH_ASSOC);
    if ($resEd) {
        $editando = true;
        $dados_edicao = $resEd;
    }
}

// Busca as turmas vinculadas ao professor para permitir filtrar o aviso por turma
$stmtTurmas = $pdo->prepare("
    SELECT DISTINCT t.id, t.nome as turma_nome, c.nome as curso_nome, t.turno
    FROM professor_turma_disciplina ptd
    JOIN turmas t ON ptd.id_turma = t.id
    JOIN cursos c ON t.id_curso = c.id
    WHERE ptd.id_professor = ?
    ORDER BY c.nome ASC, t.nome ASC
");
$stmtTurmas->execute([$id_professor]);
$turmasVinculadas = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// Busca o histórico de comunicados postados por este professor
$stmtHistorico = $pdo->prepare("
    SELECT c.*, t.nome as turma_nome 
    FROM comunicados c
    LEFT JOIN turmas t ON c.id_turma = t.id
    WHERE c.id_professor = ?
    ORDER BY c.data_postagem DESC
");
$stmtHistorico->execute([$id_professor]);
$historicoAvisos = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Postar Aviso e Comunicado</title>
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

        .card-aviso-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .card-aviso-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
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
                    <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);">Módulo de Comunicação</span>
                    <h4 class="text-dark fw-bold fs-5 mb-0">Publicar Avisos e Comunicados</h4>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <!-- Formulário de Criação / Edição -->
            <div class="card p-3 border shadow-sm mb-4 bg-light rounded-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0 fs-6"><?= $editando ? '✏️ Editar Aviso' : '📢 Novo Aviso / Comunicado'; ?></h5>
                    <?php if ($editando): ?>
                        <a href="postar_aviso.php" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem;">Cancelar Edição</a>
                    <?php endif; ?>
                </div>

                <form action="postar_aviso.php" method="POST">
                    <input type="hidden" name="id_comunicado" value="<?= htmlspecialchars($dados_edicao['id']); ?>">

                    <div class="row">
                        <!-- Título -->
                        <div class="col-md-8 mb-3">
                            <label class="form-label small fw-bold text-secondary">Título do Aviso</label>
                            <input type="text" class="form-control form-control-sm" name="titulo" value="<?= htmlspecialchars($dados_edicao['titulo']); ?>" required placeholder="Ex: Alteração na data da avaliação / Lembrete de entrega">
                        </div>

                        <!-- Tipo -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-secondary">Tipo</label>
                            <select class="form-select form-select-sm" name="tipo" required>
                                <option value="comunicado" <?= ($dados_edicao['tipo'] == 'comunicado') ? 'selected' : ''; ?>>📢 Comunicado</option>
                                <option value="aviso" <?= ($dados_edicao['tipo'] == 'aviso') ? 'selected' : ''; ?>>⚠️ Aviso Importante</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Turma Alvo -->
                        <div class="col-md-8 mb-3">
                            <label class="form-label small fw-bold text-secondary">Destinatário (Turma)</label>
                            <select class="form-select form-select-sm" name="id_turma">
                                <option value="">🌐 Para Todos / Geral (Sem turma específica)</option>
                                <?php foreach ($turmasVinculadas as $t): ?>
                                    <option value="<?= $t['id']; ?>" <?= ($dados_edicao['id_turma'] == $t['id']) ? 'selected' : ''; ?>>
                                        🎓 <?= htmlspecialchars($t['curso_nome']); ?> - Turma: <?= htmlspecialchars($t['turma_nome']); ?> (<?= htmlspecialchars($t['turno']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-secondary">Status</label>
                            <select class="form-select form-select-sm" name="status" required>
                                <option value="ativo" <?= ($dados_edicao['status'] == 'ativo') ? 'selected' : ''; ?>>🟢 Ativo (Visível)</option>
                                <option value="inativo" <?= ($dados_edicao['status'] == 'inativo') ? 'selected' : ''; ?>>🔴 Inativo (Oculto)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Descrição / Conteúdo -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Descrição / Detalhes do Aviso</label>
                        <textarea class="form-control form-control-sm" name="descricao" rows="4" required placeholder="Escreva aqui a mensagem completa do aviso..."><?= htmlspecialchars($dados_edicao['descricao']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-indigo-custom btn-sm w-100 py-2">
                        <?= $editando ? 'Salvar Alterações' : '🚀 Publicar Aviso'; ?>
                    </button>
                </form>
            </div>

            <!-- Histórico de Comunicados Criados -->
            <h5 class="fw-bold mb-3 fs-6 text-secondary">Meus Avisos Publicados</h5>

            <?php if (empty($historicoAvisos)): ?>
                <div class="alert alert-secondary text-center py-4 small rounded-3">
                    Você ainda não publicou nenhum aviso ou comunicado.
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($historicoAvisos as $aviso): ?>
                        <?php 
                            $badge_tipo_bg = ($aviso['tipo'] === 'aviso') ? 'danger' : 'primary';
                            $status_bg = ($aviso['status'] === 'ativo') ? 'success' : 'secondary';
                        ?>
                        <div class="card card-aviso-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                                <div>
                                    <span class="badge bg-<?= $badge_tipo_bg; ?> text-uppercase me-1"><?= htmlspecialchars($aviso['tipo']); ?></span>
                                    <span class="badge bg-<?= $status_bg; ?> me-1"><?= ucfirst($aviso['status']); ?></span>
                                    
                                    <?php if (!empty($aviso['turma_nome'])): ?>
                                        <span class="badge bg-info text-dark me-1">Turma: <?= htmlspecialchars($aviso['turma_nome']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-dark me-1">Geral</span>
                                    <?php endif; ?>

                                    <small class="text-muted ms-1" style="font-size: 0.75rem;">
                                        📅 <?= date('d/m/Y H:i', strtotime($aviso['data_postagem'])); ?>
                                    </small>
                                </div>

                                <div class="d-flex gap-1">
                                    <?php if ($aviso['status'] === 'ativo'): ?>
                                        <a href="postar_aviso.php?acao=status&id_aviso=<?= $aviso['id']; ?>&novo_status=inativo" class="btn btn-outline-warning btn-sm py-0 px-2" style="font-size: 0.75rem;">Ocultar</a>
                                    <?php else: ?>
                                        <a href="postar_aviso.php?acao=status&id_aviso=<?= $aviso['id']; ?>&novo_status=ativo" class="btn btn-outline-success btn-sm py-0 px-2" style="font-size: 0.75rem;">Ativar</a>
                                    <?php endif; ?>

                                    <a href="postar_aviso.php?acao=editar&id_aviso=<?= $aviso['id']; ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;">✏️ Editar</a>
                                    <a href="postar_aviso.php?acao=excluir&id_aviso=<?= $aviso['id']; ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;" onclick="return confirm('Tem certeza que deseja excluir este comunicado?');">🗑️ Excluir</a>
                                </div>
                            </div>

                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($aviso['titulo']); ?></h6>
                            <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($aviso['descricao'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>