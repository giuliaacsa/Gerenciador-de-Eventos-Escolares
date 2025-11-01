<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificação adicional de segurança
    $confirmacao = $_POST['confirmacao'] ?? '';
    
    if (strtoupper($confirmacao) !== 'EXCLUIR') {
        $_SESSION['erro'] = "Confirmação inválida!";
        header("Location: dashboard.php");
        exit();
    }
    
    try {
        $id_usuario = $_SESSION['usuario_id'];
        
        // Iniciar transação para garantir consistência
        $pdo->beginTransaction();
        
        // 1. Deletar todas as inscrições do usuário
        $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        
        // 2. Se o usuário for administrador e criou eventos, 
        // precisamos lidar com os eventos criados por ele
        // Opção 1: Transferir eventos para outro admin
        // Opção 2: Deletar eventos e suas inscrições (implementada abaixo)
        
        // Buscar eventos criados pelo usuário
        $stmt = $pdo->prepare("SELECT id_evento FROM eventos WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $eventos_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($eventos_usuario)) {
            // Deletar inscrições nesses eventos
            $placeholders = str_repeat('?,', count($eventos_usuario) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_evento IN ($placeholders)");
            $stmt->execute($eventos_usuario);
            
            // Deletar os eventos
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
        }
        
        // 3. Deletar foto de perfil se existir
        $foto_perfil = $_SESSION['foto_perfil'] ?? '';
        if (!empty($foto_perfil) && file_exists('uploads/perfil/' . $foto_perfil)) {
            unlink('uploads/perfil/' . $foto_perfil);
        }
        
        // 4. Finalmente, deletar o usuário
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        
        // Confirmar transação
        $pdo->commit();
        
        // Limpar sessão
        session_destroy();
        
        // Redirecionar para página inicial com mensagem de sucesso
        $_SESSION['sucesso'] = "Sua conta foi excluída com sucesso. Sentiremos sua falta!";
        header("Location: index.html");
        exit();
        
    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        $_SESSION['erro'] = "Erro ao excluir conta: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
} else {
    // Se acessado diretamente via GET, mostrar formulário de confirmação
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Exclusão - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white text-center">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">Atenção!</h6>
                            <p class="mb-2">Você está prestes a excluir permanentemente sua conta. Esta ação:</p>
                            <ul class="mb-2">
                                <li>Removerá todos os seus dados pessoais</li>
                                <li>Cancelará todas as suas inscrições em eventos</li>
                                <li>Se você criou eventos, eles também serão excluídos</li>
                                <li><strong>Não poderá ser desfeita</strong></li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="confirmacao" class="form-label">
                                    Para confirmar, digite <strong class="text-danger">EXCLUIR</strong> no campo abaixo:
                                </label>
                                <input type="text" class="form-control" id="confirmacao" name="confirmacao" 
                                       placeholder="Digite EXCLUIR aqui" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger" id="submitBtn" disabled>
                                    <i class="fas fa-trash-alt me-2"></i>Excluir Minha Conta Permanentemente
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validação em tempo real
        document.getElementById('confirmacao').addEventListener('input', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const confirmText = e.target.value.trim().toUpperCase();
            
            submitBtn.disabled = confirmText !== 'EXCLUIR';
        });
        
        // Prevenir envio acidental
        document.querySelector('form').addEventListener('submit', function(e) {
            const confirmText = document.getElementById('confirmacao').value.trim().toUpperCase();
            
            if (confirmText !== 'EXCLUIR') {
                e.preventDefault();
                alert('Por favor, digite "EXCLUIR" para confirmar a exclusão.');
            } else if (!confirm('Tem certeza absoluta que deseja excluir sua conta? Esta ação é IRREVERSÍVEL!')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php
}
?>