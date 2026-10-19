<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'conexao.php';

try {

    $corpo = file_get_contents("php://input");

    if (!$corpo) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Nenhum dado recebido."
        ]);
        exit;
    }

    $data = json_decode($corpo);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "JSON inválido recebido."
        ]);
        exit;
    }

    $email = isset($data->email)
        ? trim($data->email)
        : '';

    $senha = isset($data->senha)
        ? $data->senha
        : '';

    if ($email === '' || $senha === '') {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Por favor, preencha todos os campos."
        ]);
        exit;
    }

    $sql = "
        SELECT id, nome, email, senha, tipo, status
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "E-mail ou senha inválidos!"
        ]);
        exit;
    }

    if ($usuario['status'] !== 'aprovado') {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Seu cadastro ainda está pendente de aprovação pela administração."
        ]);
        exit;
    }

    if (!password_verify($senha, $usuario['senha'])) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "E-mail ou senha inválidos!"
        ]);
        exit;
    }

    echo json_encode([
        "sucesso" => true,
        "mensagem" => "Login efetuado com sucesso!",
        "usuario" => [
            "id" => $usuario['id'],
            "nome" => $usuario['nome'],
            "tipo" => $usuario['tipo']
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro interno no servidor."
    ]);

}
?>