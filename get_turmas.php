<?php
require_once 'conexao.php';

header('Content-Type: application/json');

$id_curso = intval($_GET['id_curso'] ?? 0);

if ($id_curso > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, nome, turno FROM turmas WHERE id_curso = ? ORDER BY nome ASC");
        $stmt->execute([$id_curso]);
        $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($turmas);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>