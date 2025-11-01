<?php
require_once 'includes/config.php';
session_start();

// Debug
error_log("Cadastro.php acessado via método: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug dos dados recebidos
    error_log("Dados do POST: " . print_r($_POST, true));
    
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $genero = $_POST['genero'] ?? 'nao_informar';
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    error_log("Nome: $nome, Email: $email, CPF: $cpf, Tipo: $tipo_usuario, Gênero: $genero");
    
    // Verificar se as senhas coincidem
    if ($senha !== $confirmar_senha) {
        error_log("Senhas não coincidem");
        header('Location: index.html?erro=senhas_nao_coincidem');
        exit;
    }
    
    // Validar força da senha
    if (strlen($senha) < 6) {
        error_log("Senha muito curta");
        header('Location: index.html?erro=senha_fraca');
        exit;
    }
    
    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Email inválido");
        header('Location: index.html?erro=email_invalido');
        exit;
    }
    
    // Validar CPF
    function validarCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Calcula e verifica os dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    if (!validarCPF($cpf)) {
        error_log("CPF inválido");
        header('Location: index.html?erro=cpf_invalido');
        exit;
    }
    
    try {
        // Verificar se o email já existe
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            error_log("Email já existe: $email");
            header('Location: index.html?erro=email_existente');
            exit;
        }
        
        // Verificar se o CPF já existe
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE cpf = ?");
        $stmt->execute([$cpf]);
        if ($stmt->rowCount() > 0) {
            error_log("CPF já existe: $cpf");
            header('Location: index.html?erro=cpf_existente');
            exit;
        }
        
        // Criptografar a senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        error_log("Senha hash gerada: $senha_hash");
        
        // CORREÇÃO: Primeiro usuário deve ser administrador, os demais alunos
        // Verificar se é o primeiro usuário do sistema
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Se for o primeiro usuário, definir como administrador
        // Caso contrário, usar o tipo selecionado no formulário
        if ($total_usuarios == 0) {
            $tipo_usuario_final = 'administrador';
            error_log("Primeiro usuário do sistema - definido como administrador");
        } else {
            $tipo_usuario_final = $tipo_usuario;
        }
        
        // Inserir no banco de dados
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, cpf, tipo_usuario, genero, data_cadastro, status_usuario) 
                              VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'ativo')");
        
        if ($stmt->execute([$nome, $email, $senha_hash, $cpf, $tipo_usuario_final, $genero])) {
            error_log("Usuário cadastrado com sucesso: $email - Tipo: $tipo_usuario_final");
            
            // Login automático após cadastro bem-sucedido
            $_SESSION['usuario_id'] = $pdo->lastInsertId();
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_tipo'] = $tipo_usuario_final;
            $_SESSION['email'] = $email;
            $_SESSION['genero'] = $genero;
            
            // CORREÇÃO: Redirecionar conforme o tipo de usuário FINAL
            if ($tipo_usuario_final == 'administrador') {
                header('Location: admin/index.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            error_log("Erro ao executar INSERT");
            header('Location: index.html?erro=erro_cadastro');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro PDO no cadastro: " . $e->getMessage());
        header('Location: index.html?erro=erro_banco_dados');
        exit;
    }
} else {
    error_log("Acesso direto ao cadastro.php sem POST");
    header('Location: index.html');
    exit;
}
?>