<?php
session_start();
require_once 'conexao.php';

// Verifica se é aluno
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

// Lógica de upload de atividade pelo aluno
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
            $checkEntrega = $pdo->prepare("SELECT id, arquivo_entrega FROM entregas_exercicios WHERE id_postagem = ? AND id_aluno = ?");
            $checkEntrega->execute([$id_postagem, $id_aluno]);
            $entregaExistente = $checkEntrega->fetch(PDO::FETCH_ASSOC);

            if ($entregaExistente) {
                if (!empty($entregaExistente['arquivo_entrega']) && file_exists($pasta_upload . $entregaExistente['arquivo_entrega'])) {
                    unlink($pasta_upload . $entregaExistente['arquivo_entrega']);
                }

                $stmtUpdate = $pdo->prepare("
                    UPDATE entregas_exercicios 
                    SET arquivo_entrega = ?, data_entrega = NOW(), status = 'Entregue' 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$nome_arquivo_envio, $entregaExistente['id']]);
            } else {
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
        $msg = "Por favor, selecione um arquivo válido.";
        $tipo_msg = "warning";
    }
}

$id_turma = $aluno['id_turma'] ?? 0;

// Parâmetros de Filtro
$filtro_disciplina = $_GET['disciplina'] ?? '';
$filtro_tipo       = $_GET['tipo'] ?? '';
$filtro_status     = $_GET['status_entrega'] ?? '';

// Cores do topo baseadas no curso
$cursoNomeLower = mb_strtolower(trim($aluno['curso_nome'] ?? ''));
$corNavbar = 'linear-gradient(135deg, #495057 0%, #6c757d 100%)';
if (strpos($cursoNomeLower, 'informatica') !== false || strpos($cursoNomeLower, 'informática') !== false) {
    $corNavbar = 'linear-gradient(135deg, #1e7e34 0%, #28a745 100%)';
} elseif (strpos($cursoNomeLower, 'administracao') !== false || strpos($cursoNomeLower, 'administração') !== false) {
    $corNavbar = 'linear-gradient(135deg, #003366 0%, #0056b3 100%)';
}

$disciplinas = [];
$postagens = [];
$totalPendentes = 0;
$totalEntregues = 0;
$totalCorrigidos = 0;

if ($id_turma > 0) {
    // 1. Busca lista de disciplinas da turma para preencher o SELECT do filtro
    $stmtDisc = $pdo->prepare("
        SELECT DISTINCT d.id, d.nome 
        FROM disciplinas d
        JOIN professor_turma_disciplina ptd ON ptd.id_disciplina = d.id
        WHERE ptd.id_turma = ?
        ORDER BY d.nome ASC
    ");
    $stmtDisc->execute([$id_turma]);
    $disciplinas = $stmtDisc->fetchAll(PDO::FETCH_ASSOC);

    // 2. Monta consulta SQL flexível com suporte aos filtros dinâmicos
    $sql = "
        SELECT p.*, d.id as disciplina_id, d.nome as disciplina_nome, u.nome as professor_nome,
               ee.status as entrega_status, ee.nota as entrega_nota, ee.observacoes as entrega_obs, 
               ee.arquivo_entrega, ee.data_entrega
        FROM postagens_disciplina p
        JOIN professor_turma_disciplina ptd ON p.id_professor_turma_disciplina = ptd.id
        JOIN disciplinas d ON ptd.id_disciplina = d.id
        JOIN usuarios u ON ptd.id_professor = u.id
        LEFT JOIN entregas_exercicios ee ON ee.id_postagem = p.id AND ee.id_aluno = ?
        WHERE ptd.id_turma = ?
    ";
    
    $params = [$id_aluno, $id_turma];

    // Aplicação do Filtro de Disciplina
    if (!empty($filtro_disciplina) && is_numeric($filtro_disciplina)) {
        $sql .= " AND d.id = ?";
        $params[] = $filtro_disciplina;
    }

    // Aplicação do Filtro de Tipo de Postagem
    if (!empty($filtro_tipo)) {
        $sql .= " AND p.tipo = ?";
        $params[] = $filtro_tipo;
    }

    // Aplicação do Filtro de Status de Entrega
    if (!empty($filtro_status)) {
        if ($filtro_status === 'Pendente') {
            $sql .= " AND p.tipo = 'exercicio' AND (ee.status IS NULL OR ee.status = 'Pendente')";
        } else {
            $sql .= " AND ee.status = ?";
            $params[] = $filtro_status;
        }
    }

    $sql .= " ORDER BY p.data_postagem DESC";

    $stmtPostagens = $pdo->prepare($sql);
    $stmtPostagens->execute($params);
    $postagens = $stmtPostagens->fetchAll(PDO::FETCH_ASSOC);

    // Contadores globais de estatísticas para os cards superiores
    $stmtStats = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN ee.status IS NULL OR ee.status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN ee.status = 'Entregue' THEN 1 ELSE 0 END) as entregues,
            SUM(CASE WHEN ee.status = 'Corrigido' THEN 1 ELSE 0 END) as corrigidos
        FROM postagens_disciplina p
        JOIN professor_turma_disciplina ptd ON p.id_professor_turma_disciplina = ptd.id
        LEFT JOIN entregas_exercicios ee ON ee.id_postagem = p.id AND ee.id_aluno = ?
        WHERE ptd.id_turma = ? AND p.tipo = 'exercicio'
    ");
    $stmtStats->execute([$id_aluno, $id_turma]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $totalPendentes  = $stats['pendentes'] ?? 0;
    $totalEntregues  = $stats['entregues'] ?? 0;
    $totalCorrigidos = $stats['corrigidos'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Painel Geral de Materiais e Atividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --cepdap-navbar: <?= $corNavbar; ?>; }
        body { background-color: #f8fafc; color: #334155; font-family: system-ui, -apple-system, sans-serif; }

        .container-fluid-custom {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .logo-img { max-width: 40px; height: auto; }
        .navbar-curso { background: var(--cepdap-navbar) !important; }
        
        .card-stat {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .publicacao-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            transition: all 0.2s ease;
        }
        .publicacao-card:hover {
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .card-body-content { flex-grow: 1; }
    </style>
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar navbar-dark navbar-curso shadow-sm py-2 px-3">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="painel_aluno.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold text-white">Painel Geral</span>
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm text-nowrap fw-semibold">Sair</a>
        </div>
    </nav>

    <!-- Contentor Principal -->
    <div class="container-fluid-custom my-4">
        
        <div class="d-flex flex-column mb-4 border-bottom pb-3">
            <h4 class="fw-bold text-dark fs-4 mb-1">Painel de Materiais e Entregas</h4>
            <p class="text-muted small mb-0">Acompanhe abaixo todas as apostilas, avisos e o estado das suas atividades académicas.</p>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3 mb-4" role="alert"><?= $msg; ?></div>
        <?php endif; ?>

        <!-- Cards de Estatísticas Rápidas -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card card-stat p-3 text-center border-0 border-top border-warning border-4 shadow-sm">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem;">Pendentes</span>
                    <h3 class="fw-bold text-warning mb-0"><?= $totalPendentes; ?></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-stat p-3 text-center border-0 border-top border-primary border-4 shadow-sm">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem;">Entregues</span>
                    <h3 class="fw-bold text-primary mb-0"><?= $totalEntregues; ?></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-stat p-3 text-center border-0 border-top border-success border-4 shadow-sm">
                    <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem;">Corrigidos</span>
                    <h3 class="fw-bold text-success mb-0"><?= $totalCorrigidos; ?></h3>
                </div>
            </div>
        </div>

        <!-- FORMULÁRIO DE FILTROS DINÂMICOS -->
        <?php if ($id_turma > 0): ?>
            <div class="card p-3 mb-4 border-0 shadow-sm bg-white rounded-3">
                <form method="GET" action="" class="row g-2 align-items-end">
                    
                    <!-- Filtro por Disciplina -->
                    <div class="col-12 col-md-4">
                        <label for="disciplina" class="form-label small fw-bold text-secondary mb-1">Disciplina:</label>
                        <select name="disciplina" id="disciplina" class="form-select form-select-sm">
                            <option value="">Todas as disciplinas</option>
                            <?php foreach ($disciplinas as $d): ?>
                                <option value="<?= $d['id']; ?>" <?= ($filtro_disciplina == $d['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($d['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro por Tipo de Postagem -->
                    <div class="col-12 col-md-3">
                        <label for="tipo" class="form-label small fw-bold text-secondary mb-1">Tipo de Postagem:</label>
                        <select name="tipo" id="tipo" class="form-select form-select-sm">
                            <option value="">Todos os tipos</option>
                            <option value="apostila" <?= ($filtro_tipo == 'apostila') ? 'selected' : ''; ?>>📚 Apostila / Conteúdo</option>
                            <option value="exercicio" <?= ($filtro_tipo == 'exercicio') ? 'selected' : ''; ?>>📝 Lista de Exercício</option>
                 
                        </select>
                    </div>

                    <!-- Filtro por Status da Entrega (para Exercícios) -->
                    <div class="col-12 col-md-3">
                        <label for="status_entrega" class="form-label small fw-bold text-secondary mb-1">Status da Entrega:</label>
                        <select name="status_entrega" id="status_entrega" class="form-select form-select-sm">
                            <option value="">Todos os status</option>
                            <option value="Pendente" <?= ($filtro_status == 'Pendente') ? 'selected' : ''; ?>>⏳ Pendente</option>
                            <option value="Entregue" <?= ($filtro_status == 'Entregue') ? 'selected' : ''; ?>>📤 Entregue</option>
                            <option value="Corrigido" <?= ($filtro_status == 'Corrigido') ? 'selected' : ''; ?>>✔️ Corrigido</option>
                        </select>
                    </div>

                    <!-- Botões de Ação dos Filtros -->
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Filtrar</button>
                        <?php if (!empty($filtro_disciplina) || !empty($filtro_tipo) || !empty($filtro_status)): ?>
                            <a href="painel_materiais_aluno.php" class="btn btn-outline-secondary btn-sm text-nowrap">Limpar</a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        <?php endif; ?>

        <h5 class="fw-bold mb-3 fs-6 text-secondary">Histórico de Publicações</h5>

        <?php if (empty($id_turma)): ?>
            <div class="alert alert-warning small">Sua conta não está vinculada a nenhuma turma. Entre em contato com a secretaria.</div>
        <?php elseif (empty($postagens)): ?>
            <div class="alert alert-secondary small">Nenhum material ou exercício encontrado com os filtros selecionados.</div>
        <?php else: ?>
            
            <!-- Grelha de Cards de Publicações -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($postagens as $p): ?>
                    <?php 
                        if ($p['tipo'] == 'apostila') {
                            $badge_bg = 'success';
                            $badge_label = '📚 Apostila';
                        } elseif ($p['tipo'] == 'exercicio') {
                            $badge_bg = 'warning text-dark';
                            $badge_label = '📝 Exercício';
                        } else {
                            $badge_bg = 'info text-dark';
                            $badge_label = '📢 Evento/Aviso';
                        }
                    ?>
                    <div class="col">
                        <div class="publicacao-card p-3 shadow-sm h-100">
                            <div class="card-body-content">
                                <div class="d-flex justify-content-between align-items-start mb-2 gap-1">
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-<?= $badge_bg; ?>" style="font-size: 0.7rem;"><?= $badge_label; ?></span>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.7rem;"><?= htmlspecialchars($p['disciplina_nome']); ?></span>
                                    </div>
                                    <small class="text-muted text-nowrap" style="font-size: 0.7rem;"><?= date('d/m/Y', strtotime($p['data_postagem'])); ?></small>
                                </div>
                                
                                <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($p['titulo']); ?>"><?= htmlspecialchars($p['titulo']); ?></h6>
                                <p class="text-muted small mb-2" style="font-size: 0.78rem;"><em>Prof. <?= htmlspecialchars($p['professor_nome']); ?></em></p>

                                <?php if (!empty($p['descricao'])): ?>
                                    <p class="small text-secondary mb-3" style="font-size: 0.82rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;">
                                        <?= nl2br(htmlspecialchars($p['descricao'])); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($p['arquivo'])): ?>
                                    <div class="mb-3">
                                        <a href="uploads/<?= htmlspecialchars($p['arquivo']); ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 fw-semibold" style="font-size: 0.75rem;">
                                            📥 Baixar Material de Apoio
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Painel do Exercício e Controle de Entrega -->
                            <?php if ($p['tipo'] == 'exercicio'): ?>
                                <div class="p-2 rounded border bg-light mt-2" style="font-size: 0.8rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-secondary" style="font-size: 0.75rem;">Status:</span>
                                        <?php 
                                            $statusAtual = $p['entrega_status'] ?? 'Pendente';
                                            if ($statusAtual == 'Pendente') {
                                                echo '<span class="badge bg-secondary">Pendente</span>';
                                            } elseif ($statusAtual == 'Entregue') {
                                                echo '<span class="badge bg-primary">Entregue</span>';
                                            } else {
                                                echo '<span class="badge bg-success">Corrigido</span>';
                                            }
                                        ?>
                                    </div>

                                    <?php if ($p['pontuacao'] !== null || $p['entrega_nota'] !== null): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.75rem;">
                                            <?php if ($p['pontuacao'] !== null): ?>
                                                <span class="text-muted">Valor: <strong><?= number_format($p['pontuacao'], 2, ',', '.'); ?> pts</strong></span>
                                            <?php endif; ?>
                                            <?php if ($p['entrega_nota'] !== null): ?>
                                                <span class="badge bg-success">Nota: <?= number_format($p['entrega_nota'], 2, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($p['arquivo_entrega'])): ?>
                                        <div class="text-muted mb-1 text-truncate" style="font-size: 0.75rem;">
                                            <a href="uploads/<?= htmlspecialchars($p['arquivo_entrega']); ?>" target="_blank" class="text-primary text-decoration-underline fw-semibold">
                                                📄 Ver Envio (<?= date('d/m/Y', strtotime($p['data_entrega'])); ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($p['entrega_obs'])): ?>
                                        <div class="p-1 my-1 bg-white rounded border text-muted" style="font-size: 0.75rem;">
                                            <strong>💬 Feedback:</strong> <?= htmlspecialchars($p['entrega_obs']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($statusAtual != 'Corrigido'): ?>
                                        <form action="painel_materiais_aluno.php" method="POST" enctype="multipart/form-data" class="mt-2 pt-2 border-top">
                                            <input type="hidden" name="acao" value="enviar_atividade">
                                            <input type="hidden" name="id_postagem" value="<?= $p['id']; ?>">
                                            
                                            <div class="d-flex flex-column gap-1">
                                                <input type="file" class="form-control form-control-sm" name="arquivo_aluno" style="font-size: 0.75rem;" required>
                                                <button type="submit" class="btn btn-success btn-sm fw-bold w-100" style="font-size: 0.75rem;">
                                                    <?= !empty($p['arquivo_entrega']) ? '🔄 Reenviar' : '📤 Enviar Resolução' ?>
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="text-success fw-bold text-center mt-1 pt-1 border-top" style="font-size: 0.75rem;">
                                            ✔️ Submissão encerrada
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>