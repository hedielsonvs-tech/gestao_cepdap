<?php
session_start();
require_once 'conexao.php';

// Verifica se é administrador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'admin') {
    header("Location: index.php");
    exit;
}

$msg = "";
$tipo_msg = "";

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

// Ações do Administrador via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Aprovar Usuário
    if ($acao == 'aprovar_usuario') {
        $id_u = intval($_POST['id_usuario']);
        $stmt = $pdo->prepare("UPDATE usuarios SET status = 'aprovado' WHERE id = ?");
        if ($stmt->execute([$id_u])) {
            $msg = "Usuário aprovado com sucesso!";
            $tipo_msg = "success";
        }
    }
    // 2. Criar Disciplina
    elseif ($acao == 'nova_disciplina') {
        $nome_disc = trim($_POST['nome_disciplina']);
        if (!empty($nome_disc)) {
            $stmt = $pdo->prepare("INSERT INTO disciplinas (nome) VALUES (?)");
            if ($stmt->execute([$nome_disc])) {
                $msg = "Disciplina cadastrada com sucesso!";
                $tipo_msg = "success";
            }
        }
    }
    // 3. Criar Turma (Com suporte a Manhã, Tarde e Noite)
    elseif ($acao == 'nova_turma') {
        $nome_turma = trim($_POST['nome_turma']);
        $id_curso = intval($_POST['id_curso']);
        $turno = trim($_POST['turno'] ?? '');
        $sala = trim($_POST['sala'] ?? 'SALA 1');

        if (!empty($nome_turma) && !empty($id_curso) && !empty($turno)) {
            $stmt = $pdo->prepare("INSERT INTO turmas (nome, id_curso, turno, sala) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nome_turma, $id_curso, $turno, $sala])) {
                $msg = "Turma criada com sucesso!";
                $tipo_msg = "success";
            }
        } else {
            $msg = "Preencha todos os campos da turma, incluindo o turno.";
            $tipo_msg = "danger";
        }
    }
    // 4. Vincular Professor à Turma e Disciplina
    elseif ($acao == 'vincular_professor') {
        $id_prof = intval($_POST['id_professor']);
        $id_disc = intval($_POST['id_disciplina']);
        $id_turm = intval($_POST['id_turma']);
        $semestreVinculo = trim($_POST['semestre'] ?? $semestreAtualPadrao);
        
        if (!empty($id_prof) && !empty($id_disc) && !empty($id_turm)) {
            $checkStmt = $pdo->prepare("SELECT id FROM professor_turma_disciplina WHERE id_professor = ? AND id_disciplina = ? AND id_turma = ? AND semestre = ?");
            $checkStmt->execute([$id_prof, $id_disc, $id_turm, $semestreVinculo]);
            
            if ($checkStmt->rowCount() > 0) {
                $msg = "Este professor já está vinculado a esta disciplina nesta turma para este semestre!";
                $tipo_msg = "danger";
            } else {
                $stmt = $pdo->prepare("INSERT INTO professor_turma_disciplina (id_professor, id_disciplina, id_turma, semestre) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$id_prof, $id_disc, $id_turm, $semestreVinculo])) {
                    $msg = "Professor vinculado com sucesso!";
                    $tipo_msg = "success";
                }
            }
        } else {
            $msg = "Preencha todos os campos para efetivar o vínculo.";
            $tipo_msg = "danger";
        }
    }
    // 5. Atualizar Configurações de Geolocalização
    elseif ($acao == 'atualizar_configuracoes') {
        $latEscola = trim($_POST['latEscola']);
        $lonEscola = trim($_POST['lonEscola']);
        $toleranciaMetros = trim($_POST['toleranciaMetros']);

        if ($latEscola !== '' && $lonEscola !== '' && $toleranciaMetros !== '') {
            $stmt1 = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'latEscola'");
            $stmt1->execute([$latEscola]);

            $stmt2 = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'lonEscola'");
            $stmt2->execute([$lonEscola]);

            $stmt3 = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'toleranciaMetros'");
            $stmt3->execute([$toleranciaMetros]);

            $msg = "Configurações de geolocalização atualizadas com sucesso!";
            $tipo_msg = "success";
        } else {
            $msg = "Preencha todos os campos de configuração.";
            $tipo_msg = "danger";
        }
    }
}

// Consultas para exibição nos painéis
$usuariosPendentes = $pdo->query("SELECT u.*, c.nome as curso_nome, t.nome as turma_nome FROM usuarios u LEFT JOIN cursos c ON u.id_curso = c.id LEFT JOIN turmas t ON u.id_turma = t.id WHERE u.status = 'pendente'")->fetchAll(PDO::FETCH_ASSOC);
$professores = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'professor' AND status = 'aprovado'")->fetchAll(PDO::FETCH_ASSOC);
$disciplinas = $pdo->query("SELECT * FROM disciplinas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$cursos = $pdo->query("SELECT * FROM cursos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$turmas = $pdo->query("SELECT t.*, c.nome as curso_nome FROM turmas t JOIN cursos c ON t.id_curso = c.id ORDER BY t.nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Consulta configurações atuais
$configsDb = [];
$resConfig = $pdo->query("SELECT chave, valor FROM configuracoes");
while ($row = $resConfig->fetch(PDO::FETCH_ASSOC)) {
    $configsDb[$row['chave']] = $row['valor'];
}

$latEscolaVal = $configsDb['latEscola'] ?? '-1.3566733';
$lonEscolaVal = $configsDb['lonEscola'] ?? '-48.3514295';
$toleranciaVal = $configsDb['toleranciaMetros'] ?? '1500';

// Consulta para listar os professores já vinculados
$vinculosListagem = $pdo->query("
    SELECT ptd.id, p.nome as professor_nome, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome, ptd.semestre 
    FROM professor_turma_disciplina ptd
    JOIN usuarios p ON ptd.id_professor = p.id
    JOIN disciplinas d ON ptd.id_disciplina = d.id
    JOIN turmas t ON ptd.id_turma = t.id
    JOIN cursos c ON t.id_curso = c.id
    ORDER BY p.nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

$secaoAtiva = $_GET['secao'] ?? 'menu';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Painel do Administrador</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
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

        .logo-img { max-width: 70px; height: auto; }
        .btn-cepdap { background-color: var(--cepdap-green); color: white; }
        .btn-cepdap:hover { background-color: #218838; color: white; }

        /* Grid de Cards do Menu Inicial */
        .admin-menu-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            .admin-menu-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .card-menu-admin {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 22px 14px;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .card-menu-admin:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
        }

        .card-aprovacoes { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .card-turmas { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
        .card-vincular { background: #fef3c7; border-color: #fde68a; color: #b45309; }
        .card-vinculos { background: #f3e8ff; border-color: #d8b4fe; color: #6b21a8; }
        .card-carteirinhas { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
        .card-frequencia { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
        .card-indicadores { background: #e0f2fe; border-color: #7dd3fc; color: #0369a1; }
        .card-configuracoes { background: #f8fafc; border-color: #cbd5e1; color: #334155; }

        .card-menu-admin .icon-container { font-size: 2rem; margin-bottom: 8px; line-height: 1; }
        .card-menu-admin .title { font-size: 0.9rem; font-weight: 700; line-height: 1.2; }

        .card-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm py-2" style="background-color: var(--cepdap-blue);">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="painel_admin.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Painel Administrador</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white small d-none d-sm-inline">Olá, <?= htmlspecialchars($_SESSION['usuario_nome']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="text-primary fw-bold fs-4 mb-1">Gerenciamento Escolar</h3>
                    <p class="text-muted small mb-0">Painel de controle administrativo do sistema.</p>
                </div>
                <?php if ($secaoAtiva !== 'menu'): ?>
                    <div>
                        <a href="painel_admin.php" class="btn btn-outline-secondary btn-sm fw-bold">⬅️ Voltar</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> py-2 small shadow-sm rounded-3" role="alert"><?= $msg; ?></div>
            <?php endif; ?>

            <!-- MENU PRINCIPAL COM 8 CARDS -->
            <?php if ($secaoAtiva === 'menu'): ?>
                <div class="admin-menu-cards">
                    <!-- 1. Aprovações -->
                    <a href="painel_admin.php?secao=aprovacoes" class="card-menu-admin card-aprovacoes">
                        <?php if (count($usuariosPendentes) > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= count($usuariosPendentes); ?>
                            </span>
                        <?php endif; ?>
                        <div class="icon-container">👥</div>
                        <span class="title">Aprovações (<?= count($usuariosPendentes); ?>)</span>
                    </a>

                    <!-- 2. Turmas & Disciplinas -->
                    <a href="painel_admin.php?secao=turmas" class="card-menu-admin card-turmas">
                        <div class="icon-container">📚</div>
                        <span class="title">Turmas & Disciplinas</span>
                    </a>

                    <!-- 3. Vincular Professor -->
                    <a href="painel_admin.php?secao=vincular" class="card-menu-admin card-vincular">
                        <div class="icon-container">🔗</div>
                        <span class="title">Vincular Professor</span>
                    </a>

                    <!-- 4. Professores Vinculados -->
                    <a href="painel_admin.php?secao=vinculos" class="card-menu-admin card-vinculos">
                        <div class="icon-container">📋</div>
                        <span class="title">Professores Vinculados</span>
                    </a>

                    <!-- 5. Carteirinhas -->
                    <a href="pesquisar_alunos.php" class="card-menu-admin card-carteirinhas">
                        <div class="icon-container">🪪</div>
                        <span class="title">Carteirinhas</span>
                    </a>

                    <!-- 6. Registrar Frequência -->
                    <a href="registrar_frequencia.php" class="card-menu-admin card-frequencia">
                        <div class="icon-container">📷</div>
                        <span class="title">Registrar Frequência</span>
                    </a>

                    <!-- 7. Indicadores Escolares -->
                    <a href="indicadores_escolares.php" class="card-menu-admin card-indicadores">
                        <div class="icon-container">📊</div>
                        <span class="title">Indicadores Escolares</span>
                    </a>

                    <!-- 8. Configurações -->
                    <a href="painel_admin.php?secao=configuracoes" class="card-menu-admin card-configuracoes">
                        <div class="icon-container">⚙️</div>
                        <span class="title">Configurações GPS</span>
                    </a>
                </div>

            <!-- SEÇÃO DE APROVAÇÕES -->
            <?php elseif ($secaoAtiva === 'aprovacoes'): ?>
                <h5 class="fw-bold mb-3 fs-6 text-secondary">Cadastros Pendentes de Aprovação</h5>
                <?php if (empty($usuariosPendentes)): ?>
                    <div class="alert alert-secondary text-center py-4 small rounded-3">Nenhum cadastro pendente no momento.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($usuariosPendentes as $u): ?>
                            <div class="card card-item p-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-info text-dark text-uppercase mb-1"><?= ucfirst($u['tipo']); ?></span>
                                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($u['nome']); ?></h6>
                                        <p class="small text-muted mb-1">📧 E-mail: <?= htmlspecialchars($u['email']); ?></p>
                                        <?php if ($u['tipo'] == 'aluno' && (!empty($u['curso_nome']) || !empty($u['turma_nome']))): ?>
                                            <p class="small text-secondary mb-0">🎓 Curso/Turma: <?= htmlspecialchars(($u['curso_nome'] ?? '') . ' - ' . ($u['turma_nome'] ?? '')); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <form action="painel_admin.php?secao=aprovacoes" method="POST" class="d-inline">
                                            <input type="hidden" name="acao" value="aprovar_usuario">
                                            <input type="hidden" name="id_usuario" value="<?= $u['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm px-3">✔️ Aprovar Cadastro</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <!-- SEÇÃO DE TURMAS & DISCIPLINAS -->
            <?php elseif ($secaoAtiva === 'turmas'): ?>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="card p-3 border shadow-sm h-100 rounded-3 bg-light">
                            <h5 class="fw-bold text-primary mb-3 fs-6">📚 Nova Disciplina</h5>
                            <form action="painel_admin.php?secao=turmas" method="POST">
                                <input type="hidden" name="acao" value="nova_disciplina">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Nome da Disciplina</label>
                                    <input type="text" class="form-control form-control-sm" name="nome_disciplina" required placeholder="Ex: Matemática, Física...">
                                </div>
                                <button type="submit" class="btn btn-cepdap btn-sm w-100 py-2">Cadastrar Disciplina</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card p-3 border shadow-sm h-100 rounded-3 bg-light">
                            <h5 class="fw-bold text-primary mb-3 fs-6">🎓 Nova Turma</h5>
                            <form action="painel_admin.php?secao=turmas" method="POST">
                                <input type="hidden" name="acao" value="nova_turma">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Curso</label>
                                    <select class="form-select form-select-sm" name="id_curso" required>
                                        <option value="">Selecione o curso...</option>
                                        <?php foreach ($cursos as $c): ?>
                                            <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Nome da Turma</label>
                                    <input type="text" class="form-control form-control-sm" name="nome_turma" required placeholder="Ex: 1º Ano A">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Turno</label>
                                    <select class="form-select form-select-sm" name="turno" required>
                                        <option value="">Selecione o turno...</option>
                                        <option value="Manhã">Manhã</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Noite">Noite</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-cepdap btn-sm w-100 py-2">Criar Turma</button>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- SEÇÃO VINCULAR PROFESSOR -->
            <?php elseif ($secaoAtiva === 'vincular'): ?>
                <h5 class="fw-bold mb-3 fs-6 text-secondary">Vincular Professor à Turma e Disciplina</h5>
                <div class="card p-4 border shadow-sm bg-light rounded-3">
                    <form action="painel_admin.php?secao=vincular" method="POST">
                        <input type="hidden" name="acao" value="vincular_professor">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Semestre</label>
                            <select class="form-select form-select-sm" name="semestre" required>
                                <?php foreach ($listaSemestres as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem); ?>" <?= ($sem === $semestreAtualPadrao) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Professor</label>
                            <select class="form-select form-select-sm" name="id_professor" required>
                                <option value="">Selecione o professor...</option>
                                <?php foreach ($professores as $prof): ?>
                                    <option value="<?= $prof['id']; ?>"><?= htmlspecialchars($prof['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Disciplina</label>
                            <select class="form-select form-select-sm" name="id_disciplina" required>
                                <option value="">Selecione a disciplina...</option>
                                <?php foreach ($disciplinas as $d): ?>
                                    <option value="<?= $d['id']; ?>"><?= htmlspecialchars($d['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Turma</label>
                            <select class="form-select form-select-sm" name="id_turma" required>
                                <option value="">Selecione a turma...</option>
                                <?php foreach ($turmas as $t): ?>
                                    <option value="<?= $t['id']; ?>"><?= htmlspecialchars($t['curso_nome'] . ' - ' . $t['nome'] . (isset($t['turno']) ? ' (' . $t['turno'] . ')' : '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-cepdap btn-sm w-100 py-2">🔗 Efetivar Vínculo</button>
                    </form>
                </div>

            <!-- SEÇÃO PROFESSORES VINCULADOS -->
            <?php elseif ($secaoAtiva === 'vinculos'): ?>
                <h5 class="fw-bold mb-3 fs-6 text-secondary">Professores Vinculados às Disciplinas e Turmas</h5>
                <?php if (empty($vinculosListagem)): ?>
                    <div class="alert alert-secondary text-center py-4 small rounded-3">Nenhum professor vinculado no momento.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($vinculosListagem as $v): ?>
                            <div class="card card-item p-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-dark me-1">Semestre: <?= htmlspecialchars($v['semestre'] ?? 'N/D'); ?></span>
                                        <span class="badge bg-secondary mb-1 text-uppercase">Turno: <?= htmlspecialchars($v['turno']); ?></span>
                                        <h6 class="fw-bold text-dark mb-1">👨‍🏫 <?= htmlspecialchars($v['professor_nome']); ?></h6>
                                        <p class="small text-primary fw-bold mb-1">📖 Disciplina: <?= htmlspecialchars($v['disciplina_nome']); ?></p>
                                        <p class="small text-muted mb-0">🎓 Curso / Turma: <?= htmlspecialchars($v['curso_nome'] . ' - ' . $v['turma_nome']); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <!-- SEÇÃO DE CONFIGURAÇÕES GPS -->
            <?php elseif ($secaoAtiva === 'configuracoes'): ?>
                <h5 class="fw-bold mb-3 fs-6 text-secondary">⚙️ Configurações de Geolocalização (Escola)</h5>
                <div class="card p-4 border shadow-sm bg-light rounded-3">
                    <form action="painel_admin.php?secao=configuracoes" method="POST">
                        <input type="hidden" name="acao" value="atualizar_configuracoes">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Latitude da Escola (latEscola)</label>
                            <input type="text" class="form-control form-control-sm" name="latEscola" value="<?= htmlspecialchars($latEscolaVal); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Longitude da Escola (lonEscola)</label>
                            <input type="text" class="form-control form-control-sm" name="lonEscola" value="<?= htmlspecialchars($lonEscolaVal); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Tolerância Máxima (em metros)</label>
                            <input type="number" class="form-control form-control-sm" name="toleranciaMetros" value="<?= htmlspecialchars($toleranciaVal); ?>" required>
                            <div class="form-text small text-muted">Distância máxima permitida para o aluno registrar a frequência via GPS.</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100 py-2">💾 Salvar Configurações</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>