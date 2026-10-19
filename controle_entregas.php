<?php
session_start();
require_once 'conexao.php';

// Verifica se é professor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'professor') {
    header("Location: index.php");
    exit;
}

$id_post = intval($_GET['id_post'] ?? 0);
$id_vinculo = intval($_GET['id_vinculo'] ?? 0);
$id_professor = $_SESSION['usuario_id'];

// Valida se a postagem e o vínculo pertencem ao professor logado
$stmtPost = $pdo->prepare("
    SELECT p.*, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome 
    FROM postagens_disciplina p
    JOIN professor_turma_disciplina ptd ON p.id_professor_turma_disciplina = ptd.id
    JOIN disciplinas d ON ptd.id_disciplina = d.id
    JOIN turmas t ON ptd.id_turma = t.id
    JOIN cursos c ON t.id_curso = c.id
    WHERE p.id = ? AND ptd.id_professor = ?
");
$stmtPost->execute([$id_post, $id_professor]);
$postagem = $stmtPost->fetch(PDO::FETCH_ASSOC);

if (!$postagem) {
    echo "Exercício não encontrado ou acesso negado.";
    exit;
}

$msg = "";
$tipo_msg = "";

// Ação para Atualizar Nota / Status da Entrega
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_correcao') {
    $id_entrega = intval($_POST['id_entrega']);
    $status = $_POST['status'];
    $nota = !empty($_POST['nota']) ? floatval(str_replace(',', '.', $_POST['nota'])) : null;
    $observacoes = trim($_POST['observacoes']);

    $updateEntrega = $pdo->prepare("UPDATE entregas_exercicios SET status = ?, nota = ?, observacoes = ? WHERE id = ?");
    
    if ($updateEntrega->execute([$status, $nota, $observacoes, $id_entrega])) {
        $msg = "Avaliação atualizada com sucesso!";
        $tipo_msg = "success";
    } else {
        $msg = "Erro ao atualizar avaliação.";
        $tipo_msg = "danger";
    }
}

// Buscar a lista de entregas dos alunos para este exercício
$stmtEntregas = $pdo->prepare("
    SELECT ee.*, u.nome as aluno_nome, u.email as aluno_email 
    FROM entregas_exercicios ee
    JOIN usuarios u ON ee.id_aluno = u.id
    WHERE ee.id_postagem = ?
    ORDER BY u.nome ASC
");
$stmtEntregas->execute([$id_post]);
$entregas = $stmtEntregas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Controlo de Entregas</title>
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

        .header-disciplina {
            background: var(--header-gradient);
            border-left: 5px solid var(--header-border);
            border-radius: 8px;
        }

        .card-aluno {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .card-aluno:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
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
            <div class="d-flex align-items-center gap-2">
                <a href="disciplina_detalhes.php?id=<?= $id_vinculo; ?>" class="btn btn-outline-light btn-sm text-nowrap fw-medium px-3">⬅️ Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <!-- Cabeçalho do Exercício -->
            <div class="header-disciplina p-3 mb-3 shadow-sm">
                <span class="badge bg-warning text-dark mb-1">📝 Controlo de Entregas</span>
                <h3 class="text-dark fw-bold fs-5 mb-1"><?= htmlspecialchars($postagem['titulo']); ?></h3>
                <p class="small text-muted mb-1">Turma: <strong><?= htmlspecialchars($postagem['turma_nome']); ?></strong> | Disciplina: <strong><?= htmlspecialchars($postagem['disciplina_nome']); ?></strong></p>
                <?php if ($postagem['pontuacao'] !== null): ?>
                    <span class="badge bg-success">Pontuação Máxima: <?= number_format($postagem['pontuacao'], 2, ',', '.'); ?> pts</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <h5 class="fw-bold mb-3 fs-6 text-secondary">Alunos da Turma</h5>

            <?php if (empty($entregas)): ?>
                <div class="alert alert-secondary text-center py-4 small rounded-3">Nenhum aluno registado para receber este exercício.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($entregas as $e): ?>
                        <div class="card card-aluno p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">👨‍🎓 <?= htmlspecialchars($e['aluno_nome']); ?></h6>
                                    <span class="text-muted" style="font-size: 0.75rem;">📧 <?= htmlspecialchars($e['aluno_email']); ?></span>
                                </div>
                                <div>
                                    <?php 
                                        if ($e['status'] == 'Pendente') echo '<span class="badge bg-secondary">Pendente</span>';
                                        elseif ($e['status'] == 'Entregue') echo '<span class="badge bg-primary">Entregue</span>';
                                        else echo '<span class="badge bg-success">Corrigido</span>';
                                    ?>
                                </div>
                            </div>

                            <div class="row g-2 mb-3 small bg-light p-2 rounded-2">
                                <div class="col-12 col-md-6">
                                    <strong>Ficheiro Enviado:</strong><br>
                                    <?php if (!empty($e['arquivo_entrega'])): ?>
                                        <a href="uploads/<?= htmlspecialchars($e['arquivo_entrega']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2 mt-1" style="font-size: 0.75rem;">📥 Ver Arquivo</a>
                                        <small class="text-muted d-block mt-1"><?= $e['data_entrega'] ? date('d/m/Y H:i', strtotime($e['data_entrega'])) : ''; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Ainda não enviado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <strong>Nota Atribuída:</strong><br>
                                    <span class="fs-6 text-primary fw-bold"><?= $e['nota'] !== null ? number_format($e['nota'], 2, ',', '.') . ' pts' : 'Não atribuída'; ?></span>
                                    <?php if (!empty($e['observacoes'])): ?>
                                        <div class="text-muted mt-1" style="font-size: 0.75rem;">Obs: <?= htmlspecialchars($e['observacoes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <!-- Botão para abrir modal de correção -->
                                <button class="btn btn-outline-primary btn-sm py-1 px-3" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#modalCorrigir<?= $e['id']; ?>">
                                    ✏️ Avaliar / Corrigir Aluno
                                </button>
                            </div>

                            <!-- Modal de Avaliação -->
                            <div class="modal fade" id="modalCorrigir<?= $e['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="controle_entregas.php?id_post=<?= $id_post; ?>&id_vinculo=<?= $id_vinculo; ?>" method="POST">
                                            <input type="hidden" name="acao" value="atualizar_correcao">
                                            <input type="hidden" name="id_entrega" value="<?= $e['id']; ?>">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Avaliar Aluno: <?= htmlspecialchars($e['aluno_nome']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Estado da Entrega</label>
                                                    <select class="form-select form-select-sm" name="status" required>
                                                        <option value="Pendente" <?= ($e['status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                                                        <option value="Entregue" <?= ($e['status'] == 'Entregue') ? 'selected' : ''; ?>>Entregue</option>
                                                        <option value="Corrigido" <?= ($e['status'] == 'Corrigido') ? 'selected' : ''; ?>>Corrigido</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Nota Atribuída</label>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="nota" value="<?= htmlspecialchars($e['nota'] ?? ''); ?>" placeholder="Ex: 8.5">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Observações / Feedback</label>
                                                    <textarea class="form-control form-control-sm" name="observacoes" rows="3" placeholder="Comentários para o aluno..."><?= htmlspecialchars($e['observacoes'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary btn-sm">Salvar Avaliação</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

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