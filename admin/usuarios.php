<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Verificar se o usuário é administrador
if ($_SESSION['usuario_tipo'] != 'administrador') {
    header("Location: ../dashboard.php");
    exit();
}

// Processar ações
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Listar usuários
if ($action == 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erro ao carregar usuários: " . $e->getMessage();
    }
}

// Alterar tipo de usuário
if ($action == 'change_type' && $id) {
    $novo_tipo = $_GET['type'];
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET tipo_usuario = ? WHERE id_usuario = ?");
        $stmt->execute([$novo_tipo, $id]);
        $success = "Tipo de usuário alterado com sucesso!";
        header("Location: usuarios.php");
        exit();
    } catch (PDOException $e) {
        $error = "Erro ao alterar tipo de usuário: " . $e->getMessage();
    }
}

// EXCLUIR USUÁRIO - NOVA FUNCIONALIDADE
if ($action == 'delete' && $id) {
    try {
        // Verificar se não é o próprio usuário logado
        if ($id == $_SESSION['usuario_id']) {
            $_SESSION['erro'] = "Você não pode excluir sua própria conta!";
            header("Location: usuarios.php");
            exit();
        }
        
        // Verificar se é o único administrador
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'administrador'");
        $stmt->execute();
        $total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->prepare("SELECT tipo_usuario FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario['tipo_usuario'] == 'administrador' && $total_admins <= 1) {
            $_SESSION['erro'] = "Não é possível excluir o único administrador do sistema!";
            header("Location: usuarios.php");
            exit();
        }
        
        // Iniciar transação
        $pdo->beginTransaction();
        
        // 1. Deletar todas as inscrições do usuário
        $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_usuario = ?");
        $stmt->execute([$id]);
        
        // 2. Buscar eventos criados pelo usuário
        $stmt = $pdo->prepare("SELECT id_evento FROM eventos WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $eventos_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 3. Se o usuário criou eventos, deletar inscrições nesses eventos e depois os eventos
        if (!empty($eventos_usuario)) {
            $placeholders = str_repeat('?,', count($eventos_usuario) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_evento IN ($placeholders)");
            $stmt->execute($eventos_usuario);
            
            $stmt = $pdo->prepare("DELETE FROM eventos WHERE id_usuario = ?");
            $stmt->execute([$id]);
        }
        
        // 4. Buscar foto do usuário para deletar
        $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuario_foto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 5. Deletar foto se existir
        if (!empty($usuario_foto['foto_perfil']) && file_exists('../uploads/perfil/' . $usuario_foto['foto_perfil'])) {
            unlink('../uploads/perfil/' . $usuario_foto['foto_perfil']);
        }
        
        // 6. Finalmente, deletar o usuário
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        
        // Confirmar transação
        $pdo->commit();
        
        $_SESSION['sucesso'] = "Usuário excluído com sucesso!";
        header("Location: usuarios.php");
        exit();
        
    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        $_SESSION['erro'] = "Erro ao excluir usuário: " . $e->getMessage();
        header("Location: usuarios.php");
        exit();
    }
}

// Buscar usuário para visualização
if ($action == 'view' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            $error = "Usuário não encontrado!";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = "Erro ao carregar usuário: " . $e->getMessage();
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .user-photo-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #E50914;
        }
        
        .user-photo-placeholder-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #E50914;
        }
        
        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-group-sm {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gerenciar Usuários</h1>
                </div>

                <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['erro'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>CPF</th>
                                <th>Tipo</th>
                                <th>Data de Cadastro</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div class="user-info-cell">
                                        <?php if (!empty($usuario['foto_perfil'])): ?>
                                            <img src="../uploads/perfil/<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" 
                                                 alt="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                                 class="user-photo-small">
                                        <?php else: ?>
                                            <div class="user-photo-placeholder-small">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($usuario['nome']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['cpf']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $usuario['tipo_usuario'] == 'administrador' ? 'danger' : 
                                            ($usuario['tipo_usuario'] == 'professor' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($usuario['tipo_usuario']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $usuario['status_usuario'] == 'ativo' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($usuario['status_usuario']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-sm">
                                        <a href="usuarios.php?action=view&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($usuario['tipo_usuario'] != 'administrador'): ?>
                                        <a href="usuarios.php?action=change_type&id=<?php echo $usuario['id_usuario']; ?>&type=administrador" class="btn btn-sm btn-warning"
                                           onclick="return confirm('Tornar <?php echo htmlspecialchars($usuario['nome']); ?> administrador?')">
                                            <i class="fas fa-user-shield"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="usuarios.php?action=change_type&id=<?php echo $usuario['id_usuario']; ?>&type=aluno" class="btn btn-sm btn-secondary"
                                           onclick="return confirm('Reverter <?php echo htmlspecialchars($usuario['nome']); ?> para aluno?')">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- BOTÃO EXCLUIR USUÁRIO -->
                                        <?php if ($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                                        <a href="usuarios.php?action=delete&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('ATENÇÃO! Isso excluirá permanentemente o usuário <?php echo htmlspecialchars($usuario['nome']); ?> e todos os seus dados. Continuar?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Você não pode excluir sua própria conta">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($action == 'view'): ?>
                <div class="card">
                    <div class="card-header">
                        Detalhes do Usuário: <?php echo htmlspecialchars($usuario['nome']); ?>
                        <div class="float-end">
                            <?php if ($usuario['id_usuario'] != $_SESSION['usuario_id']): ?>
                            <a href="usuarios.php?action=delete&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('ATENÇÃO! Isso excluirá permanentemente o usuário <?php echo htmlspecialchars($usuario['nome']); ?> e todos os seus dados. Continuar?')">
                                <i class="fas fa-trash me-1"></i>Excluir Usuário
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Foto do Usuário -->
                            <div class="col-md-3 text-center mb-4">
                                <?php if (!empty($usuario['foto_perfil'])): ?>
                                    <img src="../uploads/perfil/<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" 
                                         alt="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                         class="img-fluid rounded-circle mb-3"
                                         style="width: 200px; height: 200px; object-fit: cover; border: 4px solid #E50914;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" 
                                         style="width: 200px; height: 200px; border: 4px solid #E50914;">
                                        <i class="fas fa-user fa-5x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Informações do Usuário -->
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Informações Pessoais</h5>
                                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($usuario['nome']); ?></p>
                                        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                                        <p><strong>CPF:</strong> <?php echo htmlspecialchars($usuario['cpf']); ?></p>
                                        <p><strong>Gênero:</strong> 
                                            <?php 
                                            $generos = [
                                                'masculino' => 'Masculino',
                                                'feminino' => 'Feminino',
                                                'outro' => 'Outro',
                                                'nao_informar' => 'Não informado'
                                            ];
                                            echo $generos[$usuario['genero']] ?? 'Não informado';
                                            ?>
                                        </p>
                                        <p><strong>Data de Cadastro:</strong> <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Status do Usuário</h5>
                                        <p><strong>Tipo:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $usuario['tipo_usuario'] == 'administrador' ? 'danger' : 
                                                    ($usuario['tipo_usuario'] == 'professor' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($usuario['tipo_usuario']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo $usuario['status_usuario'] == 'ativo' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($usuario['status_usuario']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="usuarios.php" class="btn btn-secondary">Voltar</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>