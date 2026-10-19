<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPDAP - Diagrama de Casos de Uso (UML)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Mermaid.js para renderizar o diagrama UML corretamente -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
    <script>
        mermaid.initialize({ startOnLoad: true, theme: 'default' });
    </script>
    <style>
        :root {
            --cepdap-blue: #0056b3;
            --cepdap-green: #28a745;
        }
        body { background-color: #f4f6f9; }

        /* Layout A4 para Desktop */
        @media (min-width: 992px) {
            .a4-desktop {
                width: 210mm;
                min-height: 297mm;
                margin: 20px auto;
                background: white;
                padding: 15mm 20mm;
                box-shadow: 0 0 15px rgba(0,0,0,0.15);
                border-radius: 4px;
            }
        }

        /* Layout fluido e otimizado para Celular/Mobile */
        @media (max-width: 991.98px) {
            body { background-color: #ffffff; }
            .container { padding-left: 10px; padding-right: 10px; }
            .mobile-wrapper { background: #ffffff; padding: 10px 0; box-shadow: none; border-radius: 0; width: 100%; margin: 0; }
        }

        .logo-img { max-width: 70px; height: auto; }
        
        /* Centralização do diagrama gerado */
        .mermaid {
            display: flex;
            justify-content: center;
            overflow-x: auto;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: var(--cepdap-blue);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="painel_professor.php">
                <img src="logo.png" alt="Logo" class="logo-img me-2 bg-white rounded p-1">
                <span class="fs-6 fs-md-5">Documentação do Sistema</span>
            </a>
            <div class="ms-auto">
                <a href="painel_professor.php" class="btn btn-outline-light btn-sm">Voltar ao Painel</a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container my-3 my-lg-4">
        <div class="a4-desktop mobile-wrapper">
            
            <div class="d-flex flex-column mb-3 border-bottom pb-3">
                <h3 class="text-primary fw-bold fs-4">Diagrama de Casos de Uso (Padrão UML)</h3>
                <p class="text-muted small mb-0">Representação estruturada com limites do sistema, atores externos e elipses de casos de uso do portal CEPDAP.</p>
            </div>

            <!-- Bloco do Diagrama Mermaid corrigido -->
            <div class="card border-0 shadow-sm p-3 mb-4 bg-light">
                <div class="card-body">
                    <div class="mermaid">
                        flowchart LR
                            subgraph Atores
                                Visitante([Visitante / Novo Usuário])
                                Admin([Administrador])
                                Prof([Professor])
                                Aluno([Aluno])
                            end

                            subgraph Sistema_CEPDAP [Sistema CEPDAP]
                                UC1(Realizar Cadastro)
                                UC2(Fazer Login)
                                UC3(Gerenciar Cadastros)
                                UC4(Atribuir Vínculos)
                                UC5(Pesquisar Atribuições)
                                UC6(Gerenciar Disciplina)
                            end

                            Visitante --> UC1
                            Visitante --> UC2

                            Admin --> UC2
                            Admin --> UC3
                            Admin --> UC4

                            Prof --> UC2
                            Prof --> UC5
                            Prof --> UC6

                            Aluno --> UC2

                            UC1 -.->|"&lt;&lt;include&gt;&gt;"| UC2
                    </div>
                </div>
            </div>

            <h5 class="fw-bold fs-6 text-dark mt-4">Regras de Estrutura Aplicadas:</h5>
            <ul class="small text-secondary ps-3">
                <li><strong>Fronteira do Sistema:</strong> Delimitada pelo retângulo central "Sistema CEPDAP", isolando o escopo interno do software.</li>
                <li><strong>Atores Externos:</strong> Posicionados nas margens representando os papéis que interagem com o sistema.</li>
                <li><strong>Relação de Dependência (&lt;&lt;include&gt;&gt;):</strong> Indica que o fluxo de cadastro obrigatoriamente passa pela etapa de autenticação/login.</li>
            </ul>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>