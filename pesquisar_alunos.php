<?php
session_start();
require_once 'conexao.php';

// Verifica se é administrador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Filtros recebidos via GET
$filtro_curso = $_GET['curso'] ?? '';
$filtro_turma = $_GET['turma'] ?? '';
$busca_nome = trim($_GET['busca'] ?? '');

// Busca lista de cursos para o select de filtro
$stmtCursos = $pdo->query("SELECT id, nome FROM cursos ORDER BY nome ASC");
$cursosList = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

// Busca lista de turmas para o select de filtro
$stmtTurmas = $pdo->query("SELECT id, nome, turno FROM turmas ORDER BY nome ASC");
$turmasList = $stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// Montagem da query de consulta dos alunos com filtros dinâmicos
$sql = "
    SELECT u.*, c.nome as curso_nome, t.nome as turma_nome, t.turno 
    FROM usuarios u 
    LEFT JOIN cursos c ON u.id_curso = c.id
    LEFT JOIN turmas t ON u.id_turma = t.id
    WHERE u.tipo = 'aluno'
";
$params = [];

if (!empty($filtro_curso) && is_numeric($filtro_curso)) {
    $sql .= " AND u.id_curso = ?";
    $params[] = $filtro_curso;
}

if (!empty($filtro_turma) && is_numeric($filtro_turma)) {
    $sql .= " AND u.id_turma = ?";
    $params[] = $filtro_turma;
}

if (!empty($busca_nome)) {
    $sql .= " AND (u.nome LIKE ? OR u.nome_social LIKE ? OR u.cpf LIKE ?)";
    $likeTerm = "%" . $busca_nome . "%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
}

$sql .= " ORDER BY u.nome ASC";

$stmtAlunos = $pdo->prepare($sql);
$stmtAlunos->execute($params);
$alunos = $stmtAlunos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Pesquisa de Alunos e Carteirinhas</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
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
            .container { padding-left: 8px; padding-right: 8px; }
            .mobile-wrapper { background: #ffffff; padding: 10px 0; box-shadow: none; border-radius: 0; width: 100%; margin: 0; }
        }

        .logo-nav { max-width: 45px; height: auto; }
        
        .avatar-tabela {
            width: 38px;
            height: 38px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0056b3;
        }

        .avatar-card {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0056b3;
        }
    </style>
</head>
<body>

    <!-- Navbar Superior -->
    <nav class="navbar navbar-dark shadow-sm py-2" style="background-color: var(--cepdap-blue);">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="#">
                <img src="logo.png" alt="Logo" class="logo-nav me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Carteirinhas</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="painel_admin.php" class="btn btn-outline-light btn-sm text-nowrap">← Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-2 my-lg-4">
        <div class="a4-desktop mobile-wrapper">

            <div class="text-center mb-3">
                <h4 class="fw-bold text-primary fs-5 fs-md-4 mb-1">Pesquisa e Emissão de Carteirinhas</h4>
                <p class="text-muted small px-2">Filtre por curso, turma ou busque pelo nome/CPF para acessar a carteirinha digital.</p>
            </div>

            <!-- Formulário de Filtros Otimizado -->
            <form method="GET" action="pesquisar_alunos.php" class="row g-2 mb-3 bg-light p-3 rounded border shadow-sm align-items-end">
                <div class="col-12 col-md-3">
                    <label for="curso" class="form-label small fw-bold text-secondary mb-1">Curso:</label>
                    <select name="curso" id="curso" class="form-select form-select-sm">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($cursosList as $c): ?>
                            <option value="<?= $c['id']; ?>" <?= ($filtro_curso == $c['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($c['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label for="turma" class="form-label small fw-bold text-secondary mb-1">Turma:</label>
                    <select name="turma" id="turma" class="form-select form-select-sm">
                        <option value="">Todas as turmas</option>
                        <?php foreach ($turmasList as $t): ?>
                            <option value="<?= $t['id']; ?>" <?= ($filtro_turma == $t['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($t['nome']); ?> (<?= htmlspecialchars($t['turno']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label for="busca" class="form-label small fw-bold text-secondary mb-1">Nome ou CPF:</label>
                    <input type="text" name="busca" id="busca" class="form-control form-control-sm" value="<?= htmlspecialchars($busca_nome); ?>" placeholder="Digite para buscar...">
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                    <?php if (!empty($filtro_curso) || !empty($filtro_turma) || !empty($busca_nome)): ?>
                        <a href="pesquisar_alunos.php" class="btn btn-outline-secondary btn-sm text-nowrap">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <h6 class="fw-bold text-secondary mb-0 small">👥 Alunos Localizados (<?= count($alunos); ?>)</h6>
            </div>

            <?php if (empty($alunos)): ?>
                <div class="alert alert-secondary small text-center py-4">Nenhum aluno encontrado com os filtros selecionados.</div>
            <?php else: ?>

                <!-- VISUALIZAÇÃO EM CARDS PARA DISPOSITIVOS MÓVEIS (Aparece apenas em telas pequenas) -->
                <div class="d-block d-lg-none">
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($alunos as $al): ?>
                            <?php 
                                $nomeExibicao = !empty($al['nome_social']) ? $al['nome_social'] : $al['nome'];
                            ?>
                            <div class="card p-3 border shadow-sm">
                                <div class="d-flex align-items-center gap-3">
                                    <div>
                                        <?php if (!empty($al['foto']) && file_exists("uploads/" . $al['foto'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($al['foto']); ?>" alt="Foto" class="avatar-card shadow-sm">
                                        <?php else: ?>
                                            <div class="avatar-card d-flex align-items-center justify-content-center bg-secondary text-white fs-5 shadow-sm">👤</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 0.95rem;"><?= htmlspecialchars($nomeExibicao); ?></h6>
                                        <?php if (!empty($al['nome_social'])): ?>
                                            <small class="text-muted d-block" style="font-size: 0.72rem;">Civil: <?= htmlspecialchars($al['nome']); ?></small>
                                        <?php endif; ?>
                                        <p class="text-secondary small mb-1 mt-1" style="font-size: 0.78rem; line-height: 1.1;">
                                            <strong>Curso:</strong> <?= htmlspecialchars($al['curso_nome'] ?? 'Não vinculado'); ?><br>
                                            <strong>Turma:</strong> <?= htmlspecialchars($al['turma_nome'] ?? 'N/A'); ?> (<?= htmlspecialchars($al['turno'] ?? '-'); ?>)
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 pt-2 border-top text-end">
                                    <a href="carteirinha.php?id=<?= $al['id']; ?>" target="_blank" class="btn btn-success btn-sm w-100 py-1" style="font-size: 0.8rem;">
                                        🪪 Ver Carteirinha Digital
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- VISUALIZAÇÃO EM TABELA PARA COMPUTADOR / DESKTOP (Aparece em telas grandes) -->
                <div class="d-none d-lg-block">
                    <div class="card shadow-sm border-0 p-2">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Foto</th>
                                        <th>Nome / Nome Social</th>
                                        <th>Curso</th>
                                        <th>Turma</th>
                                        <th class="text-center" style="width: 150px;">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alunos as $al): ?>
                                        <?php 
                                            $nomeExibicao = !empty($al['nome_social']) ? $al['nome_social'] : $al['nome'];
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($al['foto']) && file_exists("uploads/" . $al['foto'])): ?>
                                                    <img src="uploads/<?= htmlspecialchars($al['foto']); ?>" alt="Foto" class="avatar-tabela">
                                                <?php else: ?>
                                                    <div class="avatar-tabela d-flex align-items-center justify-content-center bg-secondary text-white fs-6">👤</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold d-block text-dark"><?= htmlspecialchars($nomeExibicao); ?></span>
                                                <?php if (!empty($al['nome_social'])): ?>
                                                    <small class="text-muted">Civil: <?= htmlspecialchars($al['nome']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($al['curso_nome'] ?? 'Não vinculado'); ?></td>
                                            <td><?= htmlspecialchars($al['turma_nome'] ?? 'N/A'); ?> (<?= htmlspecialchars($al['turno'] ?? '-'); ?>)</td>
                                            <td class="text-center">
                                                <a href="carteirinha.php?id=<?= $al['id']; ?>" target="_blank" class="btn btn-success btn-sm text-nowrap">
                                                    🪪 Ver Carteirinha
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>