<?php
// Inicialização obrigatória de variáveis no topo absoluto para evitar qualquer aviso
$temCadastro = false;
$descriptorAlunoLogado = null;
$id_aluno_logado = null;
$cursoNome = '';

session_start();

$host = 'localhost';$user = 'root';
$pass = '';$db   = 'cepdap_db';

$conn = new mysqli($host,$user, $pass,$db);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Verifica se o aluno está logado
if (!isset($_SESSION['usuario_id']) OR$_SESSION['usuario_tipo'] != 'aluno') {
    header("Location: index.php");
    exit;
}

$id_aluno_logado =$_SESSION['usuario_id'];

// Busca o curso do aluno para definir a cor da navbar
$stmtCurso =$conn->prepare("
    SELECT c.nome as curso_nome 
    FROM usuarios u
    LEFT JOIN cursos c ON u.id_curso = c.id
    WHERE u.id = ? LIMIT 1
");
$stmtCurso->bind_param("i", $id_aluno_logado);$stmtCurso->execute();
$resCurso =$stmtCurso->get_result();

if ($rowCurso =$resCurso->fetch_assoc()) {
    if (isset($rowCurso['curso_nome'])) {
        $cursoNome = mb_strtolower(trim($rowCurso['curso_nome']));
    }
}
$stmtCurso->close();

// Lógica de cores por curso idêntica ao painel
$corNavbar = 'linear-gradient(135deg, #495057 0%, #6c757d 100%)';

if (strpos($cursoNome, 'informatica') !== false OR strpos($cursoNome, 'informática') !== false) {
    $corNavbar = 'linear-gradient(135deg, #1e7e34 0%, #28a745 100%)';
} elseif (strpos($cursoNome, 'administracao') !== false OR strpos($cursoNome, 'administração') !== false) {
    $corNavbar = 'linear-gradient(135deg, #003366 0%, #0056b3 100%)';
}

$stmt =$conn->prepare("SELECT descriptor FROM descritores_faciais WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param("i", $id_aluno_logado);$stmt->execute();
$result =$stmt->get_result();

if ($row = $result->fetch_assoc()) {$temCadastro = true;
    $descriptorAlunoLogado = json_decode($row['descriptor']);
}
$stmt->close();$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Registo de Frequência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-navbar: <?php echo $corNavbar; ?>;
        }
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .navbar-curso {
            background: var(--cepdap-navbar) !important;
            transition: all 0.3s ease;
        }

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
    <nav class="navbar navbar-dark navbar-curso mb-3 shadow-sm">
        <div class="container d-flex justify-content-between">
            <span class="navbar-brand mb-0 h1 fs-6">Validação e Registo de Frequência</span>
            <a href="painel_aluno.php" class="btn btn-outline-light btn-sm">Voltar</a>
        </div>
    </nav>

    <div class="container text-center px-3">
        <div class="card p-4 shadow-sm mx-auto" style="max-width: 450px;">
            <h5 class="fw-bold mb-2">Chamada por Reconhecimento</h5>

            <?php if ($temCadastro == false): ?>
                <div class="alert alert-warning py-3 small mb-3">
                    ⚠️ <strong>Atenção:</strong> Não existe nenhum rosto cadastrado para este aluno.<br>
                    Por favor, faça o primeiro cadastro antes de registar frequência.
                </div>
                <a href="cadastrar_rosto.php" class="btn btn-primary btn-sm w-100">Ir para o Cadastro Facial</a>
            <?php else: ?>
                <p class="text-muted small mb-3">Olhe fixamente para a câmara para validar a sua presença.</p>

                <div class="camera-box mb-3">
                    <video id="videoWebcam" autoplay muted playsinline></video>
                </div>

                <div id="status" class="alert alert-info py-2 small">A obter localização GPS e a carregar inteligência...</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($temCadastro == true): ?>
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        const video = document.getElementById('videoWebcam');
        const statusDiv = document.getElementById('status');
        const descriptorSalvo = <?php echo json_encode($descriptorAlunoLogado); ?>;
        const idAlunoLogado = <?php echo $id_aluno_logado; ?>;
        let frequenciaRegistrada = false;
        
        let latitudeAtual = null;
        let longitudeAtual = null;

        window.addEventListener('DOMContentLoaded', () => {
            obterLocalizacao();
        });

        function obterLocalizacao() {
            if ("geolocation" in navigator) {
                statusDiv.innerText = "A procurar sinal de GPS no telemóvel...";
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        latitudeAtual = position.coords.latitude;
                        longitudeAtual = position.coords.longitude;
                        statusDiv.innerText = "GPS obtido com sucesso! A carregar câmara...";
                        setTimeout(carregarModelos, 500);
                    },
                    (error) => {
                        console.warn("Aviso de GPS: " + error.message);
                        statusDiv.className = 'alert alert-warning py-2 small';
                        statusDiv.innerText = "Não foi possível obter o GPS. A prosseguir mesmo assim...";
                        setTimeout(carregarModelos, 1000);
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            } else {
                setTimeout(carregarModelos, 500);
            }
        }

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
                    statusDiv.innerText = 'A procurar o seu rosto na câmara...';
                })
                .catch(err => {
                    statusDiv.className = 'alert alert-danger py-2 small';
                    statusDiv.innerText = 'Não foi possível aceder à câmara.';
                });
        }

        video.addEventListener('play', () => {
            const intervalo = setInterval(async () => {
                if (frequenciaRegistrada || typeof faceapi === 'undefined') return;

                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    const distance = faceapi.euclideanDistance(detection.descriptor, new Float32Array(descriptorSalvo));

                    if (distance < 0.6) {
                        frequenciaRegistrada = true;
                        clearInterval(intervalo);
                        statusDiv.className = 'alert alert-success py-2 small';
                        statusDiv.innerText = `✅ Rosto reconhecido! A validar geolocalização...`;

                        fetch('salvar_frequencia.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                id_usuario: idAlunoLogado,
                                latitude: latitudeAtual,
                                longitude: longitudeAtual
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.sucesso) {
                                alert('Presença registrada com sucesso!');
                            } else {
                                alert('Aviso: ' + data.erro);
                            }
                            window.location.href = 'painel_aluno.php';
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Erro na comunicação com o servidor.');
                            window.location.href = 'painel_aluno.php';
                        });
                    } else {
                        statusDiv.className = 'alert alert-warning py-2 small';
                        statusDiv.innerText = '❌ O rosto na câmara não corresponde ao aluno logado.';
                    }
                } else {
                    statusDiv.className = 'alert alert-secondary py-2 small';
                    statusDiv.innerText = 'Nenhum rosto detetado.';
                }
            }, 1500);
        });
    </script>
    <?php endif; ?>
</body>
</html>