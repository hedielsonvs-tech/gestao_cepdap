<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'aluno') {
    header("Location: index.php");
    exit;
}

$id_aluno = $_SESSION['usuario_id'];

// Busca informações atualizadas do aluno
$stmtAluno = $pdo->prepare("
    SELECT u.*, t.nome as turma_nome, t.turno, c.nome as curso_nome 
    FROM usuarios u
    LEFT JOIN turmas t ON u.id_turma = t.id
    LEFT JOIN cursos c ON u.id_curso = c.id
    WHERE u.id = ?
");
$stmtAluno->execute([$id_aluno]);
$aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

$msg = "";
$tipo_msg = "";

// Lógica de envio/upload de atividade pelo aluno
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'enviar_atividade') {
    $id_postagem = intval($_POST['id_postagem']);
    
    if (isset($_FILES['arquivo_aluno']) && $_FILES['arquivo_aluno']['error'] == 0) {
        $pasta_upload = "uploads/";
        if (!is_dir($pasta_upload)) {
            mkdir($pasta_upload, 0777, true);
        }

        $extensao = pathinfo($_FILES['arquivo_aluno']['name'], PATHINFO_EXTENSION);
        $nome_arquivo_envio = "entrega_" . $id_postagem . "_" . $id_aluno . "_" . uniqid() . "." . $extensao;

        if (move_uploaded_file($_FILES['arquivo_aluno']['tmp_name'], $pasta_upload . $nome_arquivo_envio)) {
            // Verifica se o registro de entrega já existe na tabela
            $checkEntrega = $pdo->prepare("SELECT id, arquivo_entrega FROM entregas_exercicios WHERE id_postagem = ? AND id_aluno = ?");
            $checkEntrega->execute([$id_postagem, $id_aluno]);
            $entregaExistente = $checkEntrega->fetch(PDO::FETCH_ASSOC);

            if ($entregaExistente) {
                // Remove o arquivo físico antigo se existir
                if (!empty($entregaExistente['arquivo_entrega']) && file_exists($pasta_upload . $entregaExistente['arquivo_entrega'])) {
                    unlink($pasta_upload . $entregaExistente['arquivo_entrega']);
                }

                // Atualiza o registro para 'Entregue'
                $stmtUpdate = $pdo->prepare("
                    UPDATE entregas_exercicios 
                    SET arquivo_entrega = ?, data_entrega = NOW(), status = 'Entregue' 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$nome_arquivo_envio, $entregaExistente['id']]);
            } else {
                // Insere novo registro como 'Entregue'
                $stmtInsert = $pdo->prepare("
                    INSERT INTO entregas_exercicios (id_postagem, id_aluno, arquivo_entrega, data_entrega, status) 
                    VALUES (?, ?, ?, NOW(), 'Entregue')
                ");
                $stmtInsert->execute([$id_postagem, $id_aluno, $nome_arquivo_envio]);
            }

            $msg = "Atividade enviada com sucesso!";
            $tipo_msg = "success";
        } else {
            $msg = "Erro ao mover o arquivo para a pasta de uploads.";
            $tipo_msg = "danger";
        }
    } else {
        $msg = "Por favor, selecione um arquivo válido para enviar.";
        $tipo_msg = "warning";
    }
}

$cursoNomeLower = "";
if (isset($aluno['curso_nome'])) {
    $cursoNomeLower = mb_strtolower(trim($aluno['curso_nome']));
}

$corNavbar = 'linear-gradient(135deg, #495057 0%, #6c757d 100%)';
$gradienteHeader = 'linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)';
$corBordaHeader = '#6c757d';
$corAvatarBorda = '#6c757d';
$imagemReferenciaCurso = 'enfermagem.png';

if (strpos($cursoNomeLower, 'informatica') !== false || strpos($cursoNomeLower, 'informática') !== false) {
    $corNavbar = 'linear-gradient(135deg, #1e7e34 0%, #28a745 100%)';
    $gradienteHeader = 'linear-gradient(135deg, rgba(40,167,69,0.15) 0%, rgba(32,201,151,0.2) 100%)';
    $corBordaHeader = '#28a745';
    $corAvatarBorda = '#28a745';
    $imagemReferenciaCurso = 'informatica.png';
} elseif (strpos($cursoNomeLower, 'administracao') !== false || strpos($cursoNomeLower, 'administração') !== false) {
    $corNavbar = 'linear-gradient(135deg, #003366 0%, #0056b3 100%)';
    $gradienteHeader = 'linear-gradient(135deg, rgba(0,86,179,0.15) 0%, rgba(23,162,184,0.2) 100%)';
    $corBordaHeader = '#0056b3';
    $corAvatarBorda = '#0056b3';
    $imagemReferenciaCurso = 'administracao.png';
}

$id_turma = $aluno['id_turma'] ?? 0;
$disciplina_selecionada = $_GET['disciplina'] ?? '';

$anoAtual = date('Y');
$mesAtual = date('n');
$semestreAtualPadrao = ($mesAtual <= 6 ? '1-' : '2-') . $anoAtual;

$listaSemestres = [];
for ($i = 0; $i <= 3; $i++) {
    $anoLoop = $anoAtual - $i;
    $listaSemestres[] = '2-' . $anoLoop;
    $listaSemestres[] = '1-' . $anoLoop;
}

$semestreSelecionado = $_GET['semestre'] ?? $semestreAtualPadrao;
if (!in_array($semestreSelecionado, $listaSemestres)) {
    $semestreSelecionado = $semestreAtualPadrao;
}

$disciplinas = [];
$postagens = [];

if ($id_turma > 0) {
    $stmtDisciplinas = $pdo->prepare("
        SELECT DISTINCT d.id, d.nome 
        FROM disciplinas d
        JOIN professor_turma_disciplina ptd ON ptd.id_disciplina = d.id
        WHERE ptd.id_turma = ? AND ptd.semestre = ?
        ORDER BY d.nome ASC
    ");
    $stmtDisciplinas->execute([$id_turma, $semestreSelecionado]);
    $disciplinas = $stmtDisciplinas->fetchAll(PDO::FETCH_ASSOC);

    // Consulta que traz os dados das postagens e a entrega do aluno
    $sql = "
        SELECT p.*, d.id as disciplina_id, d.nome as disciplina_nome, u.nome as professor_nome,
               ee.status as entrega_status, ee.nota as entrega_nota, ee.observacoes as entrega_obs, 
               ee.arquivo_entrega, ee.data_entrega
        FROM postagens_disciplina p
        JOIN professor_turma_disciplina ptd ON p.id_professor_turma_disciplina = ptd.id
        JOIN disciplinas d ON ptd.id_disciplina = d.id
        JOIN usuarios u ON ptd.id_professor = u.id
        LEFT JOIN entregas_exercicios ee ON ee.id_postagem = p.id AND ee.id_aluno = ?
        WHERE ptd.id_turma = ? AND p.semestre = ?
    ";
    
    $params = [$id_aluno, $id_turma, $semestreSelecionado];

    if (!empty($disciplina_selecionada) && is_numeric($disciplina_selecionada)) {
        $sql .= " AND d.id = ?";
        $params[] = $disciplina_selecionada;
    }

    $sql .= " ORDER BY p.data_postagem DESC";

    $stmtPostagens = $pdo->prepare($sql);
    $stmtPostagens->execute($params);
    $postagens = $stmtPostagens->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Mural de Materiais e Avisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: <?= $corNavbar; ?>;
            --header-border: <?= $corBordaHeader; ?>;
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
    </style>
</head>
<body>

    <nav class="navbar navbar-dark navbar-curso shadow-sm py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="painel_aluno.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Mural da Turma</span>
            </a>
            <a href="painel_aluno.php" class="btn btn-outline-light btn-sm text-nowrap">Voltar ao Painel</a>
        </div>
    </nav>

    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                <h5 class="fw-bold mb-0 fs-5 text-primary">Mural de Materiais e Avisos da Turma</h5>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($id_turma)): ?>
                <form method="GET" action="" class="row g-2 mb-4 align-items-end bg-light p-3 rounded border shadow-sm">
                    <div class="col-12 col-md-4">
                        <label for="semestre" class="form-label small fw-bold text-secondary mb-1">Filtrar Semestre:</label>
                        <select name="semestre" id="semestre" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($listaSemestres as $sem): ?>
                                <option value="<?= htmlspecialchars($sem); ?>" <?= ($sem === $semestreSelecionado) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sem); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="disciplina" class="form-label small fw-bold text-secondary mb-1">Filtrar por Disciplina:</label>
                        <select name="disciplina" id="disciplina" class="form-select form-select-sm">
                            <option value="">Todas as disciplinas</option>
                            <?php foreach ($disciplinas as $d): ?>
                                <option value="<?= $d['id']; ?>" <?= ($disciplina_selecionada == $d['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($d['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                        <?php if (!empty($disciplina_selecionada) || $semestreSelecionado !== $semestreAtualPadrao): ?>
                            <a href="mural_aluno.php" class="btn btn-outline-secondary btn-sm text-nowrap">Limpar</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>

            <?php if (empty($id_turma)): ?>
                <div class="alert alert-warning small">Sua conta ainda não está vinculada a nenhuma turma. Entre em contato com a secretaria ou administração.</div>
            <?php elseif (empty($postagens)): ?>
                <div class="alert alert-secondary small">Nenhuma postagem ou material pedagógico foi encontrado para o semestre <strong><?= htmlspecialchars($semestreSelecionado); ?></strong> e critérios selecionados.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($postagens as $p): ?>
                        <?php 
                            if ($p['tipo'] == 'apostila') {
                                $badge_bg = 'success';
                                $badge_label = '📚 Apostila / Conteúdo';
                            } elseif ($p['tipo'] == 'exercicio') {
                                $badge_bg = 'warning text-dark';
                                $badge_label = '📝 Lista de Exercício';
                            } else {
                                $badge_bg = 'info text-dark';
                                $badge_label = '📢 Notícia / Evento';
                            }
                        ?>
                        <div class="card p-3 border shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                                <div>
                                    <span class="badge bg-dark me-1">Semestre: <?= htmlspecialchars($p['semestre'] ?? $semestreSelecionado); ?></span>
                                    <span class="badge bg-<?= $badge_bg; ?> me-1"><?= $badge_label; ?></span>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['disciplina_nome']); ?></span>
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($p['data_postagem'])); ?></small>
                            </div>
                            
                            <h5 class="fw-bold text-dark mb-1 fs-6"><?= htmlspecialchars($p['titulo']); ?></h5>
                            <p class="text-muted small mb-1"><em>Prof. <?= htmlspecialchars($p['professor_nome']); ?></em></p>

                            <?php if (!empty($p['descricao'])): ?>
                                <p class="small text-secondary mb-2"><?= nl2br(htmlspecialchars($p['descricao'])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($p['arquivo'])): ?>
                                <div class="mb-3">
                                    <a href="uploads/<?= htmlspecialchars($p['arquivo']); ?>" target="_blank" class="btn btn-outline-primary btn-sm" style="font-size: 0.75rem;">
                                        📥 Baixar Material do Professor
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- ÁREA DE SUBMISSÃO E STATUS DO EXERCÍCIO -->
                            <?php if ($p['tipo'] == 'exercicio'): ?>
                                <div class="p-3 my-1 rounded border bg-light">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                        <div>
                                            <span class="small fw-bold text-secondary">Status da sua entrega:</span>
                                            <?php 
                                                $statusAtual = $p['entrega_status'] ?? 'Pendente';
                                                if ($statusAtual == 'Pendente') {
                                                    echo '<span class="badge bg-secondary ms-1">Pendente</span>';
                                                } elseif ($statusAtual == 'Entregue') {
                                                    echo '<span class="badge bg-primary ms-1">Entregue</span>';
                                                } else {
                                                    echo '<span class="badge bg-success ms-1">Corrigido</span>';
                                                }
                                            ?>
                                        </div>
                                        <div>
                                            <?php if ($p['pontuacao'] !== null): ?>
                                                <span class="small text-muted me-2">Valor: <strong><?= number_format($p['pontuacao'], 2, ',', '.'); ?> pts</strong></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($p['entrega_nota'] !== null): ?>
                                                <span class="badge bg-success fs-7">Sua Nota: <?= number_format($p['entrega_nota'], 2, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Arquivo enviado anteriormente -->
                                    <?php if (!empty($p['arquivo_entrega'])): ?>
                                        <div class="small text-muted mb-2">
                                            <strong>Seu arquivo enviado:</strong> 
                                            <a href="uploads/<?= htmlspecialchars($p['arquivo_entrega']); ?>" target="_blank" class="text-primary text-decoration-underline ms-1">
                                                📄 Ver Envio (<?= date('d/m/Y H:i', strtotime($p['data_entrega'])); ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Feedback do professor -->
                                    <?php if (!empty($p['entrega_obs'])): ?>
                                        <div class="mb-2 p-2 bg-white rounded border small text-muted">
                                            <strong>💬 Feedback do Professor:</strong> <?= htmlspecialchars($p['entrega_obs']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Formulário de Upload do Aluno Responsivo -->
                                    <?php if ($statusAtual != 'Corrigido'): ?>
                                        <form action="mural_aluno.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" method="POST" enctype="multipart/form-data" class="mt-2 pt-2 border-top">
                                            <input type="hidden" name="acao" value="enviar_atividade">
                                            <input type="hidden" name="id_postagem" value="<?= $p['id']; ?>">
                                            
                                            <label class="form-label small fw-bold text-dark mb-1">
                                                <?= !empty($p['arquivo_entrega']) ? '🔄 Reenviar / Substituir Atividade:' : '📤 Enviar Resolução da Atividade:' ?>
                                            </label>
                                            
                                            <div class="d-flex flex-column flex-md-row gap-2">
                                                <input type="file" class="form-control form-control-sm" name="arquivo_aluno" required>
                                                <button type="submit" class="btn btn-success btn-sm fw-bold text-nowrap">
                                                    <?= !empty($p['arquivo_entrega']) ? 'Atualizar Envio' : 'Enviar Atividade' ?>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-success fw-bold mt-2 pt-2 border-top">
                                            ✔️ Atividade corrigida pelo professor. Submissões encerradas.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>