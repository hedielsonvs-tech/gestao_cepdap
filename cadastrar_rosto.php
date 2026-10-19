<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'cepdap_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$id_usuario = $_SESSION['id_usuario'] ?? 1;

// Verifica se já tem cadastro
$temCadastro = false;
$stmt = $conn->prepare("SELECT id FROM descritores_faciais WHERE id_usuario = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $temCadastro = true;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Cadastro Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .camera-box {
            position: relative;
            width: 100%;
            max-width: 320px;
            height: 240px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: #000;
        }
        video { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary mb-3">
        <div class="container d-flex justify-content-between">
            <span class="navbar-brand mb-0 h1 fs-6">Cadastro de Biometria Facial</span>
            <a href="painel_aluno.php" class="btn btn-outline-light btn-sm">Voltar</a>
        </div>
    </nav>

    <div class="container text-center px-3">
        <div class="card p-4 shadow-sm mx-auto" style="max-width: 450px;">
            <h5 class="fw-bold mb-2">Registo Facial</h5>
            <p class="text-muted small mb-3">
                <?= $temCadastro ? 'Já possui um rosto cadastrado. Se desejar atualizar, posicione-se abaixo.' : 'Posicione seu rosto no centro para realizar o primeiro cadastro.'; ?>
            </p>

            <div class="camera-box mb-3">
                <video id="videoWebcam" autoplay muted playsinline></video>
            </div>

            <div id="status" class="alert alert-info py-2 small">A carregar inteligência artificial...</div>

            <button id="btnCadastrar" class="btn btn-success btn-sm w-100 mt-2" disabled onclick="cadastrarRosto()">📷 Capturar e Salvar Rosto</button>
        </div>
    </div>

    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        const video = document.getElementById('videoWebcam');
        const statusDiv = document.getElementById('status');
        const btnCadastrar = document.getElementById('btnCadastrar');
        let ultimoDescriptorDetectado = null;

        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(carregarModelos, 500);
        });

        function carregarModelos() {
            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('models')
            ]).then(() => {
                statusDiv.innerText = "Modelos carregados! A iniciar câmara...";
                iniciarCamera();
            }).catch(err => {
                statusDiv.className = 'alert alert-danger py-2 small';
                statusDiv.innerText = 'Erro ao carregar modelos da pasta "models".';
            });
        }

        function iniciarCamera() {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false })
                .then(stream => {
                    video.srcObject = stream;
                    statusDiv.className = 'alert alert-secondary py-2 small';
                    statusDiv.innerText = 'A aguardar detecção do rosto...';
                })
                .catch(err => {
                    statusDiv.className = 'alert alert-danger py-2 small';
                    statusDiv.innerText = 'Não foi possível aceder à câmara.';
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
                    statusDiv.className = 'alert alert-warning py-2 small';
                    statusDiv.innerText = 'Rosto detetado! Pode efetuar a captura.';
                    btnCadastrar.disabled = false;
                } else {
                    btnCadastrar.disabled = true;
                    statusDiv.className = 'alert alert-secondary py-2 small';
                    statusDiv.innerText = 'Nenhum rosto detetado na câmara.';
                }
            }, 1000);
        });

        function cadastrarRosto() {
            if (!ultimoDescriptorDetectado) return;
            statusDiv.innerText = 'A guardar registo facial...';

            fetch('salvar_face.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ descriptor: ultimoDescriptorDetectado })
            })
            .then(res => res.json())
            .then(data => {
                if (data.sucesso) {
                    alert('Biometria facial cadastrada com sucesso!');
                    window.location.href = 'painel_aluno.php';
                } else {
                    alert('Erro ao salvar: ' + data.erro);
                }
            });
        }
    </script>
</body>
</html>