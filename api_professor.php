<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'conexao.php';

// Recebe o ID do professor via GET ou POST (JSON)
$id_professor = isset($_GET['id_professor']) ? $_GET['id_professor'] : null;

if (!$id_professor) {
    $data = json_decode(file_get_contents("php://input"));
    $id_professor = isset($data->id_professor) ? $data->id_professor : null;
}

if (empty($id_professor)) {
    echo json_encode(["sucesso" => false, "mensagem" => "ID do professor não fornecido."]);
    exit;
}

try {
    $meusVinculos = $pdo->prepare("
        SELECT ptd.id as id_vinculo, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome 
        FROM professor_turma_disciplina ptd
        JOIN disciplinas d ON ptd.id_disciplina = d.id
        JOIN turmas t ON ptd.id_turma = t.id
        JOIN cursos c ON t.id_curso = c.id
        WHERE ptd.id_professor = ?
        ORDER BY c.nome ASC, t.nome ASC, d.nome ASC
    ");
    $meusVinculos->execute([$id_professor]);
    $turmasProfessor = $meusVinculos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "sucesso" => true,
        "dados" => $turmasProfessor
    ]);
} catch (Exception $e) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro na base de dados: " . $e->getMessage()
    ]);
}
?>