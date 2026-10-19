<?php
session_start();
require_once 'conexao.php';

$autorizado = true;
if (!isset($_SESSION['usuario_tipo'])) {$autorizado = false;
} else {
    if ($_SESSION['usuario_tipo'] != 'admin') {$autorizado = false;
    }
}

if (!$autorizado) {
    header("Location: index.php");
    exit;
}

$msg = "";
$tipo_msg = "";
$alunoRegistrado = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['acao_frequencia'])) {$dadosQr = trim($_POST['qr_data'] ?? '');$id_aluno = 0;

        if (preg_match('/ALUNO_ID:(\d+)/i', $dadosQr,$matches)) {
            $id_aluno = intval($matches[1]);
        } else {
            if (is_numeric($dadosQr)) {
                $id_aluno = intval($dadosQr);
            }
        }

        if ($id_aluno > 0) {
            $stmt =$pdo->prepare("
                SELECT u.*, c.nome as curso_nome, t.nome as turma_nome, t.turno 
                FROM usuarios u 
                LEFT JOIN cursos c ON u.id_curso = c.id
                LEFT JOIN turmas t ON u.id_turma = t.id
                WHERE u.id = ? AND u.tipo = 'aluno'
            ");
            $stmt->execute([$id_aluno]);
            $aluno =$stmt->fetch(PDO::FETCH_ASSOC);

            if ($aluno) {
                $stmtCheck =$pdo->prepare("
                    SELECT COUNT(*) FROM frequencia 
                    WHERE id_aluno = ? AND data_registro >= NOW() - INTERVAL 5 MINUTE
                ");
                $stmtCheck->execute([$id_aluno]);

                if ($stmtCheck->fetchColumn() > 0) {
                    $nomeExibir =$aluno['nome'];
                    if (!empty($aluno['nome_social'])) {
                        $nomeExibir =$aluno['nome_social'];
                    }
                    $msg = "Atenção: A presença de " . $nomeExibir . " já foi registrada recentemente!";
                    $tipo_msg = "warning";
                    $alunoRegistrado =$aluno;
                } else {
                    $stmtInsert =$pdo->prepare("INSERT INTO frequencia (id_aluno, data_registro, observacao) VALUES (?, NOW(), 'Leitura QR Code Portaria')");
                    $stmtInsert->execute([$id_aluno]);

                    $msg = "Presença registrada com sucesso!";
                    $tipo_msg = "success";
                    $alunoRegistrado =$aluno;
                }
            } else {
                $msg = "Erro: Aluno não localizado no sistema (ID: " . $id_aluno . ").";
                $tipo_msg = "danger";
            }
        } else {
            $msg = "Erro: Código QR ou ID inválido.";
            $tipo_msg = "danger";
        }

        $isAjax = false;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {$isAjax = true;
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            $sucessoJson = false;
            if ($tipo_msg == 'success') {$sucessoJson = true;
            }
            if ($tipo_msg == 'warning') {$sucessoJson = true;
            }

            $alunoArray = null;
            if ($alunoRegistrado) {
                $nomeFinal =$alunoRegistrado['nome'];
                if (!empty($alunoRegistrado['nome_social'])) {
                    $nomeFinal =$alunoRegistrado['nome_social'];
                }
                
                $cursoFinal =$alunoRegistrado['curso_nome'] ?? 'Não informado';
                $turmaNomeFinal = $alunoRegistrado['turma_nome'] ?? 'N/A';$turnoFinal = $alunoRegistrado['turno'] ?? '-';$fotoFinal = null;
                if (!empty($alunoRegistrado['foto'])) {
                    if (file_exists("uploads/" . $alunoRegistrado['foto'])) {
                        $fotoFinal = "uploads/" . $alunoRegistrado['foto'];
                    }
                }

                $alunoArray = [
                    'nome' => $nomeFinal,
                    'nome_civil' => $alunoRegistrado['nome'],
                    'curso' => $cursoFinal,
                    'turma' => $turmaNomeFinal . ' (' .$turnoFinal . ')',
                    'foto' => $fotoFinal
                ];
            }

            echo json_encode([
                'sucesso' => $sucessoJson,
                'tipo' => $tipo_msg,
                'mensagem' => $msg,
                'aluno' => $alunoArray
            ]);
            exit;
        }
    }
}

$stmtHistorico =$pdo->prepare("
    SELECT f.data_registro, u.nome, u.nome_social, c.nome as curso_nome, t.nome as turma_nome
    FROM frequencia f
    JOIN usuarios u ON f.id_aluno = u.id
    LEFT JOIN cursos c ON u.id_curso = c.id
    LEFT JOIN turmas t ON u.id_turma = t.id
    WHERE DATE(f.data_registro) = CURDATE()
    ORDER BY f.data_registro DESC
    LIMIT 10
");
$stmtHistorico->execute();
$historicoHoje =$stmtHistorico->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Controle de Frequência e Portaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
            .container { padding-left: 10px; padding-right: 10px; }
            .mobile-wrapper { background: #ffffff; padding: 10px 0; box-shadow: none; border-radius: 0; width: 100%; margin: 0; }
        }

        .logo-nav { max-width: 45px; height: auto; }
        
        #reader {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            border: 2px dashed #0056b3;
            background: #000;
        }

        .foto-aluno-presenca {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--cepdap-green);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark shadow-sm py-2" style="background-color: var(--cepdap-blue);">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center m-0" href="#">
                <img src="logo.png" alt="Logo" class="logo-nav me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5">Controle de Portaria / Presença</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="painel_admin.php" class="btn btn-outline-light btn-sm text-nowrap">Voltar ao Painel</a>
            </div>
        </div>
    </nav>

    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">

            <div class="text-center mb-4">
                <h4 class="fw-bold text-primary mb-1">Registro de Frequência do Aluno</h4>
                <p class="text-muted small">Aproxime o QR Code da carteirinha digital do aluno da câmera ou digite o código manualmente.</p>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0 p-3">
                        <h6 class="fw-bold text-dark mb-3">Leitor de QR Code (Câmera)</h6>
                        
                        <div id="reader"></div>

                        <hr class="my-3">

                        <form id="formFrequencia" action="registrar_frequencia.php" method="POST">
                            <input type="hidden" name="acao_frequencia" value="1">
                            <label class="form-label small fw-bold">Entrada Manual ou Leitor USB Barcode:</label>
                            <div class="input-group">
                                <input type="text" id="qr_data" name="qr_data" class="form-control form-control-sm" placeholder="Cole o QR Code ou digite o ID do Aluno..." autofocus required>
                                <button type="submit" class="btn btn-primary btn-sm px-3">Registrar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div id="cardResultado" class="card shadow-sm border-0 p-4 text-center h-100 d-flex flex-column justify-content-center bg-light">
                        <div id="statusPlaceholder">
                            <span class="fs-1 text-muted">🪪</span>
                            <p class="text-muted small mt-2">Aguardando leitura do QR Code...</p>
                        </div>

                        <div id="alunoCardInfo" class="d-none">
                            <div class="mb-3" id="boxFotoAluno">
                                <img id="imgAluno" src="" class="foto-aluno-presenca shadow-sm" alt="Foto Aluno">
                            </div>
                            <span id="badgeStatus" class="badge bg-success mb-2 fs-6">Presença Confirmada!</span>
                            <h4 id="nomeAlunoRes" class="fw-bold text-dark mb-1"></h4>
                            <p id="cursoAlunoRes" class="text-primary fw-bold mb-1 small"></p>
                            <p id="turmaAlunoRes" class="text-muted mb-0 small"></p>
                            <div id="alertMensagem" class="alert mt-3 py-2 small mb-0"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 p-3 mt-4">
                <h6 class="fw-bold text-secondary mb-3">Últimas Presenças Registradas Hoje</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Horário</th>
                                <th>Aluno(a)</th>
                                <th>Curso</th>
                                <th>Turma</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaHistorico">
                            <?php if (empty($historicoHoje)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Nenhum registro de frequência efetuado hoje.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historicoHoje as$h): ?>
                                    <tr>
                                        <td><span class="badge bg-dark"><?= date('H:i:s', strtotime($h['data_registro'])); ?></span></td>
                                        <td class="fw-bold"><?= htmlspecialchars(!empty($h['nome_social']) ? $h['nome_social'] :$h['nome']); ?></td>
                                        <td><?= htmlspecialchars($h['curso_nome'] ?? 'Não informado'); ?></td>
                                        <td><?= htmlspecialchars($h['turma_nome'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        function enviarPresenca(qrContent) {
            const formData = new FormData();
            formData.append('acao_frequencia', '1');
            formData.append('qr_data', qrContent);

            fetch('registrar_frequencia.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const placeholder = document.getElementById('statusPlaceholder');
                const cardInfo = document.getElementById('alunoCardInfo');
                
                placeholder.classList.add('d-none');
                cardInfo.classList.remove('d-none');

                if (data.sucesso) {
                    document.getElementById('badgeStatus').className = 'badge bg-' + data.tipo + ' mb-2 fs-6';
                    document.getElementById('badgeStatus').innerText = (data.tipo === 'success') ? 'Presença Confirmada!' : 'Atenção / Já Registrado';
                    
                    document.getElementById('nomeAlunoRes').innerText = data.aluno.nome;
                    document.getElementById('cursoAlunoRes').innerText = data.aluno.curso;
                    document.getElementById('turmaAlunoRes').innerText = 'Turma: ' + data.aluno.turma;
                    
                    if (data.aluno.foto) {
                        document.getElementById('imgAluno').src = data.aluno.foto;
                        document.getElementById('boxFotoAluno').classList.remove('d-none');
                    } else {
                        document.getElementById('boxFotoAluno').classList.add('d-none');
                    }

                    document.getElementById('alertMensagem').className = 'alert alert-' + data.tipo + ' mt-3 py-2 small mb-0';
                    document.getElementById('alertMensagem').innerText = data.mensagem;

                    const tabela = document.getElementById('tabelaHistorico');
                    const horaAtual = new Date().toLocaleTimeString('pt-BR');
                    const novaLinha = `
                        <tr class="table-success">
                            <td><span class="badge bg-dark">${horaAtual}</span></td>
                            <td class="fw-bold">${data.aluno.nome}</td>
                            <td>${data.aluno.curso}</td>
                            <td>${data.aluno.turma}</td>
                        </tr>
                    `;
                    tabela.insertAdjacentHTML('afterbegin', novaLinha);
                } else {
                    document.getElementById('badgeStatus').className = 'badge bg-danger mb-2 fs-6';
                    document.getElementById('badgeStatus').innerText = 'Erro de Validação';
                    document.getElementById('nomeAlunoRes').innerText = 'Não Identificado';
                    document.getElementById('cursoAlunoRes').innerText = '';
                    document.getElementById('turmaAlunoRes').innerText = '';
                    document.getElementById('boxFotoAluno').classList.add('d-none');

                    document.getElementById('alertMensagem').className = 'alert alert-danger mt-3 py-2 small mb-0';
                    document.getElementById('alertMensagem').innerText = data.mensagem;
                }

                document.getElementById('qr_data').value = '';
                document.getElementById('qr_data').focus();
            })
            .catch(error => console.error('Erro na requisição:', error));
        }

        document.getElementById('formFrequencia').addEventListener('submit', function(e) {
            e.preventDefault();
            const qrVal = document.getElementById('qr_data').value.trim();
            if (qrVal !== '') {
                enviarPresenca(qrVal);
            }
        });

        function onScanSuccess(decodedText, decodedResult) {
            enviarPresenca(decodedText);
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", 
            { fps: 10, qrbox: { width: 220, height: 220 } },
            false
        );
        html5QrcodeScanner.render(onScanSuccess);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>