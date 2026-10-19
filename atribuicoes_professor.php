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

if (mb_strlen($nome_professor) > 18) {
    $nome_exibicao = mb_strimwidth($nome_professor, 0, 15, '...');
} else {
    $nome_exibicao = $nome_professor;
}

// Define o semestre padrão atual
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

// Consulta para buscar as turmas, disciplinas e turnos vinculados a este professor
$meusVinculos = $pdo->prepare("
    SELECT ptd.id as id_vinculo, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome, ptd.semestre 
    FROM professor_turma_disciplina ptd
    JOIN disciplinas d ON ptd.id_disciplina = d.id
    JOIN turmas t ON ptd.id_turma = t.id
    JOIN cursos c ON t.id_curso = c.id
    WHERE ptd.id_professor = ? AND ptd.semestre = ?
    ORDER BY c.nome ASC, t.nome ASC, d.nome ASC
");
$meusVinculos->execute([$id_professor, $semestreSelecionado]);
$turmasProfessor = $meusVinculos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Minhas Atribuições</title>
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

        /* Layout A4 para Desktop */
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

        /* Layout fluido para Dispositivos Móveis */
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

        .header-professor-card {
            background: var(--header-gradient);
            border-left: 5px solid var(--header-border);
            border-radius: 8px;
        }

        /* Card de Controle (Filtro e Busca) */
        .control-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        /* Grade de Cartões de Atribuição */
        .atribuicoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .card-atribuicao {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            transition: all 0.25s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }

        .card-atribuicao:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(63, 43, 150, 0.12);
            border-color: #c7d2fe;
        }

        /* Faixa Lateral Indicadora de Turno */
        .card-atribuicao::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .card-atribuicao.borda-manha::before { background-color: #f59e0b; }
        .card-atribuicao.borda-tarde::before { background-color: #ea580c; }
        .card-atribuicao.borda-noite::before { background-color: #4f46e5; }
        .card-atribuicao.borda-default::before { background-color: #64748b; }

        .card-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 8px;
        }

        .disciplina-titulo {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.3;
            margin-bottom: 0;
        }

        .curso-nome {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .turma-badge-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
        }

        .turma-nome {
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
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

        /* Botão Acessar Disciplina */
        .btn-acessar-card {
            background-color: #f8fafc;
            color: var(--primary-indigo);
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 12px;
        }

        .btn-acessar-card:hover {
            background-color: var(--primary-indigo);
            color: #ffffff;
            border-color: var(--primary-indigo);
            box-shadow: 0 3px 8px rgba(63, 43, 150, 0.25);
        }
    </style>
</head>
<body>

    <!-- Navbar Superior com Apenas o Botão Voltar -->
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
            
            <!-- Cabeçalho -->
            <div class="header-professor-card p-3 mb-3 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);">Minhas Atribuições</span>
                    <h4 class="text-dark fw-bold fs-5 mb-0">Turmas e Disciplinas Vinculadas</h4>
                </div>
                <div>
                    <span class="badge bg-secondary px-3 py-2 fw-normal">Semestre Vigente: <strong><?= htmlspecialchars($semestreSelecionado); ?></strong></span>
                </div>
            </div>

            <!-- Painel de Controle (Filtro por Semestre + Pesquisa) -->
            <div class="control-panel">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-12 col-md-4 d-flex align-items-center gap-2">
                        <label for="semestre" class="form-label fw-bold small text-secondary mb-0 text-nowrap">Semestre:</label>
                        <select name="semestre" id="semestre" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($listaSemestres as $sem): ?>
                                <option value="<?= htmlspecialchars($sem); ?>" <?= ($sem === $semestreSelecionado) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($sem); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($turmasProfessor)): ?>
                    <div class="col-12 col-md-8 ms-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-end-0">🔍</span>
                            <input type="text" id="inputPesquisa" class="form-control border-start-0 ps-0" placeholder="Pesquisar por disciplina, curso ou turma..." onkeyup="filtrarCards()">
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Exibição das Atribuições em Cards -->
            <?php if (empty($turmasProfessor)): ?>
                <div class="alert alert-secondary text-center py-4 my-3 rounded-3 shadow-sm">
                    <span class="fs-4 d-block mb-1">📂</span>
                    <span class="fw-semibold text-dark d-block">Nenhuma atribuição encontrada</span>
                    <small class="text-muted">Você não possui turmas ou disciplinas vinculadas para o semestre <?= htmlspecialchars($semestreSelecionado); ?>.</small>
                </div>
            <?php else: ?>
                <div class="atribuicoes-grid" id="gridCards">
                    <?php foreach ($turmasProfessor as $vinculo): ?>
                    <?php 
                        // Lógica de cores por turno
                        $turnoLower = mb_strtolower($vinculo['turno']);
                        $classeTurno = 'turno-default';
                        $classeBorda = 'borda-default';
                        
                        if (strpos($turnoLower, 'manhã') !== false || strpos($turnoLower, 'manha') !== false) {
                            $classeTurno = 'turno-manha';
                            $classeBorda = 'borda-manha';
                        } elseif (strpos($turnoLower, 'tarde') !== false) {
                            $classeTurno = 'turno-tarde';
                            $classeBorda = 'borda-tarde';
                        } elseif (strpos($turnoLower, 'noite') !== false) {
                            $classeTurno = 'turno-noite';
                            $classeBorda = 'borda-noite';
                        }
                    ?>
                    <div class="card-atribuicao <?= $classeBorda; ?>">
                        <div>
                            <div class="card-header-info">
                                <h5 class="disciplina-titulo"><?= htmlspecialchars($vinculo['disciplina_nome']); ?></h5>
                                <span class="badge-turno <?= $classeTurno; ?>"><?= htmlspecialchars($vinculo['turno']); ?></span>
                            </div>

                            <div class="curso-nome">
                                🎓 <span><?= htmlspecialchars($vinculo['curso_nome']); ?></span>
                            </div>
                        </div>

                        <div>
                            <div class="turma-badge-container">
                                <span class="text-muted small">Turma:</span>
                                <span class="turma-nome">🏫 <?= htmlspecialchars($vinculo['turma_nome']); ?></span>
                            </div>

                            <a href="disciplina_detalhes.php?id=<?= $vinculo['id_vinculo']; ?>" class="btn-acessar-card">
                                <span>Acessar Atividades</span> 📂
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div id="nenhumResultado" class="text-center text-muted small py-4" style="display: none;">
                    <span class="d-block mb-1 fs-5">🔍</span>
                    Nenhuma disciplina ou turma atende aos critérios da sua pesquisa.
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Script de Filtro Dinâmico nos Cards -->
    <script>
        function filtrarCards() {
            const input = document.getElementById('inputPesquisa');
            const filtro = input.value.toLowerCase();
            const grid = document.getElementById('gridCards');
            if (!grid) return;

            const cards = grid.getElementsByClassName('card-atribuicao');
            let encontrados = 0;

            for (let i = 0; i < cards.length; i++) {
                let card = cards[i];
                let textoCard = card.textContent || card.innerText;
                
                if (textoCard.toLowerCase().indexOf(filtro) > -1) {
                    card.style.display = "flex";
                    encontrados++;
                } else {
                    card.style.display = "none";
                }
            }

            const aviso = document.getElementById('nenhumResultado');
            if (encontrados === 0 && cards.length > 0) {
                aviso.style.display = "block";
            } else {
                aviso.style.display = "none";
            }
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>