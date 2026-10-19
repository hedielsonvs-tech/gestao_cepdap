<?php
// conexao.php - Exemplo simples de conexão PDO
$host = 'localhost';
$db = 'cepdap_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Portal Pedagógico</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
            --cepdap-orange: #fd7e14;
        }

        body {
            background-color: #f4f6f9;
            color: #333;
        }

        .navbar-cepdap {
            background-color: var(--cepdap-blue);
        }

        .btn-cepdap {
            background-color: var(--cepdap-green);
            color: white;
        }
        .btn-cepdap:hover {
            background-color: #218838;
            color: white;
        }

        /* Estilo exclusivo para Desktop simulando proporção/formato A4 centralizado */
        @media (min-width: 992px) {
            .a4-desktop {
                max-width: 210mm;
                min-height: 297mm;
                margin: 30px auto;
                background: white;
                padding: 20mm;
                box-shadow: 0 0 15px rgba(0,0,0,0.15);
                border-radius: 4px;
            }
        }
    </style>
</head>
<body>

    <!-- Cabeçalho / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-cepdap shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <!-- Simbolizando a logo institucional -->
                <span class="badge bg-success me-2">CEPDAP</span> Portal Pedagógico
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="#">Início</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Disciplinas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Materiais</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="#">Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal Envolvido no Layout A4 para Desktop / Fluido para Mobile -->
    <div class="container">
        <div class="a4-desktop">
            
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h2 class="text-primary fw-bold">Mural de Materiais</h2>
                    <p class="text-muted mb-0">Compartilhamento de conteúdos pedagógicos - CEPDAP</p>
                </div>
                <div>
                    <button class="btn btn-cepdap" data-bs-toggle="modal" data-bs-target="#modalNovoMaterial">+ Novo Material</button>
                </div>
            </div>

            <!-- Filtros de Disciplinas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <select class="form-select">
                        <option selected>Filtrar por Disciplina...</option>
                        <option value="1">Matemática</option>
                        <option value="2">Português</option>
                        <option value="3">Informática</option>
                    </select>
                </div>
            </div>

            <!-- Listagem de Materiais -->
            <div class="row g-3">
                <?php
                // Exemplo simulado de listagem do banco de dados
                $materiaisExemplo = [
                    ["titulo" => "Apostila de Algoritmos PHP", "disciplina" => "Informática", "professor" => "Prof. Carlos", "data" => "16/09/2026"],
                    ["titulo" => "Lista de Exercícios - Funções", "disciplina" => "Matemática", "professor" => "Profª Ana", "data" => "15/09/2026"]
                ];

                foreach ($materiaisExemplo as $mat) {
                    echo '
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <span class="badge bg-secondary mb-2">'.$mat['disciplina'].'</span>
                                <h5 class="card-title text-dark fw-bold">'.$mat['titulo'].'</h5>
                                <p class="card-text text-muted small mb-2">Enviado por: '.$mat['professor'].' em '.$mat['data'].'</p>
                                <a href="#" class="btn btn-outline-primary btn-sm">Baixar Material</a>
                            </div>
                        </div>
                    </div>';
                }
                ?>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>