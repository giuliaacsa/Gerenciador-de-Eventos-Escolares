<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $genero = $_POST['genero'] ?? 'nao_informar';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    // Verificar se as senhas coincidem
    if (!empty($senha) && $senha !== $confirmar_senha) {
        $_SESSION['erro'] = "As senhas não coincidem!";
        header("Location: index.php");
        exit();
    }
    
    try {
        // Verificar se o email já existe (exceto para o usuário atual)
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$email, $_SESSION['usuario_id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['erro'] = "Este e-mail já está em uso por outro usuário!";
            header("Location: index.php");
            exit();
        }
        
        // Processar upload de foto
        $foto_nome = null;
        $foto_atual = $_POST['foto_atual'] ?? '';
        
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
            $arquivo = $_FILES['foto_perfil'];
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($extensao, $extensoes_permitidas)) {
                // Verificar tamanho (máximo 2MB)
                if ($arquivo['size'] <= 2097152) {
                    // Criar pasta se não existir
                    $pasta_upload = '../uploads/perfil/';
                    if (!file_exists($pasta_upload)) {
                        mkdir($pasta_upload, 0755, true);
                    }
                    
                    // Gerar nome único para o arquivo
                    $foto_nome = uniqid() . '_' . time() . '.' . $extensao;
                    $caminho_completo = $pasta_upload . $foto_nome;
                    
                    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                        // Se havia foto anterior, deletar
                        if (!empty($foto_atual) && file_exists($pasta_upload . $foto_atual)) {
                            unlink($pasta_upload . $foto_atual);
                        }
                    } else {
                        $_SESSION['erro'] = "Erro ao fazer upload da foto.";
                        $foto_nome = $foto_atual;
                    }
                } else {
                    $_SESSION['erro'] = "A foto deve ter no máximo 2MB.";
                    $foto_nome = $foto_atual;
                }
            } else {
                $_SESSION['erro'] = "Formato de imagem não permitido. Use: JPG, PNG, GIF ou WEBP.";
                $foto_nome = $foto_atual;
            }
        } else {
            $foto_nome = $foto_atual;
        }
        
        // Atualizar informações do usuário
        if (!empty($senha)) {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ?, genero = ?, foto_perfil = ? WHERE id_usuario = ?");
            $stmt->execute([$nome, $email, $senha_hash, $genero, $foto_nome, $_SESSION['usuario_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, genero = ?, foto_perfil = ? WHERE id_usuario = ?");
            $stmt->execute([$nome, $email, $genero, $foto_nome, $_SESSION['usuario_id']]);
        }
        
        // Atualizar dados na sessão
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['email'] = $email;
        $_SESSION['genero'] = $genero;
        $_SESSION['foto_perfil'] = $foto_nome;
        
        $_SESSION['sucesso'] = "Perfil atualizado com sucesso!";
        header("Location: index.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao atualizar perfil: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    // Se alguém tentar acessar diretamente via GET
    header("Location: index.php");
    exit();
}
?>