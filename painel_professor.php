<?php
session_start();
require_once 'conexao.php';

// Verifica se é professor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'professor') {
    header("Location: index.php");
    exit;
}

$id_professor = $_SESSION['usuario_id'];

// Busca os dados do professor (nome e nome_social) diretamente da tabela usuarios
$stmtProf = $pdo->prepare("SELECT nome, nome_social FROM usuarios WHERE id = ?");
$stmtProf->execute([$id_professor]);
$dadosProf = $stmtProf->fetch(PDO::FETCH_ASSOC);

// Define a exibição priorizando o nome social, caso exista e não esteja vazio
$nome_exibicao = !empty($dadosProf['nome_social']) ? $dadosProf['nome_social'] : ($dadosProf['nome'] ?? ($_SESSION['usuario_nome'] ?? ''));

// Busca comunicados ativos e recentes (últimas 24h) para o painel do professor
$stmtAvisos = $pdo->prepare("
    SELECT c.*, u.nome as autor_nome
    FROM comunicados c
    LEFT JOIN usuarios u ON c.id_professor = u.id
    WHERE c.status = 'ativo' 
      AND (c.id_professor = ? OR c.id_turma IS NULL)
      AND c.data_postagem >= (NOW() - INTERVAL 1 DAY)
    ORDER BY c.data_postagem DESC
");
$stmtAvisos->execute([$id_professor]);
$avisosRecentes = $stmtAvisos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Painel do Professor</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            /* Esquema de cores exclusivo do Professor ( Tons de Índigo / Roxo Executivo ) */
            --cepdap-navbar: linear-gradient(135deg, #3f2b96 0%, #a8c0ff 100%);
            --header-gradient: linear-gradient(135deg, rgba(63,43,150,0.12) 0%, rgba(168,192,255,0.2) 100%);
            --header-border: #3f2b96;
            --avatar-border: #3f2b96;
        }
        body { background-color: #f4f6f9; }

        /* Layout A4 para Desktop */
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
            border-radius: 6px;
            position: relative;
            overflow: hidden;
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

        /* Cartões de menu em formato de grade (padrão igual ao do aluno) */
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
            padding: 22px 12px;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .card-menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        /* Cores temáticas individualizadas para cada card */
        .card-atribuicoes { background: #f3e8ff; border-color: #d8b4fe; color: #6b21a8; }
        .card-atribuicoes:hover { border-color: #a855f7; color: #581c87; }

        .card-cadastro { background: #e0f2fe; border-color: #bae6fd; color: #0369a1; }
        .card-cadastro:hover { border-color: #38bdf8; color: #075985; }

        .card-aviso { background: #fef3c7; border-color: #fde68a; color: #b45309; }
        .card-aviso:hover { border-color: #f59e0b; color: #92400e; }

        .card-menu-item .icon-container { font-size: 1.8rem; margin-bottom: 8px; line-height: 1; }
        .card-menu-item .title { font-size: 0.95rem; font-weight: 700; line-height: 1.2; }
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
            <a href="logout.php" class="btn btn-outline-light btn-sm text-nowrap">Sair</a>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <!-- Cabeçalho de Boas-Vindas -->
            <div class="header-professor-card p-3 mb-3 shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="badge mb-1 text-uppercase text-white" style="font-size: 0.7rem; background-color: var(--header-border);">Área Docente</span>
                    <h4 class="text-dark fw-bold fs-5 mb-0">Prof. <?= htmlspecialchars($nome_exibicao); ?>!</h4>
                </div>
            </div>

            <!-- CAIXA DE COMUNICADOS RECENTES (UMA LINHA COM OPÇÃO DE EXPANDIR) -->
            <div class="box-comunicado shadow-sm">
                <span class="icon-envelope">📨</span>
                <?php if (!empty($avisosRecentes)): ?>
                    <div class="text-start" id="container-comunicados">
                        <h6 class="fw-bold text-dark text-center mb-2">📢 Avisos & Comunicados</h6>
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

            <!-- MENU DE CARTÕES DO PROFESSOR -->
            <div class="nav-menu-cards">
                <!-- Cartão de Atribuições -->
                <a href="atribuicoes_professor.php" class="card-menu-item card-atribuicoes">
                    <div class="icon-container">📚</div>
                    <span class="title">Minhas Atribuições</span>
                </a>

                <!-- Cartão de Edição de Cadastro -->
                <a href="perfil_professor.php" class="card-menu-item card-cadastro">
                    <div class="icon-container">👤</div>
                    <span class="title">Meu Cadastro</span>
                </a>

                <!-- Cartão de Postagem de Aviso -->
                <a href="postar_aviso.php" class="card-menu-item card-aviso">
                    <div class="icon-container">📢</div>
                    <span class="title">Postar Aviso</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>