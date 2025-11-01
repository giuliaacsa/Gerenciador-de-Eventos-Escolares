<?php
// admin/index.php - CÓDIGO CORRIGIDO
require_once '../includes/config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.html");
    exit();
}

// Verificar se é administrador
if ($_SESSION['usuario_tipo'] != 'administrador') {
    header("Location: ../dashboard.php");
    exit();
}

// Determinar pronome baseado no gênero
$genero = $_SESSION['genero'] ?? 'nao_informar';
$pronome = 'Bem-vindo(a)'; // Padrão

switch($genero) {
    case 'masculino':
        $pronome = 'Bem-vindo';
        break;
    case 'feminino':
        $pronome = 'Bem-vinda';
        break;
    case 'outro':
    case 'nao_informar':
        $pronome = 'Bem-vindo(a)';
        break;
}

// Buscar estatísticas
try {
    // Total de eventos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM eventos");
    $total_eventos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de usuários
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total_usuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de inscrições
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inscricoes");
    $total_inscricoes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Eventos próximos
    $stmt = $pdo->prepare("
        SELECT e.*, COUNT(i.id_inscricao) as inscritos 
        FROM eventos e 
        LEFT JOIN inscricoes i ON e.id_evento = i.id_evento 
        WHERE e.data_evento >= CURDATE()
        GROUP BY e.id_evento 
        ORDER BY e.data_evento ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $eventos_proximos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro ao carregar estatísticas: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #E50914 0%, #8B0000 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
        }
        
        .user-profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .user-profile-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Banner de Boas-vindas -->
                <div class="welcome-banner mt-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 mb-3">
                                <?php echo $pronome; ?>, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!
                            </h1>
                            <p class="lead mb-0">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>Painel Administrativo</strong> - Gerenciando <?php echo $total_eventos; ?> evento(s)
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if (!empty($_SESSION['foto_perfil'])): ?>
                                <img src="../uploads/perfil/<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" 
                                     alt="Foto de perfil" 
                                     class="user-profile-photo">
                            <?php else: ?>
                                <div class="user-profile-placeholder">
                                    <i class="fas fa-user-shield fa-5x text-white opacity-75"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Eventos</h5>
                                        <p class="card-text display-4 mb-0"><?php echo $total_eventos; ?></p>
                                    </div>
                                    <div>
                                        <i class="fas fa-calendar-alt fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="eventos.php" class="text-white text-decoration-none">
                                    Ver todos <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Usuários</h5>
                                        <p class="card-text display-4 mb-0"><?php echo $total_usuarios; ?></p>
                                    </div>
                                    <div>
                                        <i class="fas fa-users fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="usuarios.php" class="text-white text-decoration-none">
                                    Ver todos <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Inscrições</h5>
                                        <p class="card-text display-4 mb-0"><?php echo $total_inscricoes; ?></p>
                                    </div>
                                    <div>
                                        <i class="fas fa-clipboard-list fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="relatorios.php" class="text-white text-decoration-none">
                                    Ver relatórios <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Próximos Eventos -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-calendar-check me-2"></i>Próximos Eventos
                            </div>
                            <div class="card-body">
                                <?php if (count($eventos_proximos) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Evento</th>
                                                    <th>Data</th>
                                                    <th>Local</th>
                                                    <th>Inscritos</th>
                                                    <th>Status</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($eventos_proximos as $evento): 
                                                    $vagas_disponiveis = $evento['limite_evento'] - $evento['inscritos'];
                                                    $percentual = $evento['limite_evento'] > 0 ? 
                                                        ($evento['inscritos'] / $evento['limite_evento']) * 100 : 0;
                                                    
                                                    if ($vagas_disponiveis <= 0) {
                                                        $status_class = 'danger';
                                                    } elseif ($percentual >= 80) {
                                                        $status_class = 'warning';
                                                    } else {
                                                        $status_class = 'success';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($evento['nome_evento']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></td>
                                                    <td><?php echo htmlspecialchars($evento['local']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo $evento['inscritos']; ?>/<?php echo $evento['limite_evento']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($evento['status_evento']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="eventos.php?action=view&id=<?php echo $evento['id_evento']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="eventos.php?action=edit&id=<?php echo $evento['id_evento']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Nenhum evento próximo cadastrado.</p>
                                    <a href="eventos.php?action=add" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Criar Primeiro Evento
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>