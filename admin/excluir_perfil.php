<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Verificar se é administrador (opcional - pode remover se quiser que admin também possa excluir sua conta)
if ($_SESSION['usuario_tipo'] == 'administrador') {
    // Contar quantos administradores existem
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'administrador'");
    $stmt->execute();
    $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Impedir exclusão se for o único administrador
    if ($total_admins <= 1) {
        $_SESSION['erro'] = "Não é possível excluir a conta. Você é o único administrador do sistema.";
        header("Location: index.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $confirmacao = $_POST['confirmacao'] ?? '';
    
    if (strtoupper($confirmacao) !== 'EXCLUIR') {
        $_SESSION['erro'] = "Confirmação inválida!";
        header("Location: index.php");
        exit();
    }
    
    try {
        $id_usuario = $_SESSION['usuario_id'];
        $pdo->beginTransaction();
        
        // Deletar inscrições
        $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        
        // Buscar e lidar com eventos criados
        $stmt = $pdo->prepare("SELECT id_evento FROM eventos WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        $eventos_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($eventos_usuario)) {
            $placeholders = str_repeat('?,', count($eventos_usuario) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_evento IN ($placeholders)");
            $stmt->execute($eventos_usuario);
            
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
        }
        
        // Deletar foto
        $foto_perfil = $_SESSION['foto_perfil'] ?? '';
        if (!empty($foto_perfil) && file_exists('../uploads/perfil/' . $foto_perfil)) {
            unlink('../uploads/perfil/' . $foto_perfil);
        }
        
        // Deletar usuário
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        
        $pdo->commit();
        session_destroy();
        
        $_SESSION['sucesso'] = "Sua conta foi excluída com sucesso.";
        header("Location: ../index.html");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['erro'] = "Erro ao excluir conta: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Exclusão - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 text-danger">Excluir Minha Conta</h1>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white text-center">
                                <h4><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h4>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <h6 class="alert-heading">Atenção Administrador!</h6>
                                    <p class="mb-2">Como administrador, a exclusão de sua conta também afetará:</p>
                                    <ul class="mb-2">
                                        <li>Todos os eventos criados por você serão excluídos</li>
                                        <li>As inscrições nesses eventos também serão removidas</li>
                                        <li>Sua conta de administrador será permanentemente removida</li>
                                        <li><strong>Esta ação é irreversível!</strong></li>
                                    </ul>
                                </div>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="confirmacao" class="form-label">
                                            Digite <strong class="text-danger">EXCLUIR</strong> para confirmar:
                                        </label>
                                        <input type="text" class="form-control" id="confirmacao" name="confirmacao" 
                                               placeholder="Digite EXCLUIR aqui" required>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-danger" id="submitBtn" disabled>
                                            <i class="fas fa-trash-alt me-2"></i>Excluir Minha Conta
                                        </button>
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>Cancelar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('confirmacao').addEventListener('input', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const confirmText = e.target.value.trim().toUpperCase();
            
            submitBtn.disabled = confirmText !== 'EXCLUIR';
        });
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const confirmText = document.getElementById('confirmacao').value.trim().toUpperCase();
            
            if (confirmText !== 'EXCLUIR') {
                e.preventDefault();
                alert('Por favor, digite "EXCLUIR" para confirmar.');
            } else if (!confirm('ATENÇÃO: Você está excluindo uma conta de ADMINISTRADOR. Tem certeza absoluta?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php
}
?>