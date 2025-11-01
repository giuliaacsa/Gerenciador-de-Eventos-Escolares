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

// Buscar eventos em que o usuário está inscrito
$id_usuario = $_SESSION['usuario_id'];
$eventos_inscritos = [];

try {
    $stmt = $pdo->prepare("
        SELECT e.*, i.status, i.id_inscricao, i.data_inscricao 
        FROM eventos e 
        INNER JOIN inscricoes i ON e.id_evento = i.id_evento 
        WHERE i.id_usuario = ? 
        ORDER BY e.data_evento ASC
    ");
    $stmt->execute([$id_usuario]);
    $eventos_inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao carregar eventos: " . $e->getMessage();
}

// Processar cancelamento de inscrição
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_inscricao'])) {
    $id_inscricao = $_POST['id_inscricao'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_inscricao = ? AND id_usuario = ?");
        if ($stmt->execute([$id_inscricao, $id_usuario])) {
            $_SESSION['sucesso'] = "Inscrição cancelada com sucesso!";
            header("Location: meus-eventos.php");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erro ao cancelar inscrição: " . $e->getMessage();
    }
}

// Separar eventos por período
$eventos_futuros = array_filter($eventos_inscritos, function($evento) {
    return new DateTime($evento['data_evento']) >= new DateTime('today');
});

$eventos_passados = array_filter($eventos_inscritos, function($evento) {
    return new DateTime($evento['data_evento']) < new DateTime('today');
});
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Eventos - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .poster-card {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            height: 500px;
            background: #1a1a1a;
        }
        
        .poster-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.4);
        }
        
        .poster-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .poster-card:hover .poster-image {
            transform: scale(1.1);
        }
        
        .poster-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .poster-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 60%, transparent 100%);
            padding: 25px 20px;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .poster-card:hover .poster-overlay {
            background: linear-gradient(to top, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.85) 70%, rgba(0,0,0,0.6) 100%);
        }
        
        .poster-title {
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        
        .poster-info {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .poster-info i {
            margin-right: 8px;
            color: #E50914;
            width: 16px;
        }
        
        .poster-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 15px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 0.85rem;
            backdrop-filter: blur(10px);
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .poster-actions {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }
        
        .btn-cancel {
            background: rgba(220, 53, 69, 0.9);
            border: 2px solid white;
            color: white;
            padding: 6px 15px;
            border-radius: 25px;
            font-weight: bold;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .btn-cancel:hover {
            background: white;
            color: #dc3545;
            transform: scale(1.05);
        }
        
        .section-header {
            background: linear-gradient(135deg, #E50914 0%, #8B0000 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #E50914;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-active {
            color: #E50914 !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2">
                        <i class="fas fa-ticket-alt me-2" style="color: #E50914;"></i>
                        Meus Eventos
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="eventos-disponiveis.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>Buscar Mais Eventos
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Estatísticas -->
                <?php if (count($eventos_inscritos) > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($eventos_inscritos); ?></div>
                            <div class="stats-label">Total de Eventos</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-success"><?php echo count($eventos_futuros); ?></div>
                            <div class="stats-label">Próximos</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-info">
                                <?php 
                                $presentes = array_filter($eventos_inscritos, fn($e) => $e['status'] == 'presente');
                                echo count($presentes); 
                                ?>
                            </div>
                            <div class="stats-label">Presentes</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="stats-number text-secondary"><?php echo count($eventos_passados); ?></div>
                            <div class="stats-label">Realizados</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Eventos Futuros -->
                <?php if (count($eventos_futuros) > 0): ?>
                <div class="section-header">
                    <h3 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Próximos Eventos (<?php echo count($eventos_futuros); ?>)
                    </h3>
                </div>

                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4 mb-5">
                    <?php foreach ($eventos_futuros as $evento): 
                        $data_evento = new DateTime($evento['data_evento']);
                        $hoje = new DateTime();
                        $pode_cancelar = $data_evento > $hoje;
                    ?>
                    <div class="col">
                        <div class="poster-card" onclick="window.location.href='detalhes-evento.php?id=<?php echo $evento['id_evento']; ?>'">
                            <!-- Badge de Status -->
                            <div class="poster-badge bg-<?php 
                                echo $evento['status'] == 'presente' ? 'success' : 
                                    ($evento['status'] == 'ausente' ? 'danger' : 'info'); 
                            ?>">
                                <?php echo ucfirst($evento['status']); ?>
                            </div>
                            
                            <!-- Botão de Cancelar -->
                            <?php if ($pode_cancelar): ?>
                            <div class="poster-actions">
                                <form method="POST" class="d-inline" onsubmit="event.stopPropagation(); return confirm('Tem certeza que deseja cancelar sua inscrição?');">
                                    <input type="hidden" name="id_inscricao" value="<?php echo $evento['id_inscricao']; ?>">
                                    <button type="submit" name="cancelar_inscricao" class="btn-cancel">
                                        <i class="fas fa-times me-1"></i>Cancelar
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Imagem ou Placeholder -->
                            <?php if (!empty($evento['imagem_evento'])): ?>
                                <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                     alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                     class="poster-image">
                            <?php else: ?>
                                <div class="poster-placeholder">
                                    <i class="fas fa-calendar-check fa-5x text-white opacity-50"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Overlay com Informações -->
                            <div class="poster-overlay">
                                <h5 class="poster-title"><?php echo htmlspecialchars($evento['nome_evento']); ?></h5>
                                
                                <div class="poster-info">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></span>
                                </div>
                                
                                <div class="poster-info">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('H:i', strtotime($evento['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></span>
                                </div>
                                
                                <div class="poster-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars(substr($evento['local'], 0, 30)); ?><?php echo strlen($evento['local']) > 30 ? '...' : ''; ?></span>
                                </div>
                                
                                <div class="poster-info mt-2">
                                    <i class="fas fa-user-check"></i>
                                    <span>Inscrito em <?php echo date('d/m/Y', strtotime($evento['data_inscricao'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Eventos Passados -->
                <?php if (count($eventos_passados) > 0): ?>
                <div class="section-header" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                    <h3 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Eventos Realizados (<?php echo count($eventos_passados); ?>)
                    </h3>
                </div>

                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4 mb-5">
                    <?php foreach ($eventos_passados as $evento): ?>
                    <div class="col">
                        <div class="poster-card" onclick="window.location.href='detalhes-evento.php?id=<?php echo $evento['id_evento']; ?>'" style="opacity: 0.8;">
                            <!-- Badge de Status -->
                            <div class="poster-badge bg-<?php 
                                echo $evento['status'] == 'presente' ? 'success' : 
                                    ($evento['status'] == 'ausente' ? 'danger' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($evento['status']); ?>
                            </div>
                            
                            <!-- Imagem ou Placeholder -->
                            <?php if (!empty($evento['imagem_evento'])): ?>
                                <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                     alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                     class="poster-image" style="filter: grayscale(30%);">
                            <?php else: ?>
                                <div class="poster-placeholder" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                                    <i class="fas fa-calendar-check fa-5x text-white opacity-50"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Overlay com Informações -->
                            <div class="poster-overlay">
                                <h5 class="poster-title"><?php echo htmlspecialchars($evento['nome_evento']); ?></h5>
                                
                                <div class="poster-info">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></span>
                                </div>
                                
                                <div class="poster-info">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('H:i', strtotime($evento['hora_inicio'])); ?> - <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></span>
                                </div>
                                
                                <div class="poster-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars(substr($evento['local'], 0, 30)); ?><?php echo strlen($evento['local']) > 30 ? '...' : ''; ?></span>
                                </div>
                                
                                <div class="mt-2 text-center">
                                    <small class="text-white-50">Evento Realizado</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Mensagem quando não há eventos -->
                <?php if (count($eventos_inscritos) == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                    <h3 class="text-muted">Você não está inscrito em nenhum evento</h3>
                    <p class="text-muted mb-4">Explore os eventos disponíveis e faça sua primeira inscrição!</p>
                    <a href="eventos-disponiveis.php" class="btn btn-primary btn-lg" style="border-radius: 50px; padding: 15px 40px;">
                        <i class="fas fa-search me-2"></i>Buscar Eventos
                    </a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Prevenir propagação do clique nos botões de cancelar
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>