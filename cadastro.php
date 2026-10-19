<?php
require_once 'conexao.php';

$mensagem = '';
$tipo_msg = '';

// Busca cursos para preencher o select
$stmtCursos = $pdo->query("SELECT * FROM cursos ORDER BY nome ASC");
$cursos = $stmtCursos->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $cpf = trim($_POST['cpf']);
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);
    $tipo = $_POST['tipo']; // 'professor', 'aluno' ou 'admin'
    $id_curso = ($tipo == 'aluno') ? (!empty($_POST['id_curso']) ? $_POST['id_curso'] : null) : null;
    $id_turma = ($tipo == 'aluno') ? (!empty($_POST['id_turma']) ? $_POST['id_turma'] : null) : null;

    if (!empty($nome) && !empty($cpf) && !empty($email) && !empty($senha) && !empty($tipo)) {
        
        // Verifica se o CPF já existe no banco
        $stmtCheckCpf = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = :cpf LIMIT 1");
        $stmtCheckCpf->execute([':cpf' => $cpf]);

        // Verifica se o E-mail já existe no banco
        $stmtCheckEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmtCheckEmail->execute([':email' => $email]);
        
        if ($stmtCheckCpf->rowCount() > 0) {
            $mensagem = "Este CPF já está cadastrado no sistema!";
            $tipo_msg = "danger";
        } elseif ($stmtCheckEmail->rowCount() > 0) {
            $mensagem = "Este e-mail já está cadastrado no sistema!";
            $tipo_msg = "danger";
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            
            // Certifique-se de que a coluna 'cpf' existe na sua tabela 'usuarios'
            $sql = "INSERT INTO usuarios (nome, cpf, email, senha, tipo, id_curso, id_turma) 
                    VALUES (:nome, :cpf, :email, :senha, :tipo, :id_curso, :id_turma)";
            $stmt = $pdo->prepare($sql);
            $sucesso = $stmt->execute([
                ':nome' => $nome,
                ':cpf' => $cpf,
                ':email' => $email,
                ':senha' => $senhaHash,
                ':tipo' => $tipo,
                ':id_curso' => $id_curso,
                ':id_turma' => $id_turma
            ]);

            if ($sucesso) {
                $mensagem = "Cadastro realizado com sucesso! <a href='login.php'>Faça login aqui</a>.";
                $tipo_msg = "success";
            } else {
                $mensagem = "Erro ao realizar cadastro. Tente novamente.";
                $tipo_msg = "danger";
            }
        }
    } else {
        $mensagem = "Por favor, preencha todos os campos obrigatórios.";
        $tipo_msg = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Cadastro de Usuário</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
        }

        body {
            background-color: #f4f6f9;
            padding: 20px 0;
        }

        /* Estilo simulando folha A4 centralizada no Desktop */
        @media (min-width: 992px) {
            .a4-desktop-cadastro {
                width: 210mm;
                max-width: 100%;
                min-height: 250mm;
                background: white;
                padding: 30mm 25mm;
                box-shadow: 0 0 15px rgba(0,0,0,0.15);
                border-radius: 6px;
                margin: auto;
            }
        }

        /* Estilo para Dispositivos Móveis */
        @media (max-width: 991px) {
            .card-mobile-cadastro {
                width: 100%;
                max-width: 500px;
                padding: 20px;
                background: white;
                border-radius: 6px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                margin: auto;
            }
        }

        .logo-img {
            max-width: 100px;
            height: auto;
            margin-bottom: 10px;
        }

        .btn-cepdap {
            background-color: var(--cepdap-green);
            color: white;
        }
        .btn-cepdap:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <!-- Layout Desktop (A4) / Mobile Separados -->
        <div class="a4-desktop-cadastro card-mobile-cadastro">
            
            <div class="text-center mb-4">
                <img src="logo.png" alt="Logo CEPDAP" class="logo-img">
                <h3 class="fw-bold text-primary">Cadastro no Portal</h3>
                <p class="text-muted small">Preencha os dados para criar sua conta</p>
            </div>

            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?= $tipo_msg; ?> text-center py-2" role="alert">
                    <?= $mensagem; ?>
                </div>
            <?php endif; ?>

            <form action="cadastro.php" method="POST" id="formCadastro">
                <div class="mb-3">
                    <label for="nome" class="form-label fw-semibold">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required placeholder="Seu nome completo">
                </div>

                <div class="mb-3">
                    <label for="cpf" class="form-label fw-semibold">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" required placeholder="000.000.000-00" maxlength="14" oninput="mascaraCPF(this)">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">E-mail Institucional</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu.email@escola.com">
                </div>

                <div class="mb-3">
                    <label for="senha" class="form-label fw-semibold">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required placeholder="********">
                </div>

                <div class="mb-3">
                    <label for="tipo" class="form-label fw-semibold">Tipo de Usuário</label>
                    <select class="form-select" id="tipo" name="tipo" required onchange="controlarCamposAluno()">
                        <option value="" selected disabled>Selecione o perfil...</option>
                        <option value="aluno">Aluno</option>
                        <option value="professor">Professor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <!-- Campos exclusivos para Alunos (Ocultos por padrão) -->
                <div id="camposAluno" style="display: none;">
                    <div class="mb-3">
                        <label for="id_curso" class="form-label fw-semibold">Curso</label>
                        <select class="form-select" id="id_curso" name="id_curso" onchange="carregarTurmas(this.value)">
                            <option value="">Selecione o curso...</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?= $curso['id']; ?>"><?= htmlspecialchars($curso['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_turma" class="form-label fw-semibold">Turma</label>
                        <select class="form-select" id="id_turma" name="id_turma">
                            <option value="">Selecione o curso primeiro...</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-cepdap btn-lg">Cadastrar-se</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none small">Já possui uma conta? Faça login</a>
            </div>

        </div>
    </div>

    <!-- Script JavaScript para alternar campos, máscara de CPF e carregar turmas via AJAX -->
    <script>
        // Função para formatar o CPF automaticamente (000.000.000-00)
        function mascaraCPF(input) {
            let v = input.value.replace(/\D/g, "");
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 9) {
                v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
            } else if (v.length > 6) {
                v = v.replace(/^(\d{3})(\d{3})(\d{0,3})$/, "$1.$2.$3");
            } else if (v.length > 3) {
                v = v.replace(/^(\d{3})(\d{0,3})$/, "$1.$2");
            }
            input.value = v;
        }

        function controlarCamposAluno() {
            const tipo = document.getElementById('tipo').value;
            const camposAluno = document.getElementById('camposAluno');
            
            if (tipo === 'aluno') {
                camposAluno.style.display = 'block';
                document.getElementById('id_curso').required = true;
                document.getElementById('id_turma').required = true;
            } else {
                camposAluno.style.display = 'none';
                document.getElementById('id_curso').required = false;
                document.getElementById('id_turma').required = false;
            }
        }

        function carregarTurmas(idCurso) {
            const selectTurma = document.getElementById('id_turma');
            selectTurma.innerHTML = '<option value="">Carregando turmas...</option>';

            if (!idCurso) {
                selectTurma.innerHTML = '<option value="">Selecione o curso primeiro...</option>';
                return;
            }

            fetch('get_turmas.php?id_curso=' + idCurso)
                .then(response => response.json())
                .then(data => {
                    selectTurma.innerHTML = '<option value="">Selecione a turma...</option>';
                    data.forEach(turma => {
                        let option = document.createElement('option');
                        option.value = turma.id;
                        option.textContent = turma.nome;
                        selectTurma.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erro:', error);
                    selectTurma.innerHTML = '<option value="">Erro ao carregar turmas</option>';
                });
        }
    </script>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>