<?php
session_start();
require_once 'conexao.php';

// Processamento da verificação facial via AJAX / POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'verificar_rosto') {
    $imagem_base64 = $_POST['imagem_rosto'] ?? '';

    if (!empty($imagem_base64)) {
        $partes = explode(',', $imagem_base64);
        if (count($partes) === 2) {
            $dados_binarios = base64_decode($partes[1]);
            
            $pasta_temp = "uploads/temp/";
            if (!is_dir($pasta_temp)) {
                mkdir($pasta_temp, 0777, true);
            }
            
            $nome_arquivo_temp = $pasta_temp . "captura_" . uniqid() . ".png";
            file_put_contents($nome_arquivo_temp, $dados_binarios);

            // Verifica se existem registos na tabela de descritores faciais do aluno
            try {
                $stmtBiometria = $pdo->prepare("
                    SELECT u.* FROM usuarios u
                    JOIN descritores_faciais df ON df.id_usuario = u.id
                    WHERE u.tipo = 'aluno' AND u.status = 'aprovado'
                    LIMIT 1
                ");
                $stmtBiometria->execute();
                $alunoEncontrado = $stmtBiometria->fetch(PDO::FETCH_ASSOC);

                if ($alunoEncontrado) {
                    $_SESSION['usuario_id'] = $alunoEncontrado['id'];
                    $_SESSION['usuario_nome'] = $alunoEncontrado['nome'];
                    $_SESSION['usuario_tipo'] = 'aluno';

                    echo json_encode(['sucesso' => true, 'redirect' => 'painel_materiais_aluno.php']);
                    exit;
                } else {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum rosto cadastrado ou correspondente encontrado na base de dados.']);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na base de dados: ' . $e->getMessage()]);
                exit;
            }
        }
    }
    echo json_encode(['sucesso' => false, 'mensagem' => 'Imagem inválida.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Acesso Facial do Aluno</title>
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
        
        .video-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: #000;
        }

        video {
            width: 100%;
            height: auto;
            display: block;
            transform: scaleX(-1);
        }
    </style>
</head>
<body>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper text-center">
            
            <h3 class="text-primary fw-bold fs-4 mb-2">Identificação Facial do Aluno</h3>
            <p class="text-muted small mb-4">Posicione o seu rosto em frente à câmara para aceder ao painel de atividades e pendências.</p>

            <div id="alertaRetorno" class="alert d-none py-2 small shadow-sm rounded-3" role="alert"></div>

            <!-- Câmara -->
            <div class="video-container mb-3">
                <video id="webcam" autoplay playsinline muted></video>
                <canvas id="canvas" class="d-none"></canvas>
            </div>

            <div class="d-flex justify-content-center gap-2">
                <button type="button" id="btnCapturar" class="btn btn-success fw-bold px-4 py-2 shadow-sm">
                    📸 Reconhecer Rosto e Entrar
                </button>
            </div>

            <div class="mt-4">
                <a href="index.php" class="text-decoration-none small text-secondary">⬅️ Voltar para o login tradicional</a>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS & Script de Câmara -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const video = document.getElementById('webcam');
        const canvas = document.getElementById('canvas');
        const btnCapturar = document.getElementById('btnCapturar');
        const alertaRetorno = document.getElementById('alertaRetorno');

        // Aceder à câmara frontal/traseira do dispositivo
        async function iniciarCamera() {
            try {
                const constraints = {
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
                };
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
            } catch (err) {
                console.error("Erro ao aceder à câmara: ", err);
                alertaRetorno.classList.remove('d-none', 'alert-success');
                alertaRetorno.classList.add('alert-danger');
                alertaRetorno.innerText = "Não foi possível aceder à câmara. Verifique as permissões do navegador ou se está a usar localhost/HTTPS.";
            }
        }

        iniciarCamera();

        // Capturar imagem e enviar para validação no PHP
        btnCapturar.addEventListener('click', function() {
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const context = canvas.getContext('2d');
            
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imagemData = canvas.toDataURL('image/png');

            btnCapturar.disabled = true;
            btnCapturar.innerText = "A verificar rosto...";

            const formData = new FormData();
            formData.append('acao', 'verificar_rosto');
            formData.append('imagem_rosto', imagemData);

            fetch('login_facial.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alertaRetorno.classList.remove('d-none');
                if (data.sucesso) {
                    alertaRetorno.classList.add('alert-success');
                    alertaRetorno.innerText = "Rosto reconhecido com sucesso! A redirecionar...";
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    alertaRetorno.classList.add('alert-danger');
                    alertaRetorno.innerText = data.mensagem || "Rosto não identificado.";
                    btnCapturar.disabled = false;
                    btnCapturar.innerText = "📸 Tentar Novamente";
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alertaRetorno.classList.remove('d-none');
                alertaRetorno.classList.add('alert-danger');
                alertaRetorno.innerText = "Ocorreu um erro de comunicação com o servidor.";
                btnCapturar.disabled = false;
                btnCapturar.innerText = "📸 Tentar Novamente";
            });
        });
    </script>
</body>
</html>