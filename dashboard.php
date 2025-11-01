<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Função para atualizar status dos eventos automaticamente
function atualizarStatusEventos($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE eventos 
            SET status_evento = 'expirado' 
            WHERE data_evento < CURDATE() 
            AND status_evento != 'cancelado'
            AND status_evento != 'expirado'
        ");
        $stmt->execute();
        
        $stmt = $pdo->prepare("
            UPDATE eventos e
            INNER JOIN (
                SELECT id_evento, COUNT(*) as total_inscritos
                FROM inscricoes
                GROUP BY id_evento
            ) i ON e.id_evento = i.id_evento
            SET e.status_evento = 'esgotado'
            WHERE i.total_inscritos >= e.limite_evento
            AND e.status_evento = 'disponível'
            AND e.data_evento >= CURDATE()
        ");
        $stmt->execute();
        
        $stmt = $pdo->prepare("
            UPDATE eventos e
            LEFT JOIN (
                SELECT id_evento, COUNT(*) as total_inscritos
                FROM inscricoes
                GROUP BY id_evento
            ) i ON e.id_evento = i.id_evento
            SET e.status_evento = 'disponível'
            WHERE (i.total_inscritos < e.limite_evento OR i.total_inscritos IS NULL)
            AND e.status_evento = 'esgotado'
            AND e.data_evento >= CURDATE()
        ");
        $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erro ao atualizar status dos eventos: " . $e->getMessage());
    }
}

atualizarStatusEventos($pdo);

// Verificar se o usuário é admin e redirecionar se for
if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'administrador') {
    header("Location: admin/index.php");
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

// Buscar eventos do usuário
$id_usuario = $_SESSION['usuario_id'];
$eventos_inscritos = [];
$eventos_disponiveis = [];

try {
    // Eventos em que o usuário está inscrito (próximos 4)
    $stmt = $pdo->prepare("
        SELECT e.*, i.status, i.id_inscricao
        FROM eventos e 
        INNER JOIN inscricoes i ON e.id_evento = i.id_evento 
        WHERE i.id_usuario = ? AND e.data_evento >= CURDATE()
        ORDER BY e.data_evento ASC
        LIMIT 4
    ");
    $stmt->execute([$id_usuario]);
    $eventos_inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eventos disponíveis para inscrição (próximos 4)
    $stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento) as inscritos_count
        FROM eventos e 
        WHERE e.data_evento >= CURDATE() 
        AND e.status_evento = 'disponível'
        AND e.id_evento NOT IN (SELECT id_evento FROM inscricoes WHERE id_usuario = ?)
        AND (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento) < e.limite_evento
        ORDER BY e.data_evento ASC
        LIMIT 4
    ");
    $stmt->execute([$id_usuario]);
    $eventos_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao carregar eventos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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
        
        .poster-card-small {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            height: 350px;
            background: #1a1a1a;
        }
        
        .poster-card-small:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(229, 9, 20, 0.4);
        }
        
        .poster-image-small {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .poster-card-small:hover .poster-image-small {
            transform: scale(1.1);
        }
        
        .poster-placeholder-small {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .poster-overlay-small {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.6) 70%, transparent 100%);
            padding: 20px 15px;
        }
        
        .poster-title-small {
            color: white;
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .poster-info-small {
            color: rgba(255,255,255,0.9);
            font-size: 0.85rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
        }
        
        .poster-info-small i {
            margin-right: 6px;
            color: #E50914;
            width: 14px;
        }
        
        .poster-badge-small {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.75rem;
            backdrop-filter: blur(10px);
            z-index: 10;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .section-title i {
            color: #E50914;
            margin-right: 10px;
        }
        
        .view-all-link {
            font-size: 0.9rem;
            color: #E50914;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .view-all-link:hover {
            color: #8B0000;
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px dashed #dee2e6;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Banner de Boas-vindas -->
                <div class="welcome-banner mt-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 mb-3">
                                <?php echo $pronome; ?>, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!
                            </h1>
                            <p class="lead mb-0">
                                <i class="fas fa-calendar-check me-2"></i>
                                Você está inscrito em <strong><?php echo count($eventos_inscritos); ?></strong> evento(s)
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if (!empty($_SESSION['foto_perfil'])): ?>
                                <img src="uploads/perfil/<?php echo htmlspecialchars($_SESSION['foto_perfil']); ?>" 
                                     alt="Foto de perfil" 
                                     class="user-profile-photo">
                            <?php else: ?>
                                <div class="user-profile-placeholder">
                                    <i class="fas fa-user-circle fa-5x text-white opacity-75"></i>
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

                <!-- Meus Eventos -->
                <div class="mb-5">
                    <div class="section-title">
                        <div>
                            <i class="fas fa-ticket-alt"></i>
                            Meus Próximos Eventos
                        </div>
                        <?php if (count($eventos_inscritos) > 0): ?>
                        <a href="meus-eventos.php" class="view-all-link">
                            Ver todos <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (count($eventos_inscritos) > 0): ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                            <?php foreach ($eventos_inscritos as $evento): ?>
                            <div class="col">
                                <div class="poster-card-small" onclick="window.location.href='detalhes-evento.php?id=<?php echo $evento['id_evento']; ?>'">
                                    <div class="poster-badge-small bg-<?php 
                                        echo $evento['status'] == 'presente' ? 'success' : 
                                            ($evento['status'] == 'ausente' ? 'danger' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($evento['status']); ?>
                                    </div>
                                    
                                    <?php if (!empty($evento['imagem_evento'])): ?>
                                        <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                             alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                             class="poster-image-small">
                                    <?php else: ?>
                                        <div class="poster-placeholder-small">
                                            <i class="fas fa-calendar-check fa-4x text-white opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="poster-overlay-small">
                                        <h6 class="poster-title-small"><?php echo htmlspecialchars($evento['nome_evento']); ?></h6>
                                        
                                        <div class="poster-info-small">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></span>
                                        </div>
                                        
                                        <div class="poster-info-small">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars(substr($evento['local'], 0, 25)); ?><?php echo strlen($evento['local']) > 25 ? '...' : ''; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>Você não está inscrito em nenhum evento</h4>
                            <p class="text-muted mb-3">Explore os eventos disponíveis abaixo</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Eventos Disponíveis -->
                <div class="mb-5">
                    <div class="section-title">
                        <div>
                            <i class="fas fa-star"></i>
                            Eventos Disponíveis
                        </div>
                        <?php if (count($eventos_disponiveis) > 0): ?>
                        <a href="eventos-disponiveis.php" class="view-all-link">
                            Ver todos <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (count($eventos_disponiveis) > 0): ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                            <?php foreach ($eventos_disponiveis as $evento): 
                                $vagas_disponiveis = $evento['limite_evento'] - $evento['inscritos_count'];
                                
                                if ($vagas_disponiveis <= 0) {
                                    $status_class = 'danger';
                                    $status_text = 'Esgotado';
                                } elseif ($vagas_disponiveis <= 5) {
                                    $status_class = 'warning';
                                    $status_text = 'Últimas vagas';
                                } else {
                                    $status_class = 'success';
                                    $status_text = 'Disponível';
                                }
                            ?>
                            <div class="col">
                                <div class="poster-card-small" onclick="window.location.href='detalhes-evento.php?id=<?php echo $evento['id_evento']; ?>'">
                                    <div class="poster-badge-small bg-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </div>
                                    
                                    <?php if (!empty($evento['imagem_evento'])): ?>
                                        <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                             alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                             class="poster-image-small">
                                    <?php else: ?>
                                        <div class="poster-placeholder-small">
                                            <i class="fas fa-calendar-plus fa-4x text-white opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="poster-overlay-small">
                                        <h6 class="poster-title-small"><?php echo htmlspecialchars($evento['nome_evento']); ?></h6>
                                        
                                        <div class="poster-info-small">
                                            <i class="fas fa-calendar"></i>
                                            <span><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></span>
                                        </div>
                                        
                                        <div class="poster-info-small">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo $vagas_disponiveis; ?> vagas</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-plus"></i>
                            <h4>Nenhum evento disponível no momento</h4>
                            <p class="text-muted mb-0">Novos eventos em breve!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>