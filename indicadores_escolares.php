<?php
session_start();

// FORÇA O FUSO HORÁRIO DO BRASIL PARA CORRIGIR A HORA DO SERVIDOR
date_default_timezone_set('America/Sao_Paulo');

require_once 'conexao.php';

// Verifica se é administrador
if (!isset($_SESSION['usuario_tipo'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['usuario_tipo'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Data atual no formato YYYY-MM-DD
$data_hoje = date('Y-m-d');

// Descobre o turno atual com base na hora exata local
$hora_atual = (int)date('H');
$min_atual = (int)date('i');
$total_min_atual = $hora_atual * 60 + $min_atual;

// Regra estrita de horários:
if ($total_min_atual >= 420 && $total_min_atual <= 735) {
    $filtro_turno = "Manhã";
} elseif ($total_min_atual >= 780 && $total_min_atual <= 1095) {
    $filtro_turno = "Tarde";
} elseif ($total_min_atual >= 1140 && $total_min_atual <= 1335) {
    $filtro_turno = "Noite";
} else {
    if ($hora_atual < 13) {
        $filtro_turno = "Manhã";
    } elseif ($hora_atual < 19) {
        $filtro_turno = "Tarde";
    } else {
        $filtro_turno = "Noite";
    }
}

// 1. Estatísticas gerais
$totalAlunos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno' AND status = 'aprovado'")->fetchColumn();
$totalTurmas = $pdo->query("SELECT COUNT(*) FROM turmas")->fetchColumn();

// Total de presenças únicas hoje
$stmtPresencasHoje = $pdo->prepare("
    SELECT COUNT(DISTINCT f.id_aluno) 
    FROM frequencia f 
    WHERE DATE(f.data_registro) = ?
");
$stmtPresencasHoje->execute([$data_hoje]);
$totalPresencasHoje = $stmtPresencasHoje->fetchColumn();

// 2. Busca apenas as turmas do turno atual selecionado automaticamente
$stmtTurmas =$pdo->prepare("
    SELECT t.*, c.nome as curso_nome 
    FROM turmas t 
    LEFT JOIN cursos c ON t.id_curso = c.id 
    WHERE t.turno = ? 
    ORDER BY t.sala ASC, t.nome ASC
");
$stmtTurmas->execute([$filtro_turno]);
$turmas =$stmtTurmas->fetchAll(PDO::FETCH_ASSOC);

// 3. Busca alunos presentes hoje
$stmtAlunosPresentes =$pdo->prepare("
    SELECT DISTINCT u.id, u.nome, u.nome_social, u.foto, u.id_turma, f.data_registro
    FROM frequencia f
    JOIN usuarios u ON f.id_aluno = u.id
    WHERE DATE(f.data_registro) = ? AND u.tipo = 'aluno'
    ORDER BY f.data_registro DESC
");
$stmtAlunosPresentes->execute([$data_hoje]);
$presentesHoje =$stmtAlunosPresentes->fetchAll(PDO::FETCH_ASSOC);

// Indexa os alunos presentes por ID da turma
$presencaPorTurma = [];
foreach ($presentesHoje as $aluno) {$presencaPorTurma[$aluno['id_turma']][] =$aluno;
}

// Mapeamento exato das salas na planta da escola
$exibirSalasOeste = ['SALA 0 LAB - INF', 'SALA 1', 'SALA 2', 'SALA 3 - LAB ENF'];$exibirSalasLeste = ['SALA 4', 'SALA 5', 'SALA 6', 'SALA 7', 'SALA 8'];

$turmasPorSalaOeste = [];$turmasPorSalaLeste = [];

foreach ($exibirSalasOeste as$s) { $turmasPorSalaOeste[$s] = []; }
foreach ($exibirSalasLeste as$s) { $turmasPorSalaLeste[$s] = []; }

foreach ($turmas as $t) {$salaTurma = strtolower(trim($t['sala'] ?? 'sala 1'));$alocada = false;

    foreach ($exibirSalasOeste as$sOeste) {
        if (strcasecmp($sOeste, $t['sala']) === 0 || str_contains($salaTurma, strtolower($sOeste))) {$turmasPorSalaOeste[$sOeste][] =$t;
            $alocada = true;
            break;
        }
    }
    
    if (!$alocada) {
        foreach ($exibirSalasLeste as$sLeste) {
            if (strcasecmp($sLeste, $t['sala']) === 0 || str_contains($salaTurma, strtolower($sLeste))) {$turmasPorSalaLeste[$sLeste][] =$t;
                $alocada = true;
                break;
            }
        }
    }

    if (!$alocada) {
        if (str_contains($salaTurma, '7')) { $turmasPorSalaLeste['SALA 7'][] =$t; }
        elseif (str_contains($salaTurma, '8')) { $turmasPorSalaLeste['SALA 8'][] =$t; }
        elseif (str_contains($salaTurma, '6')) { $turmasPorSalaLeste['SALA 6'][] =$t; }
        elseif (str_contains($salaTurma, '5')) { $turmasPorSalaLeste['SALA 5'][] =$t; }
        elseif (str_contains($salaTurma, '4')) { $turmasPorSalaLeste['SALA 4'][] =$t; }
        elseif (str_contains($salaTurma, '2')) { $turmasPorSalaOeste['SALA 2'][] =$t; }
        elseif (str_contains($salaTurma, '3')) { $turmasPorSalaOeste['SALA 3 - LAB ENF'][] =$t; }
        else { $turmasPorSalaOeste['SALA 1'][] =$t; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Planta Baixa & Turmas (Modo Responsivo)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-light: #e2e8f0;
            --panel-bg: #ffffff;
            --border-color: #cbd5e1;
            --text-main: #0f172a;
            --accent-blue: #0284c7;
            --accent-green: #16a34a;
        }

        body { 
            background-color: var(--bg-light); 
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar-tv {
            background-color: #ffffff;
            border-bottom: 3px solid var(--accent-blue);
            padding: 10px 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .logo-img { max-width: 42px; height: auto; }

        .slide-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin: 10px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            min-height: 75vh;
        }

        .slide-item {
            display: none;
            flex-direction: column;
            flex: 1;
        }

        .slide-item.ativo {
            display: flex;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.995); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Planta Baixa Adaptável para Computador e Telemóvel */
        .planta-grid {
            display: grid;
            grid-template-columns: 1fr 130px 1fr;
            gap: 12px;
            flex: 1;
        }

        @media (max-width: 992px) {
            .planta-grid {
                grid-template-columns: 1fr;
            }
            body {
                overflow: auto;
            }
            .slide-container {
                min-height: auto;
            }
        }

        .bloco-norte-geral {
            background: #cbd5e1;
            border: 2px dashed #64748b;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
        }
        
        .ala-geral {
            background: #f8fafc;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .sala-box-geral {
            background: #ffffff;
            border: 1.5px solid #94a3b8;
            border-radius: 8px;
            padding: 10px 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .patio-geral {
            background: rgba(22, 163, 74, 0.08);
            border: 2px dashed var(--accent-green);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--accent-green);
            padding: 20px;
        }

        /* Mini-avatares */
        .mini-avatar-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        
        .mini-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--accent-green);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        /* Estilos dos Slides Individuais de Turmas */
        .avatar-aluno-presenca {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--accent-green);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .aluno-card-item {
            text-align: center;
            width: 140px;
            background: #f8fafc;
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 10px;
        }
        
        .alunos-grid-turma {
            flex: 1;
            padding: 10px 0;
            max-height: 60vh;
            overflow-y: auto;
        }

        .slide-controls {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

    <!-- Navbar Responsiva -->
    <nav class="navbar navbar-light navbar-tv shadow-sm">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <img src="logo.png" alt="Logo" class="logo-img rounded">
                <div>
                    <h5 class="m-0 fw-bold text-dark fs-6 fs-md-5">CEPDAP - Planta Baixa & Radar</h5>
                    <small class="text-secondary" style="font-size: 0.8rem;">Turno: <strong class="text-primary"><?= htmlspecialchars($filtro_turno); ?></strong> <span class="badge bg-success text-white ms-1">Auto</span></small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="text-end">
                    <span class="fw-bold text-success small d-block" id="relogio" style="font-size: 0.85rem;"><?= date('d/m/Y - H:i:s'); ?></span>
                    <a href="painel_admin.php" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 0.7rem;">Painel Admin</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Container Principal Responsivo -->
    <div class="slide-container">
        
        <!-- SLIDE 0: VISÃO GERAL DA PLANTA BAIXA DA ESCOLA -->
        <div class="slide-item ativo" id="slide-geral">
            <div class="bloco-norte-geral mb-3">
                <h6 class="fw-bold text-uppercase text-secondary m-0" style="font-size: 0.85rem;">🏛️ ENTRADA PRINCIPAL • RECEPÇÃO & SECRETARIA</h6>
            </div>

            <div class="planta-grid">
                <!-- Ala Oeste -->
                <div class="ala-geral">
                    <div class="bg-primary text-white text-center rounded py-1 fw-bold text-uppercase small">Ala Oeste</div>
                    <?php foreach ($exibirSalasOeste as$nomeSala): ?>
                        <div class="sala-box-geral">
                            <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?= $nomeSala; ?></span>
                            <?php if (!empty($turmasPorSalaOeste[$nomeSala])): ?>
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <?php foreach ($turmasPorSalaOeste[$nomeSala] as$t): ?>
                                        <div class="bg-light px-2 py-1 rounded border d-flex justify-content-between align-items-center">
                                            <span class="text-primary fw-bold small"><?= htmlspecialchars($t['nome']); ?></span>
                                            <span class="badge bg-success" style="font-size: 0.7rem;"><?= isset($presencaPorTurma[$t['id']]) ? count($presencaPorTurma[$t['id']]) : 0; ?> presentes</span>
                                        </div>
                                        <!-- Mini-avatares dos alunos presentes -->
                                        <?php if (!empty($presencaPorTurma[$t['id']])): ?>
                                            <div class="mini-avatar-container">
                                                <?php foreach ($presencaPorTurma[$t['id']] as$aluno): ?>
                                                    <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Foto" class="mini-avatar" title="<?= htmlspecialchars($aluno['nome']); ?>">
                                                    <?php else: ?>
                                                        <div class="mini-avatar bg-secondary d-flex align-items-center justify-content-center text-white fw-bold small" title="<?= htmlspecialchars($aluno['nome']); ?>">
                                                            <?= mb_strtoupper(mb_substr($aluno['nome'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted fst-italic mt-1" style="font-size: 0.75rem;">Nenhuma turma nesta sala</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pátio Central -->
                <div class="patio-geral">
                    <div class="fs-2 mb-1">🌳</div>
                    <h6 class="fw-bold m-0 text-uppercase small">Pátio Central</h6>
                    <small style="font-size: 0.75rem;">Convivência & Recreio</small>
                </div>

                <!-- Ala Leste -->
                <div class="ala-geral">
                    <div class="bg-info text-dark text-center rounded py-1 fw-bold text-uppercase small">Ala Leste</div>
                    <?php foreach ($exibirSalasLeste as$nomeSala): ?>
                        <div class="sala-box-geral">
                            <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?= $nomeSala; ?></span>
                            <?php if (!empty($turmasPorSalaLeste[$nomeSala])): ?>
                                <div class="mt-1 d-flex flex-column gap-1">
                                    <?php foreach ($turmasPorSalaLeste[$nomeSala] as$t): ?>
                                        <div class="bg-light px-2 py-1 rounded border d-flex justify-content-between align-items-center">
                                            <span class="text-primary fw-bold small"><?= htmlspecialchars($t['nome']); ?></span>
                                            <span class="badge bg-success" style="font-size: 0.7rem;"><?= isset($presencaPorTurma[$t['id']]) ? count($presencaPorTurma[$t['id']]) : 0; ?> presentes</span>
                                        </div>
                                        <!-- Mini-avatares dos alunos presentes -->
                                        <?php if (!empty($presencaPorTurma[$t['id']])): ?>
                                            <div class="mini-avatar-container">
                                                <?php foreach ($presencaPorTurma[$t['id']] as$aluno): ?>
                                                    <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                                                        <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Foto" class="mini-avatar" title="<?= htmlspecialchars($aluno['nome']); ?>">
                                                    <?php else: ?>
                                                        <div class="mini-avatar bg-secondary d-flex align-items-center justify-content-center text-white fw-bold small" title="<?= htmlspecialchars($aluno['nome']); ?>">
                                                            <?= mb_strtoupper(mb_substr($aluno['nome'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted fst-italic mt-1" style="font-size: 0.75rem;">Nenhuma turma nesta sala</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SLIDES SEGUINTES: TURMA POR TURMA -->
        <?php if (!empty($turmas)): ?>
            <?php foreach ($turmas as $index =>$t): ?>
                <?php 
                    $qtdPresentes = isset($presencaPorTurma[$t['id']]) ? count($presencaPorTurma[$t['id']]) : 0;
                    $salaTurma = !empty($t['sala']) ?$t['sala'] : 'Sala Não Definida';
                ?>
                <div class="slide-item" id="slide-turma-<?= $index; ?>">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-3 mb-2 gap-2">
                        <div>
                            <span class="badge bg-primary px-2 py-1 mb-1 small">🚪 Local: <?= htmlspecialchars($salaTurma); ?></span>
                            <h4 class="fw-bold text-dark m-0 fs-5">🎓 <?= htmlspecialchars($t['nome']); ?> <span class="text-muted fs-6 fw-normal">(<?= htmlspecialchars($t['turno']); ?>)</span></h4>
                        </div>
                        <div>
                            <span class="badge bg-success px-3 py-2 shadow-sm fs-6"><?= $qtdPresentes; ?> Alunos Presentes</span>
                        </div>
                    </div>

                    <div class="alunos-grid-turma">
                        <?php if (!empty($presencaPorTurma[$t['id']])): ?>
                            <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start align-items-start pt-2">
                                <?php foreach ($presencaPorTurma[$t['id']] as$aluno): ?>
                                    <?php 
                                        $nomeExibicao = !empty($aluno['nome_social']) ?$aluno['nome_social'] : $aluno['nome'];$primeiroNome = explode(' ', trim($nomeExibicao))[0];$sobrenome = isset(explode(' ', trim($nomeExibicao))[1]) ? explode(' ', trim($nomeExibicao))[1] : '';
                                    ?>
                                    <div class="aluno-card-item shadow-sm">
                                        <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Avatar" class="avatar-aluno-presenca mx-auto d-block">
                                        <?php else: ?>
                                            <div class="avatar-aluno-presenca bg-secondary d-flex align-items-center justify-content-center text-white fw-bold mx-auto fs-3">
                                                <?= mb_strtoupper(mb_substr($aluno['nome'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="d-block text-truncate fw-bold text-dark mt-2 small" title="<?= htmlspecialchars($nomeExibicao); ?>">
                                            <?= htmlspecialchars($primeiroNome . ' ' .$sobrenome); ?>
                                        </span>
                                        <span class="badge bg-success text-white fw-bold mt-1" style="font-size: 0.7rem;">
                                            🕒 <?= date('H:i', strtotime($aluno['data_registro'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
                                <div class="display-4 text-muted opacity-50 mb-2">📭</div>
                                <h6 class="text-muted fst-italic text-center">Nenhum aluno desta turma registrou presença hoje.</h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Controles Manuais na Tela -->
        <div class="slide-controls">
            <button class="btn btn-outline-secondary btn-sm px-3" onclick="mudarSlide(-1)">⬅️ Anterior</button>
            <button class="btn btn-primary btn-sm px-3" id="btnPausa" onclick="alternarPausa()">⏸️ Pausar</button>
            <button class="btn btn-outline-secondary btn-sm px-3" onclick="mudarSlide(1)">Próxima ➡️</button>
        </div>
    </div>

    <!-- Script de Relógio e Carrossel Híbrido -->
    <script>
        setInterval(() => {
            const agora = new Date();
            const dataStr = agora.toLocaleDateString('pt-BR');
            const horaStr = agora.toLocaleTimeString('pt-BR');
            document.getElementById('relogio').innerText = `${dataStr} - ${horaStr}`;
        }, 1000);

        const totalSlides = 1 + <?= count($turmas); ?>;
        let slideAtual = 0;
        let pausado = false;
        let intervaloCarrossel;

        function mostrarSlide(index) {
            const slides = document.querySelectorAll('.slide-item');
            slides.forEach(s => s.classList.remove('ativo'));
            
            slideAtual = (index + totalSlides) % totalSlides;
            document.getElementById(slideAtual === 0 ? 'slide-geral' : `slide-turma-${slideAtual - 1}`).classList.add('ativo');
        }

        function mudarSlide(direcao) {
            mostrarSlide(slideAtual + direcao);
            reiniciarTemporizador();
        }

        function alternarPausa() {
            pausado = !pausado;
            const btn = document.getElementById('btnPausa');
            if (pausado) {
                btn.innerHTML = "▶️ Retomar";
                btn.className = "btn btn-success btn-sm px-3";
                clearInterval(intervaloCarrossel);
            } else {
                btn.innerHTML = "⏸️ Pausar";
                btn.className = "btn btn-primary btn-sm px-3";
                iniciarCarrossel();
            }
        }

        function iniciarCarrossel() {
            if (totalSlides <= 1) return;
            intervaloCarrossel = setInterval(() => {
                if (!pausado) {
                    mostrarSlide(slideAtual + 1);
                }
            }, 12000); // 12 segundos por slide
        }

        function reiniciarTemporizador() {
            clearInterval(intervaloCarrossel);
            if (!pausado) {
                iniciarCarrossel();
            }
        }

        iniciarCarrossel();

        // Recarrega a página inteira a cada 5 minutos para atualizar dados
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>