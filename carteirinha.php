<?php
session_start();
require_once 'conexao.php';

// Verifica quem está acessando e define o ID do aluno correspondente
if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'admin' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_aluno = intval($_GET['id']);
    $urlVoltar = "pesquisar_alunos.php";
} elseif (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'aluno') {
    $id_aluno = $_SESSION['usuario_id'];
    $urlVoltar = "painel_aluno.php";
} else {
    header("Location: index.php");
    exit;
}

// Busca informações completas do aluno (incluindo nome_social, curso e cpf)
$stmtAluno = $pdo->prepare("
    SELECT u.*, t.nome as turma_nome, t.turno, c.nome as curso_nome 
    FROM usuarios u
    LEFT JOIN turmas t ON u.id_turma = t.id
    LEFT JOIN cursos c ON u.id_curso = c.id
    WHERE u.id = ?
");
$stmtAluno->execute([$id_aluno]);
$aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

if (!$aluno) {
    echo "Aluno não encontrado.";
    exit;
}

// Lógica de nomes: Frente exibe Nome Social (se houver) ou Nome Civil. Verso exibe sempre o Nome Civil.
$nomeFrente = !empty($aluno['nome_social']) ? $aluno['nome_social'] : $aluno['nome'];
$nomeVerso = $aluno['nome'];
$cpfAluno = $aluno['cpf'] ?? 'Não informado';
$nomeCursoVerso = $aluno['curso_nome'] ?? 'Curso não vinculado';

// Identificação do curso e definição das referências solicitadas
$cursoNomeLower = mb_strtolower(trim($aluno['curso_nome'] ?? ''));

if (strpos($cursoNomeLower, 'informatica') !== false || strpos($cursoNomeLower, 'informática') !== false) {
    // INFORMÁTICA: Gradiente Verde + Imagem de Referência 2
    $corNavbar = 'linear-gradient(135deg, #1e7e34 0%, #28a745 100%)';
    $gradienteFrente = 'linear-gradient(135deg, #1e7e34 0%, #28a745 50%, #20c997 100%)';
    $corBarraVerso = '#28a745';
    $corIconeVerso = '#1e7e34';
    $corTextoFrente = '#ffffff';
    $classeCurso = 'curso-informatica';
    $imagemReferenciaCurso = 'informatica.png';
} elseif (strpos($cursoNomeLower, 'administracao') !== false || strpos($cursoNomeLower, 'administração') !== false) {
    // ADMINISTRAÇÃO: Gradiente Azul + Imagem de Referência 3
    $corNavbar = 'linear-gradient(135deg, #003366 0%, #0056b3 100%)';
    $gradienteFrente = 'linear-gradient(135deg, #003366 0%, #0056b3 50%, #17a2b8 100%)';
    $corBarraVerso = '#0056b3';
    $corIconeVerso = '#003366';
    $corTextoFrente = '#ffffff';
    $classeCurso = 'curso-administracao';
    $imagemReferenciaCurso = 'administracao.png';
} else {
    // ENFERMAGEM / PADRÃO: Gradiente Branco/Prata Limpo + Imagem de Referência 1
    $corNavbar = 'linear-gradient(135deg, #495057 0%, #6c757d 100%)';
    $gradienteFrente = 'linear-gradient(135deg, #e9ecef 0%, #f8f9fa 60%, #ffffff 100%)';
    $corBarraVerso = '#6c757d';
    $corIconeVerso = '#495057';
    $corTextoFrente = '#212529';
    $classeCurso = 'curso-enfermagem';
    $imagemReferenciaCurso = 'enfermagem.png';
}

// Dados para o QR Code de presença
$dadoQrCode = "ALUNO_ID:" . $aluno['id'] . " | NOME:" . $nomeFrente . " | CPF:" . $cpfAluno . " | TURMA:" . ($aluno['turma_nome'] ?? 'N/A') . " | CURSO:" . $nomeCursoVerso;
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($dadoQrCode);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Carteirinha Digital</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: <?= $corNavbar; ?>;
            --gradiente-curso: <?= $gradienteFrente; ?>;
            --cor-barra: <?= $corBarraVerso; ?>;
            --cor-icone: <?= $corIconeVerso; ?>;
            --cor-texto: <?= $corTextoFrente; ?>;
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

        .logo-nav { max-width: 45px; height: auto; }

        /* Navbar Dinâmica de acordo com o Curso */
        .navbar-curso {
            background: var(--cepdap-navbar) !important;
            transition: all 0.3s ease;
        }

        /* Container que agrupa o Frente e Verso */
        .carteirinha-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 25px;
        }

        /* Estrutura Base das Carteirinhas (Proporção Vertical) */
        .carteirinha-card {
            width: 100%;
            max-width: 280px;
            height: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Ajuste específico para Enfermagem */
        .carteirinha-card.curso-enfermagem .frente-dados-box {
            border-top: 3px solid #dee2e6;
        }
        .carteirinha-card.curso-enfermagem .frente-info p {
            color: #495057 !important;
            font-weight: 600;
        }

        /* ---------------- FRENTE DO CARTÃO ---------------- */
        .frente-foto-box {
            width: 100%;
            height: 270px;
            background-color: #e9ecef;
            overflow: hidden;
            position: relative;
        }

        .frente-foto-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .frente-dados-box {
            background: var(--gradiente-curso);
            color: var(--cor-texto);
            padding: 15px;
            height: 170px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            border-top: 3px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .frente-bg-imagem {
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

        .frente-info {
            z-index: 2;
        }

        .frente-info h5 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.2;
            text-transform: capitalize;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .frente-info p {
            font-size: 0.75rem;
            opacity: 0.95;
            margin-bottom: 0;
            text-shadow: 0 1px 1px rgba(0,0,0,0.15);
        }

        .frente-logo-box {
            z-index: 2;
        }

        .frente-logo-box img {
            max-width: 50px;
            height: auto;
            background: white;
            padding: 4px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        /* ---------------- VERSO DO CARTÃO ---------------- */
        .verso-container {
            display: flex;
            height: 100%;
            width: 100%;
        }

        .verso-sidebar {
            width: 35px;
            background-color: var(--cor-barra);
            height: 100%;
        }

        .verso-content {
            flex: 1;
            padding: 10px 14px 8px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
        }

        /* Recuo superior mantido para a perfuração do crachá */
        .verso-dados-aluno {
            margin-top: 35px; 
        }

        /* Itens compactos para caber perfeitamente com o curso */
        .verso-item {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .verso-badge-icon {
            width: 18px;
            height: 18px;
            background-color: var(--cor-icone);
            color: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.55rem;
            flex-shrink: 0;
        }

        .verso-text {
            line-height: 1.0;
            overflow: hidden;
        }

        .verso-text label {
            font-size: 0.45rem;
            color: #6c757d;
            display: block;
            margin-bottom: 1px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .verso-text span {
            font-size: 0.6rem;
            font-weight: 700;
            color: #212529;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }

        .verso-qrcode-box {
            text-align: center;
            padding: 10px 2px;
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: 6px;
            margin-top: 13px;
            margin-bottom: 3px;
        }

        /* Informações da Escola no Rodapé */
        .verso-escola-info {
            text-align: center;
            border-top: 1px solid #dee2e6;
            padding-top: 3px;
            margin-top: auto;
        }

        .verso-escola-info h6 {
            font-size: 0.44rem;
            font-weight: 700;
            color: #222222;
            margin-bottom: 1px;
            line-height: 1.0;
        }

        .verso-escola-info p {
            font-size: 0.4rem;
            color: #555555;
            margin-bottom: 0;
            line-height: 1.0;
        }

        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .a4-desktop { box-shadow: none; margin: 0; padding: 0; width: 100%; }
            .carteirinha-wrapper { gap: 20px; }
            .carteirinha-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <!-- Navbar Superior Dinâmica conforme o Curso -->
    <nav class="navbar navbar-dark navbar-curso shadow-sm py-2 no-print">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="#">
                <img src="logo.png" alt="Logo" class="logo-nav me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5 fw-bold">Carteirinha Digital</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= $urlVoltar; ?>" class="btn btn-outline-light btn-sm text-nowrap">← Voltar</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper text-center">
            
            <div class="carteirinha-wrapper">
                
                <!-- ================= FRENTE DA CARTEIRINHA ================= -->
                <div class="carteirinha-card <?= $classeCurso; ?>">
                    <!-- Foto em Destaque -->
                    <div class="frente-foto-box">
                        <?php if (!empty($aluno['foto']) && file_exists("uploads/" . $aluno['foto'])): ?>
                            <img src="uploads/<?= htmlspecialchars($aluno['foto']); ?>" alt="Foto Aluno">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-secondary text-white fs-1">
                                👤
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rodapé com Gradiente, Cores do Curso e Imagem de Referência em Fundo -->
                    <div class="frente-dados-box">
                        <?php if (file_exists($imagemReferenciaCurso)): ?>
                            <img src="<?= $imagemReferenciaCurso; ?>" alt="Referência Curso" class="frente-bg-imagem">
                        <?php endif; ?>

                        <div class="frente-info text-start">
                            <h5><?= htmlspecialchars($nomeFrente); ?></h5>
                            <p><?= htmlspecialchars($aluno['curso_nome'] ?? 'Curso não vinculado'); ?></p>
                        </div>
                        <div class="frente-logo-box">
                            <img src="logo.png" alt="Logo CEPDAP">
                        </div>
                    </div>
                </div>

                <!-- ================= VERSO DO CARTÃO ================= -->
                <div class="carteirinha-card <?= $classeCurso; ?>">
                    <div class="verso-container">
                        <!-- Barra Lateral com a cor exata do curso -->
                        <div class="verso-sidebar"></div>

                        <!-- Conteúdo com Informações e QR Code -->
                        <div class="verso-content text-start">
                            <!-- Bloco de Informações com Recuo Superior para Perfuração -->
                            <div class="verso-dados-aluno">
                                <!-- Item: Nome Civil -->
                                <div class="verso-item">
                                    <div class="verso-badge-icon">👤</div>
                                    <div class="verso-text">
                                        <label>Nome Civil (Completo)</label>
                                        <span><?= htmlspecialchars($nomeVerso); ?></span>
                                    </div>
                                </div>

                                <!-- Item: CPF -->
                                <div class="verso-item">
                                    <div class="verso-badge-icon">📄</div>
                                    <div class="verso-text">
                                        <label>CPF</label>
                                        <span><?= htmlspecialchars($cpfAluno); ?></span>
                                    </div>
                                </div>
								
								<!-- Item: Nome do Curso -->
                                <div class="verso-item">
                                    <div class="verso-badge-icon">🎓</div>
                                    <div class="verso-text">
                                        <label>Curso</label>
                                        <span><?= htmlspecialchars($nomeCursoVerso); ?></span>
                                    </div>
                                </div>

                                <!-- Item: Turma e Turno -->
                                <div class="verso-item">
                                    <div class="verso-badge-icon">🏫</div>
                                    <div class="verso-text">
                                        <label>Turma / Turno</label>
                                        <span><?= htmlspecialchars($aluno['turma_nome'] ?? 'N/A'); ?> (<?= htmlspecialchars($aluno['turno'] ?? '-'); ?>)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Área do QR Code de Presença -->
                            <div class="verso-qrcode-box">
                                <img src="<?= $qrCodeUrl; ?>" alt="QR Code" class="img-fluid" style="max-width: 85px; height: 85px;">
                                <small class="d-block text-muted mt-1" style="font-size: 0.45rem;">Frequência / Acesso</small>
                            </div>

                            <!-- Informações da Escola no Rodapé -->
                            <div class="verso-escola-info">
                                <h6>ESCOLA ESTADUAL CENTRO DE EDUCACAO PROFISSIONAL DOM ARISTIDES PIROVANO</h6>
                                <p>R. Assis Dória, 1067 - Pedreirinha, Marituba - PA, 67105-290</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Rodapé de Ações Inferior (Oculto na Impressão) -->
            <div class="d-flex justify-content-center gap-2 no-print mt-4">
                <button onclick="window.print()" class="btn btn-success btn-sm px-4">📥 Salvar em PDF</button>
                <a href="<?= $urlVoltar; ?>" class="btn btn-outline-secondary btn-sm px-4">Voltar</a>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>