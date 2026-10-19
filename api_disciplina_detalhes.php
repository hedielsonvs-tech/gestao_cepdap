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

$inputJSON = json_decode(file_get_contents('php://input'), true);

if ($inputJSON) {
    $acao = $inputJSON['acao'] ?? 'listar';
    $id_vinculo = intval($inputJSON['id_vinculo'] ?? 0);
} else {
    $acao = isset($_GET['acao']) ? $_GET['acao'] : (isset($_POST['acao']) ? $_POST['acao'] : 'listar');
    $id_vinculo = intval(isset($_GET['id_vinculo']) ? $_GET['id_vinculo'] : (isset($_POST['id_vinculo']) ? $_POST['id_vinculo'] : 0));
}

if ($id_vinculo <= 0) {
    echo json_encode(["sucesso" => false, "mensagem" => "ID do vínculo inválido."]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT ptd.*, d.nome as disciplina_nome, t.nome as turma_nome, t.turno, c.nome as curso_nome 
        FROM professor_turma_disciplina ptd
        JOIN disciplinas d ON ptd.id_disciplina = d.id
        JOIN turmas t ON ptd.id_turma = t.id
        JOIN cursos c ON t.id_curso = c.id
        WHERE ptd.id = ?
    ");
    $stmt->execute([$id_vinculo]);
    $vinculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vinculo) {
        echo json_encode(["sucesso" => false, "mensagem" => "Vínculo não encontrado."]);
        exit;
    }

    if ($acao == 'listar') {
        $postagensStmt = $pdo->prepare("SELECT * FROM postagens_disciplina WHERE id_professor_turma_disciplina = ? ORDER BY data_postagem DESC");
        $postagensStmt->execute([$id_vinculo]);
        $postagens = $postagensStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "sucesso" => true,
            "vinculo" => $vinculo,
            "postagens" => $postagens
        ]);
        exit;
    }

    if ($acao == 'salvar') {
        $id_post = 0;
        $tipo = '';
        $titulo = '';
        $descricao = '';
        $nome_arquivo = null;
        $pasta_upload = "uploads/";

        if (!is_dir($pasta_upload)) {
            mkdir($pasta_upload, 0777, true);
        }

        if ($inputJSON) {
            $id_post = intval($inputJSON['id_post'] ?? 0);
            $tipo = trim($inputJSON['tipo'] ?? '');
            $titulo = trim($inputJSON['titulo'] ?? '');
            $descricao = trim($inputJSON['descricao'] ?? '');
            $arquivo_base64 = $inputJSON['arquivo_base64'] ?? null;
            $nome_original = $inputJSON['nome_arquivo'] ?? null;

            if (!empty($arquivo_base64) && !empty($nome_original)) {
                $extensao = pathinfo($nome_original, PATHINFO_EXTENSION);
                $nome_gerado = uniqid() . "." . ($extensao ? $extensao : 'pdf');
                $binario = base64_decode($arquivo_base64);
                
                if (file_put_contents($pasta_upload . $nome_gerado, $binario)) {
                    $nome_arquivo = $nome_gerado; // Atribui corretamente para salvar na BD
                }
            }
        } else {
            $id_post = intval($_POST['id_post'] ?? 0);
            $tipo = trim($_POST['tipo'] ?? '');
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');

            if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
                $extensao = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
                $nome_gerado = uniqid() . "." . $extensao;
                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $pasta_upload . $nome_gerado)) {
                    $nome_arquivo = $nome_gerado;
                }
            }
        }

        if (empty($tipo) || empty($titulo)) {
            echo json_encode(["sucesso" => false, "mensagem" => "Preencha o tipo e o título."]);
            exit;
        }

        if ($id_post > 0) {
            if ($nome_arquivo) {
                $stmtAntigo = $pdo->prepare("SELECT arquivo FROM postagens_disciplina WHERE id = ?");
                $stmtAntigo->execute([$id_post]);
                $ant = $stmtAntigo->fetch(PDO::FETCH_ASSOC);
                if (!empty($ant['arquivo']) && file_exists($pasta_upload . $ant['arquivo'])) {
                    unlink($pasta_upload . $ant['arquivo']);
                }

                $update = $pdo->prepare("UPDATE postagens_disciplina SET tipo = ?, titulo = ?, descricao = ?, arquivo = ? WHERE id = ? AND id_professor_turma_disciplina = ?");
                $update->execute([$tipo, $titulo, $descricao, $nome_arquivo, $id_post, $id_vinculo]);
            } else {
                $update = $pdo->prepare("UPDATE postagens_disciplina SET tipo = ?, titulo = ?, descricao = ? WHERE id = ? AND id_professor_turma_disciplina = ?");
                $update->execute([$tipo, $titulo, $descricao, $id_post, $id_vinculo]);
            }
            echo json_encode(["sucesso" => true, "mensagem" => "Publicação atualizada com sucesso!"]);
        } else {
            $insert = $pdo->prepare("INSERT INTO postagens_disciplina (id_professor_turma_disciplina, tipo, titulo, descricao, arquivo) VALUES (?, ?, ?, ?, ?)");
            if ($insert->execute([$id_vinculo, $tipo, $titulo, $descricao, $nome_arquivo])) {
                echo json_encode(["sucesso" => true, "mensagem" => "Publicação realizada com sucesso!"]);
            } else {
                echo json_encode(["sucesso" => false, "mensagem" => "Erro ao inserir postagem."]);
            }
        }
        exit;
    }

    if ($acao == 'excluir') {
        $id_post = intval(isset($_GET['id_post']) ? $_GET['id_post'] : ($inputJSON['id_post'] ?? 0));
        $stmtCheck = $pdo->prepare("SELECT * FROM postagens_disciplina WHERE id = ? AND id_professor_turma_disciplina = ?");
        $stmtCheck->execute([$id_post, $id_vinculo]);
        $postagem_atual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($postagem_atual) {
            if (!empty($postagem_atual['arquivo']) && file_exists("uploads/" . $postagem_atual['arquivo'])) {
                unlink("uploads/" . $postagem_atual['arquivo']);
            }
            $del = $pdo->prepare("DELETE FROM postagens_disciplina WHERE id = ?");
            $del->execute([$id_post]);

            echo json_encode(["sucesso" => true, "mensagem" => "Publicação excluída com sucesso!"]);
        } else {
            echo json_encode(["sucesso" => false, "mensagem" => "Publicação não encontrada."]);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["sucesso" => false, "mensagem" => "Erro: " . $e->getMessage()]);
}
?>