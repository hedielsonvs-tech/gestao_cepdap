<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não autenticado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['descriptor'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados faciais inválidos.']);
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$descriptorJson = json_encode($input['descriptor']);

try {
    $stmt = $pdo->prepare("INSERT INTO descritores_faciais (id_usuario, descriptor) VALUES (?, ?) ON DUPLICATE KEY UPDATE descriptor = ?");
    $stmt->execute([$id_usuario, $descriptorJson, $descriptorJson]);

    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}