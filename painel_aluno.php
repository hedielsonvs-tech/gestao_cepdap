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

// Lógica de cores e imagens por curso para a Navbar Superior e Topo
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

$nomeExibicaoPainel = !empty($aluno['nome_social']) ? $aluno['nome_social'] : $aluno['nome'];
$id_turma = $aluno['id_turma'] ?? 0;

// Busca comunicados ativos da tabela 'comunicados' nas últimas 24h
$avisosRecentes = [];
if ($id_aluno > 0) {
    $stmtAvisos = $pdo->prepare("
        SELECT c.*, u.nome as professor_nome
        FROM comunicados c
        LEFT JOIN usuarios u ON c.id_professor = u.id
        WHERE c.status = 'ativo' 
          AND (c.id_turma IS NULL OR c.id_turma = ?)
          AND c.data_postagem >= (NOW() - INTERVAL 1 DAY)
        ORDER BY c.data_postagem DESC
    ");
    $stmtAvisos->execute([$id_turma]);
    $avisosRecentes = $stmtAvisos->fetchAll(PDO::FETCH_ASSOC);
}

// Conta o total de novos exercícios ou materiais postados nos últimos 3 dias para a turma do aluno
$totalNovidades = 0;
if ($id_turma > 0) {
    $stmtNovidades = $pdo->prepare("
        SELECT COUNT(*) FROM postagens_disciplina p
        JOIN professor_turma_disciplina ptd ON p.id_professor_turma_disciplina = ptd.id
        WHERE ptd.id_turma = ? AND p.data_postagem >= (NOW() - INTERVAL 3 DAY)
    ");
    $stmtNovidades->execute([$id_turma]);
    $totalNovidades = intval($stmtNovidades->fetchColumn());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Painel do Aluno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: <?= $corNavbar; ?>;
            --header-gradient: <?= $gradienteHeader; ?>;
            --header-border: <?= $corBordaHeader; ?>;
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
        
        .navbar-curso {
            background: var(--cepdap-navbar) !important;
            transition: all 0.3s ease;
        }

        .header-aluno {
            background: var(--header-gradient);
            border-left: 5px solid var(--header-border);
            border-radius: 6px;
            position: relative;
            overflow: hidden;
        }

        .header-bg-imagem {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.18;
            pointer-events: none;
            z-index: 1;
        }

        .header-aluno-content {
            position: relative;
            z-index: 2;
        }

        .foto-aluno-perfil {
            border-radius: 50%;
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid var(--avatar-border);
        }

        .box-comunicado {
            background: #f8fafd;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .box-comunicado .icon-envelope {
            font-size: 2rem;
            color: #8492a6;
            margin-bottom: 8px;
            display: block;
        }

        .comunicado-item {
            display: none;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .comunicado-item.ativo {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .texto-resumo-linha {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            max-width: 100%;
        }

        .nav-menu-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-menu-item {
            background: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 18px 12px;
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

        .card-menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .card-frequencia { background: #fffdf0; border-color: #ffecb3; color: #795548; }
        .card-frequencia:hover { border-color: #ffc107; color: #5d4037; }

        .card-mural { background: #e8f5e9; border-color: #c8e6c9; color: #2e7d32; }
        .card-mural:hover { border-color: #4caf50; color: #1b5e20; box-shadow: 0 4px 10px rgba(46,125,50,0.2); }

        .card-carteirinha { background: #ffffff; border-color: #dee2e6; color: #495057; }
        .card-carteirinha:hover { border-color: #6c757d; color: #212529; }

        .card-perfil { background: #ffffff; border-color: #dee2e6; color: #495057; }
        .card-perfil:hover { border-color: #0d6efd; color: #0d6efd; box-shadow: 0 4px 10px rgba(13,110,253,0.15); }

        .card-menu-item .icon-container { font-size: 1.8rem; margin-bottom: 8px; line-height: 1; }
        .card-menu-item .title { font-size: 0.9rem; font-weight: 700; line-height: 1.2; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark navbar-curso shadow-sm py-2">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="painel_aluno.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Painel do Aluno(a)</span>
            </a>
            <a href="logout.php" class="btn btn-outline-light btn-sm text-nowrap">Sair</a>
        </div>
    </nav>

    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <div class="header-aluno p-3 mb-3 shadow-sm">
                <?php if (file_exists($imagemReferenciaCurso)): ?>
                    <img src="<?= $imagemReferenciaCurso; ?>" alt="Marca d'água do curso" class="header-bg-imagem">
                <?php endif; ?>

                <div class="header-aluno-content d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                                <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Foto" class="foto-aluno-perfil shadow-sm">
                            <?php else: ?>
                                <div class="foto-aluno-perfil d-inline-flex align-items-center justify-content-center bg-secondary text-white fs-3 shadow-sm">
                                    👤
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);"><?= htmlspecialchars($aluno['curso_nome'] ?? 'Curso não vinculado'); ?></span>
                            <h4 class="text-dark fw-bold fs-5 mb-0">Olá, <?= htmlspecialchars($nomeExibicaoPainel); ?>!</h4>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-secondary px-3 py-2 fw-normal">Turma: <strong><?= htmlspecialchars($aluno['turma_nome'] ?? 'Não informada'); ?></strong></span>
                        <span class="badge px-3 py-2 fw-normal text-white" style="background-color: var(--header-border);">Turno: <strong><?= htmlspecialchars($aluno['turno'] ?? '-'); ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- CAIXA DE COMUNICADOS RECENTES -->
            <div class="box-comunicado shadow-sm">
                <span class="icon-envelope">📨</span>
                <?php if (!empty($avisosRecentes)): ?>
                    <div class="text-start" id="container-comunicados">
                        <h6 class="fw-bold text-dark text-center mb-2">📢 Comunicados & Avisos</h6>
                        <?php foreach ($avisosRecentes as $index => $aviso): ?>
                            <div class="comunicado-item <?= ($index === 0) ? 'ativo' : ''; ?>" onclick="alternarExpansao(this)">
                                <div class="py-1">
                                    <strong class="text-primary small d-block"><?= htmlspecialchars($aviso['titulo']); ?></strong>
                                    
                                    <div class="texto-resumo-linha text-muted small">
                                        <span><?= htmlspecialchars($aviso['descricao']); ?></span>
                                        <span class="text-primary fw-bold ms-1" style="font-size: 0.7rem;">[+ Expandir]</span>
                                    </div>

                                    <div class="texto-completo text-muted small d-none mt-1">
                                        <?= nl2br(htmlspecialchars($aviso['descricao'])); ?>
                                        <span class="text-secondary fw-bold d-block mt-1" style="font-size: 0.7rem;">[- Recolher]</span>
                                    </div>

                                    <small class="text-secondary d-block mt-1" style="font-size: 0.7rem;">
                                        📅 <?= date('d/m/Y H:i', strtotime($aviso['data_postagem'])); ?>
                                        <?= !empty($aviso['professor_nome']) ? ' - Por: ' . htmlspecialchars($aviso['professor_nome']) : ''; ?>
                                        (<?= ($index + 1) ?>/<?= count($avisosRecentes); ?>)
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <script>
                        let timerRotacao = null;

                        function iniciarRotacao() {
                            let itens = document.querySelectorAll('.comunicado-item');
                            let atual = 0;
                            if (itens.length > 1) {
                                timerRotacao = setInterval(() => {
                                    itens[atual].classList.remove('ativo');
                                    recolherItem(itens[atual]);

                                    atual = (atual + 1) % itens.length;
                                    itens[atual].classList.add('ativo');
                                }, 6000);
                            }
                        }

                        function alternarExpansao(elemento) {
                            let resumo = elemento.querySelector('.texto-resumo-linha');
                            let completo = elemento.querySelector('.texto-completo');

                            if (completo.classList.contains('d-none')) {
                                resumo.classList.add('d-none');
                                completo.classList.remove('d-none');
                                if (timerRotacao) clearInterval(timerRotacao);
                            } else {
                                recolherItem(elemento);
                                iniciarRotacao();
                            }
                        }

                        function recolherItem(elemento) {
                            let resumo = elemento.querySelector('.texto-resumo-linha');
                            let completo = elemento.querySelector('.texto-completo');
                            if (!completo.classList.contains('d-none')) {
                                resumo.classList.remove('d-none');
                                completo.classList.add('d-none');
                            }
                        }

                        document.addEventListener("DOMContentLoaded", function() {
                            iniciarRotacao();
                        });
                    </script>

                <?php else: ?>
                    <p class="text-muted mb-0 font-weight-normal small fs-6" style="color: #8492a6 !important;">
                        Nenhum comunicado ativo nas últimas 24 horas.
                    </p>
                <?php endif; ?>
            </div>

            <!-- MENU DE CARDS DO ALUNO -->
            <div class="nav-menu-cards">
                <a href="registrar_rosto.php" class="card-menu-item card-frequencia">
                    <div class="icon-container">📷</div>
                    <span class="title">Registrar Frequência</span>
                </a>

                <a href="mural_aluno.php" class="card-menu-item card-mural">
                    <?php if ($totalNovidades > 0): ?>
                        <span class="position-absolute top-0 start-0 m-2 badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            Novo! (<?= $totalNovidades; ?>)
                        </span>
                    <?php endif; ?>
                    <div class="icon-container">📚</div>
                    <span class="title">Mural e Materiais</span>
                </a>

                <a href="carteirinha.php" class="card-menu-item card-carteirinha">
                    <div class="icon-container">🪪</div>
                    <span class="title">Minha Carteirinha</span>
                </a>

                <a href="perfil_aluno.php" class="card-menu-item card-perfil">
                    <div class="icon-container">⚙️</div>
                    <span class="title">Editar Perfil</span>
                </a>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>