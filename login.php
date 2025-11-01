<?php
require_once 'includes/config.php';
session_start();

// Verificar se há cookie de "lembrar-me" e fazer login automático
if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['lembrar_email']) && isset($_COOKIE['lembrar_token'])) {
    $email = $_COOKIE['lembrar_email'];
    $token = $_COOKIE['lembrar_token'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar se o token corresponde
        if ($usuario && hash_equals($token, hash('sha256', $usuario['id_usuario'] . $usuario['senha']))) {
            // Login automático
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['genero'] = $usuario['genero'] ?? 'nao_informar';
            $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? null;
            
            if ($usuario['tipo_usuario'] == 'administrador') {
                header('Location: admin/index.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro no login automático: " . $e->getMessage());
    }
}

// Redirecionar se já estiver logado
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_tipo'] == 'administrador') {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $lembrar = isset($_POST['lembrar']);
    
    error_log("Tentativa de login: Email: $email, Lembrar: " . ($lembrar ? 'sim' : 'não'));
    
    if (empty($email) || empty($senha)) {
        header('Location: index.html?erro=campos_vazios');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['genero'] = $usuario['genero'] ?? 'nao_informar';
            $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? null;
            
            // Se "lembrar-me" foi marcado, criar cookies
            if ($lembrar) {
                // Cookie válido por 30 dias
                $expire = time() + (30 * 24 * 60 * 60);
                
                // Gerar token único baseado no ID e senha hash
                $token = hash('sha256', $usuario['id_usuario'] . $usuario['senha']);
                
                // Definir cookies seguros
                setcookie('lembrar_email', $email, $expire, '/', '', false, true);
                setcookie('lembrar_token', $token, $expire, '/', '', false, true);
                
                error_log("Cookies de 'lembrar-me' definidos para: $email");
            }
            
            error_log("Login bem-sucedido para: " . $usuario['email']);
            
            // Redirecionar conforme o tipo de usuário
            if ($usuario['tipo_usuario'] == 'administrador') {
                header('Location: admin/index.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            error_log("Falha no login para: $email");
            header('Location: index.html?erro=credenciais_invalidas');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        header('Location: index.html?erro=erro_banco_dados');
        exit;
    }
} else {
    header('Location: index.html');
    exit;
}
?>