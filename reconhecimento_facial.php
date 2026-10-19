<?php
session_start();

// Conexão com o banco de dados corrigida para cepdap_db
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'cepdap_db';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Obter ID do usuário logado (usando fallback 1 para testes locais)
$id_usuario = $_SESSION['id_usuario'] ?? 1;

$temCadastro = false;
$descriptorSalvo = "null";

// Busca se o usuário já possui biometria cadastrada
$stmt = $conn->prepare("SELECT descriptor FROM descritores_faciais WHERE id_usuario = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $temCadastro = true;
        $descriptorSalvo = $row['descriptor']; // Array JSON [128 números]
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Frequência Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f6f9; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .camera-box {
            position: relative;
            width: 320px;
            height: 240px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: #000;
        }
        video, canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 320px;
            height: 240px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-primary mb-3">
        <div class="container d-flex justify-content-between">
            <span class="navbar-brand mb-0 h1 fs-6">Frequência por Reconhecimento Facial</span>
            <a href="painel_aluno.php" class="btn btn-outline-light btn-sm">Voltar</a>
        </div>
    </nav>

    <div class="container text-center">
        <div class="card p-3 shadow-sm mx-auto" style="max-width: 450px;">
            <h5 class="fw-bold mb-2">
                <?= $temCadastro ? 'Confirmar Presença' : 'Cadastrar Rosto (1º Acesso)'; ?>
            </h5>
            <p class="text-muted small mb-3" id="instrucao">
                <?= $temCadastro ? 'Olhe para a câmera para registrar sua presença.' : 'Posicione seu rosto no centro para cadastrar sua biometria facial.'; ?>
            </p>

            <div class="camera-box mb-3">
                <video id="videoWebcam" autoplay muted playsinline></video>
            </div>

            <div id="status" class="alert alert-info py-2 small">Carregando inteligência de visão computacional...</div>

            <?php if (!$temCadastro): ?>
                <button id="btnCadastrar" class="btn btn-success btn-sm w-100 mt-2" disabled onclick="cadastrarRosto()">📷 Capturar e Salvar Rosto</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Importação da biblioteca face-api via CDN oficial -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        const video = document.getElementById('videoWebcam');
        const statusDiv = document.getElementById('status');
        const btnCadastrar = document.getElementById('btnCadastrar');
        
        const temCadastro = <?= json_encode($temCadastro); ?>;
        const descriptorSalvo = <?= $descriptorSalvo; ?>;
        let ultimoDescriptorDetectado = null;

        // Aguarda a renderização e carregamento dos scripts
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(carregarModelos, 500);
        });

        function carregarModelos() {
            if (typeof faceapi === 'undefined') {
                statusDiv.className = 'alert alert-danger py-2 small';
                statusDiv.innerText = 'Erro: A biblioteca face-api não foi carregada.';
                return;
            }

            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('models')
            ]).then(() => {
                statusDiv.innerText = "Modelos carregados! Iniciando câmera...";
                iniciarCamera();
            }).catch(err => {
                console.error(err);
                statusDiv.className = 'alert alert-danger py-2 small';
                statusDiv.innerText = 'Erro ao carregar os modelos da pasta "models". Verifique se os arquivos JSON e shards estão na pasta.';
            });
        }

        function iniciarCamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false })
                .then(stream => {
                    video.srcObject = stream;
                    statusDiv.className = 'alert alert-secondary py-2 small';
                    statusDiv.innerText = temCadastro ? 'Analisando rosto...' : 'Aguardando posição do rosto...';
                })
                .catch(err => {
                    console.error(err);
                    statusDiv.className = 'alert alert-danger py-2 small';
                    statusDiv.innerText = 'Não foi possível acessar a câmera. Verifique as permissões do navegador.';
                });
        }

        video.addEventListener('play', () => {
            setInterval(async () => {
                if (typeof faceapi === 'undefined') return;
                
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    ultimoDescriptorDetectado = Array.from(detection.descriptor);

                    if (!temCadastro) {
                        statusDiv.className = 'alert alert-warning py-2 small';
                        statusDiv.innerText = 'Rosto detectado! Clique no botão abaixo para salvar.';
                        if (btnCadastrar) btnCadastrar.disabled = false;
                    } else {
                        const distance = faceapi.euclideanDistance(detection.descriptor, new Float32Array(descriptorSalvo));
                        
                        if (distance < 0.5) {
                            statusDiv.className = 'alert alert-success py-2 small';
                            statusDiv.innerText = '✅ Rosto reconhecido com sucesso! Presença registrada.';
                        } else {
                            statusDiv.className = 'alert alert-danger py-2 small';
                            statusDiv.innerText = '❌ Rosto não reconhecido. Tente novamente.';
                        }
                    }
                } else {
                    if (btnCadastrar) btnCadastrar.disabled = true;
                    statusDiv.className = 'alert alert-secondary py-2 small';
                    statusDiv.innerText = 'Nenhum rosto detectado na câmera.';
                }
            }, 1000);
        });

        function cadastrarRosto() {
            if (!ultimoDescriptorDetectado) return;

            statusDiv.innerText = 'Salvando cadastro facial...';

            fetch('salvar_face.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ descriptor: ultimoDescriptorDetectado })
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    alert('Biometria facial cadastrada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao salvar: ' + data.erro);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro na comunicação com o servidor.');
            });
        }
    </script>
</body>
</html>