<?php
session_start();
require_once 'conexao.php';

// Verifica se é professor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'professor') {
    header("Location: index.php");
    exit;
}

$id_vinculo = intval($_GET['id'] ?? 0);
$id_professor = $_SESSION['usuario_id'];

// Valida se este vínculo pertence realmente ao professor logado para evitar acesso indevido
$stmt = $pdo->prepare("
    SELECT ptd.*, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome, ptd.id_turma 
    FROM professor_turma_disciplina ptd
    JOIN disciplinas d ON ptd.id_disciplina = d.id
    JOIN turmas t ON ptd.id_turma = t.id
    JOIN cursos c ON t.id_curso = c.id
    WHERE ptd.id = ? AND ptd.id_professor = ?
");
$stmt->execute([$id_vinculo, $id_professor]);
$vinculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vinculo) {
    echo "Acesso negado ou vínculo não encontrado.";
    exit;
}

// Define o semestre padrão atual
$anoAtual = date('Y');
$mesAtual = date('n');
$semestreAtualPadrao = ($mesAtual <= 6 ? '1-' : '2-') . $anoAtual;

// Gera a lista de opções de semestres retroagindo 3 anos
$listaSemestres = [];
for ($i = 0; $i <= 3; $i++) {
    $anoLoop = $anoAtual - $i;
    $listaSemestres[] = '2-' . $anoLoop;
    $listaSemestres[] = '1-' . $anoLoop;
}

$msg = "";
$tipo_msg = "";

// Ação de Exclusão
if (isset($_GET['acao']) && $_GET['acao'] == 'excluir') {
    $id_post = intval($_GET['id_post'] ?? 0);
    
    // Verifica se a postagem pertence a este vínculo do professor
    $stmtCheck = $pdo->prepare("SELECT * FROM postagens_disciplina WHERE id = ? AND id_professor_turma_disciplina = ?");
    $stmtCheck->execute([$id_post, $id_vinculo]);
    $postagem_atual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($postagem_atual) {
        // Remove arquivo físico se existir
        if (!empty($postagem_atual['arquivo']) && file_exists("uploads/" . $postagem_atual['arquivo'])) {
            unlink("uploads/" . $postagem_atual['arquivo']);
        }
        
        $del = $pdo->prepare("DELETE FROM postagens_disciplina WHERE id = ?");
        $del->execute([$id_post]);
        
        $msg = "Publicação excluída com sucesso!";
        $tipo_msg = "success";
    }
}

// Processar Edição ou Nova Postagem
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_post = intval($_POST['id_post'] ?? 0);
    $tipo = $_POST['tipo'] ?? ''; // 'apostila', 'exercicio' ou 'noticia'
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $semestrePost = trim($_POST['semestre'] ?? $semestreAtualPadrao);
    $pontuacao = ($tipo === 'exercicio' && !empty($_POST['pontuacao'])) ? floatval(str_replace(',', '.', $_POST['pontuacao'])) : null;
    $nome_arquivo = null;

    if (!empty($tipo) && !empty($titulo)) {
        
        // Validação anti-duplicidade
        if ($id_post > 0) {
            $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM postagens_disciplina WHERE id_professor_turma_disciplina = ? AND tipo = ? AND titulo = ? AND id != ?");
            $stmtDup->execute([$id_vinculo, $tipo, $titulo, $id_post]);
        } else {
            $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM postagens_disciplina WHERE id_professor_turma_disciplina = ? AND tipo = ? AND titulo = ?");
            $stmtDup->execute([$id_vinculo, $tipo, $titulo]);
        }
        
        if ($stmtDup->fetchColumn() > 0) {
            $msg = "Erro: Já existe uma publicação com este mesmo título e tipo para esta disciplina.";
            $tipo_msg = "danger";
        } else {
            // Tratamento de upload de arquivo (se houver)
            if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
                $pasta_upload = "uploads/";
                if (!is_dir($pasta_upload)) {
                    mkdir($pasta_upload, 0777, true);
                }
                $extensao = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
                $nome_arquivo = uniqid() . "." . $extensao;
                move_uploaded_file($_FILES['arquivo']['tmp_name'], $pasta_upload . $nome_arquivo);
            }

            if ($id_post > 0) {
                // Atualização (Edição)
                if ($nome_arquivo) {
                    $stmtAntigo = $pdo->prepare("SELECT arquivo FROM postagens_disciplina WHERE id = ?");
                    $stmtAntigo->execute([$id_post]);
                    $ant = $stmtAntigo->fetch(PDO::FETCH_ASSOC);
                    if (!empty($ant['arquivo']) && file_exists("uploads/" . $ant['arquivo'])) {
                        unlink("uploads/" . $ant['arquivo']);
                    }

                    $update = $pdo->prepare("UPDATE postagens_disciplina SET tipo = ?, titulo = ?, descricao = ?, semestre = ?, arquivo = ?, pontuacao = ? WHERE id = ? AND id_professor_turma_disciplina = ?");
                    $update->execute([$tipo, $titulo, $descricao, $semestrePost, $nome_arquivo, $pontuacao, $id_post, $id_vinculo]);
                } else {
                    $update = $pdo->prepare("UPDATE postagens_disciplina SET tipo = ?, titulo = ?, descricao = ?, semestre = ?, pontuacao = ? WHERE id = ? AND id_professor_turma_disciplina = ?");
                    $update->execute([$tipo, $titulo, $descricao, $semestrePost, $pontuacao, $id_post, $id_vinculo]);
                }
                
                $msg = "Publicação atualizada com sucesso!";
                $tipo_msg = "success";
            } else {
                // Inserção Nova
                $insert = $pdo->prepare("INSERT INTO postagens_disciplina (id_professor_turma_disciplina, tipo, titulo, descricao, semestre, arquivo, pontuacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($insert->execute([$id_vinculo, $tipo, $titulo, $descricao, $semestrePost, $nome_arquivo, $pontuacao])) {
                    $id_nova_postagem = $pdo->lastInsertId();

                    // Se for do tipo exercício, gera automaticamente os registos de entrega para os alunos da turma
                    if ($tipo === 'exercicio') {
                        $stmtAlunos = $pdo->prepare("SELECT id FROM usuarios WHERE tipo = 'aluno' AND id_turma = ?");
                        $stmtAlunos->execute([$vinculo['id_turma']]);
                        $alunosTurma = $stmtAlunos->fetchAll(PDO::FETCH_COLUMN);

                        if (!empty($alunosTurma)) {
                            $stmtEntrega = $pdo->prepare("INSERT INTO entregas_exercicios (id_postagem, id_aluno, status) VALUES (?, ?, 'Pendente')");
                            foreach ($alunosTurma as $idAluno) {
                                $stmtEntrega->execute([$id_nova_postagem, $idAluno]);
                            }
                        }
                    }

                    $msg = "Publicação realizada com sucesso!";
                    $tipo_msg = "success";
                } else {
                    $msg = "Erro ao realizar publicação.";
                    $tipo_msg = "danger";
                }
            }
        }
    } else {
        $msg = "Preencha pelo menos o título e selecione o tipo.";
        $tipo_msg = "danger";
    }
}

// Se estiver editando, busca os dados da postagem específica
$editando = false;
$dados_edicao = ['id' => '', 'tipo' => '', 'titulo' => '', 'descricao' => '', 'semestre' => $semestreAtualPadrao, 'arquivo' => '', 'pontuacao' => ''];
if (isset($_GET['acao']) && $_GET['acao'] == 'editar') {
    $id_post_edit = intval($_GET['id_post'] ?? 0);
    $stmtEdit = $pdo->prepare("SELECT * FROM postagens_disciplina WHERE id = ? AND id_professor_turma_disciplina = ?");
    $stmtEdit->execute([$id_post_edit, $id_vinculo]);
    $resEdit = $stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($resEdit) {
        $editando = true;
        $dados_edicao = $resEdit;
        if (empty($dados_edicao['semestre'])) {
            $dados_edicao['semestre'] = $semestreAtualPadrao;
        }
    }
}

// Buscar postagens anteriores desta disciplina/turma
$postagensStmt = $pdo->prepare("SELECT * FROM postagens_disciplina WHERE id_professor_turma_disciplina = ? ORDER BY data_postagem DESC");
$postagensStmt->execute([$id_vinculo]);
$postagens = $postagensStmt->fetchAll(PDO::FETCH_ASSOC);

// Identificação de Turno para estilização da Badge
$turnoLower = mb_strtolower($vinculo['turno']);
$classeTurno = 'turno-default';
if (strpos($turnoLower, 'manhã') !== false || strpos($turnoLower, 'manha') !== false) {
    $classeTurno = 'turno-manha';
} elseif (strpos($turnoLower, 'tarde') !== false) {
    $classeTurno = 'turno-tarde';
} elseif (strpos($turnoLower, 'noite') !== false) {
    $classeTurno = 'turno-noite';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Gerenciar Disciplina</title>
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

        /* Badges de Turno */
        .badge-turno {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.73rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .turno-manha { background-color: #fef3c7; color: #92400e; }
        .turno-tarde { background-color: #ffedd5; color: #9a3412; }
        .turno-noite { background-color: #e0e7ff; color: #3730a3; }
        .turno-default { background-color: #f1f5f9; color: #475569; }

        /* Botão Principal customizado */
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

        /* Estilo dos Cards de Histórico */
        .card-postagem {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .card-postagem:hover {
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
                <a href="atribuicoes_professor.php" class="btn btn-outline-light btn-sm text-nowrap fw-medium px-3">⬅️ Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <!-- Cabeçalho da Disciplina -->
            <div class="header-disciplina p-3 mb-3 shadow-sm">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);"><?= htmlspecialchars($vinculo['curso_nome']); ?></span>
                        <h3 class="text-dark fw-bold fs-4 mb-1"><?= htmlspecialchars($vinculo['disciplina_nome']); ?></h3>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-secondary px-3 py-2 fw-normal">Turma: <strong><?= htmlspecialchars($vinculo['turma_nome']); ?></strong></span>
                        <span class="badge-turno <?= $classeTurno; ?>"><?= htmlspecialchars($vinculo['turno']); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <!-- Botão de Expandir / Ocultar Formulário -->
            <div class="mb-3">
                <button class="btn btn-indigo-custom btn-sm w-100 fw-bold shadow-sm py-2" type="button" data-bs-toggle="collapse" data-bs-target="#formPublicacaoCollapse" aria-expanded="<?= ($editando || !empty($msg)) ? 'true' : 'false'; ?>" aria-controls="formPublicacaoCollapse">
                    <?= $editando ? '✏️ Editar Publicação (Clique para recolher/expandir)' : '➕ Nova Publicação / Material'; ?>
                </button>
            </div>

            <!-- Formulário Colapsável de Postagem / Edição -->
            <div class="collapse <?= ($editando || !empty($msg)) ? 'show' : ''; ?>" id="formPublicacaoCollapse">
                <div class="card p-3 border shadow-sm mb-4 bg-light rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-primary mb-0 fs-6"><?= $editando ? 'Editar Publicação' : 'Nova Publicação'; ?></h5>
                        <?php if ($editando): ?>
                            <a href="disciplina_detalhes.php?id=<?= $id_vinculo; ?>" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem;">Cancelar Edição</a>
                        <?php endif; ?>
                    </div>

                    <form action="disciplina_detalhes.php?id=<?= $id_vinculo; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id_post" value="<?= $dados_edicao['id']; ?>">
                        
                        <div class="row">
                            <!-- Seleção do Semestre -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Semestre</label>
                                <select class="form-select form-select-sm" name="semestre" required>
                                    <?php foreach ($listaSemestres as $sem): ?>
                                        <option value="<?= htmlspecialchars($sem); ?>" <?= ($sem === $dados_edicao['semestre']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($sem); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Tipo de Publicação -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">Tipo de Publicação</label>
                                <select class="form-select form-select-sm" id="tipoPublicacao" name="tipo" required onchange="togglePontuacao()">
                                    <option value="">Selecione...</option>
                                    <option value="apostila" <?= ($dados_edicao['tipo'] == 'apostila') ? 'selected' : ''; ?>>📚 Apostila / Conteúdo</option>
                                    <option value="exercicio" <?= ($dados_edicao['tipo'] == 'exercicio') ? 'selected' : ''; ?>>📝 Lista de Exercício</option>

                                </select>
                            </div>
                        </div>

                        <!-- Campo de Pontuação (Aparece apenas quando for Exercício) -->
                        <div class="mb-3" id="campoPontuacao" style="display: <?= ($dados_edicao['tipo'] == 'exercicio') ? 'block' : 'none'; ?>;">
                            <label class="form-label small fw-bold text-success">Pontuação Máxima do Exercício</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="pontuacao" value="<?= htmlspecialchars($dados_edicao['pontuacao'] ?? ''); ?>" placeholder="Ex: 10.0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Título</label>
                            <input type="text" class="form-control form-control-sm" name="titulo" value="<?= htmlspecialchars($dados_edicao['titulo']); ?>" required placeholder="Ex: Módulo 01 - Introdução / Lista 02 de Fixação">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Descrição / Instruções</label>
                            <textarea class="form-control form-control-sm" name="descricao" rows="3" placeholder="Detalhes do material ou aviso..."><?= htmlspecialchars($dados_edicao['descricao']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Anexar Arquivo (Opcional - PDF, Imagem, Word)</label>
                            <input type="file" class="form-control form-control-sm" name="arquivo">
                            <?php if (!empty($dados_edicao['arquivo'])): ?>
                                <small class="text-muted d-block mt-1">Arquivo atual: <a href="uploads/<?= htmlspecialchars($dados_edicao['arquivo']); ?>" target="_blank"><?= htmlspecialchars($dados_edicao['arquivo']); ?></a></small>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-indigo-custom btn-sm w-100 py-2"><?= $editando ? 'Salvar Alterações' : 'Publicar Conteúdo'; ?></button>
                    </form>
                </div>
            </div>

            <!-- Listagem de Publicações Feitas -->
            <h5 class="fw-bold mb-3 fs-6 text-secondary">Histórico de Publicações</h5>
            <?php if (empty($postagens)): ?>
                <div class="alert alert-secondary text-center py-4 small rounded-3">Nenhuma postagem realizada nesta disciplina ainda.</div>
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
                            $semestreExibir = !empty($p['semestre']) ? $p['semestre'] : 'N/D';
                        ?>
                        <div class="card card-postagem p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                                <div>
                                    <span class="badge bg-dark me-1">Semestre: <?= htmlspecialchars($semestreExibir); ?></span>
                                    <span class="badge bg-<?= $badge_bg; ?> me-1"><?= $badge_label; ?></span>
                                    <?php if ($p['tipo'] == 'exercicio' && isset($p['pontuacao']) && $p['pontuacao'] !== null): ?>
                                        <span class="badge bg-success me-1">Valor: <?= number_format($p['pontuacao'], 2, ',', '.'); ?> pts</span>
                                    <?php endif; ?>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($p['data_postagem'])); ?></small>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="disciplina_detalhes.php?id=<?= $id_vinculo; ?>&acao=editar&id_post=<?= $p['id']; ?>" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;">✏️ Editar</a>
                                    <a href="disciplina_detalhes.php?id=<?= $id_vinculo; ?>&acao=excluir&id_post=<?= $p['id']; ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;" onclick="return confirm('Tem certeza que deseja excluir esta publicação?');">🗑️ Excluir</a>
                                </div>
                            </div>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['titulo']); ?></h6>
                            <?php if (!empty($p['descricao'])): ?>
                                <p class="small text-muted mb-2"><?= nl2br(htmlspecialchars($p['descricao'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php if (!empty($p['arquivo'])): ?>
                                    <a href="uploads/<?= htmlspecialchars($p['arquivo']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem;">
                                        📥 Baixar Arquivo Anexado
                                    </a>
                                <?php endif; ?>

                                <!-- Botão específico para gerenciar entregas apenas se for do tipo exercício -->
                                <?php if ($p['tipo'] == 'exercicio'): ?>
                                    <a href="controle_entregas.php?id_post=<?= $p['id']; ?>&id_vinculo=<?= $id_vinculo; ?>" class="btn btn-success btn-sm" style="font-size: 0.75rem;">
                                        📋 Controle de Entregas / Notas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Bootstrap JS e Script de controle do campo pontuação -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePontuacao() {
            const tipo = document.getElementById('tipoPublicacao').value;
            const campoPontuacao = document.getElementById('campoPontuacao');
            if (tipo === 'exercicio') {
                campoPontuacao.style.display = 'block';
            } else {
                campoPontuacao.style.display = 'none';
            }
        }
    </script>
</body>
</html>