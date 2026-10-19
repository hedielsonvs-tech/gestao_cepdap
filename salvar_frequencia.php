<?php
header('Content-Type: application/json');
session_start();

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'cepdap_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'erro' => 'Falha na conexão com o banco de dados.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_aluno = $data['id_usuario'] ?? null;
$latAluno = $data['latitude'] ?? null;
$lonAluno = $data['longitude'] ?? null;

if (!$id_aluno) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID do aluno não informado.']);
    exit;
}

if ($latAluno === null || $lonAluno === null) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível obter a sua localização GPS. Ative a localização no telemóvel.']);
    exit;
}

// Verifica se o aluno já registou frequência hoje (evita duplicidade no mesmo dia)
$stmtCheck = $conn->prepare("SELECT id FROM frequencia WHERE id_aluno = ? AND DATE(data_registro) = CURDATE() LIMIT 1");
$stmtCheck->bind_param("i", $id_aluno);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'A sua frequência já foi registrada hoje!']);
    $stmtCheck->close();
    $conn->close();
    exit;
}
$stmtCheck->close();

// Consulta as configurações dinâmicas da tabela configuracoes
$latEscola = -1.3566733; // Valor padrão de segurança
$lonEscola = -48.3514295; // Valor padrão de segurança
$toleranciaMetros = 1500; // Valor padrão de segurança

$resultConfig = $conn->query("SELECT chave, valor FROM configuracoes");
if ($resultConfig) {
    while ($row = $resultConfig->fetch_assoc()) {
        if ($row['chave'] === 'latEscola') {
            $latEscola = floatval($row['valor']);
        } elseif ($row['chave'] === 'lonEscola') {
            $lonEscola = floatval($row['valor']);
        } elseif ($row['chave'] === 'toleranciaMetros') {
            $toleranciaMetros = intval($row['valor']);
        }
    }
    $resultConfig->free();
}

// Função de cálculo de distância (Haversine) em metros
function calcularDistanciaMetros($lat1, $lon1, $lat2, $lon2) {
    $raioTerra = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raioTerra * $c;
}

$distancia = calcularDistanciaMetros($latEscola, $lonEscola, $latAluno, $lonAluno);

if ($distancia > $toleranciaMetros) {
    echo json_encode([
        'sucesso' => false, 
        'erro' => 'Localização inválida. Você está a ' . round($distancia) . ' metros da escola. Aproxime-se para registrar a frequência.'
    ]);
    exit;
}

// Insere a frequência caso passe em todas as validações
$stmt = $conn->prepare("INSERT INTO frequencia (id_aluno, data_registro, observacao, latitude, longitude) VALUES (?, NOW(), 'Reconhecimento Facial (GPS OK)', ?, ?)");
if ($stmt) {
    $latStr = strval($latAluno);
    $lonStr = strval($lonAluno);
    $stmt->bind_param("iss", $id_aluno, $latStr, $lonStr);
    
    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar a frequência no banco de dados.']);
    }
    $stmt->close();
} else {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro na preparação da query.']);
}

$conn->close();
?>