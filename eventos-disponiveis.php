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

// Buscar eventos disponíveis para inscrição
$id_usuario = $_SESSION['usuario_id'];
$eventos_disponiveis = [];
$search = $_GET['search'] ?? '';

try {
    $query = "
        SELECT e.*, 
               (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento) as inscritos_count,
               (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento AND id_usuario = ?) as usuario_inscrito
        FROM eventos e 
        WHERE e.data_evento >= CURDATE() 
        AND e.status_evento = 'disponível'
        AND (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento) < e.limite_evento
    ";
    
    $params = [$id_usuario];
    
    if (!empty($search)) {
        $query .= " AND (e.nome_evento LIKE ? OR e.descricao_evento LIKE ? OR e.local LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY e.data_evento ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
    <title>Eventos Disponíveis - Eventos Escolares</title>
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
        
        .poster-action {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }
        
        .btn-view-details {
            background: rgba(229, 9, 20, 0.9);
            border: 2px solid white;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .btn-view-details:hover {
            background: white;
            color: #E50914;
            transform: scale(1.05);
        }
        
        .vagas-indicator {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            padding: 10px 15px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .vagas-text {
            color: white;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .mini-progress {
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .mini-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        /* NOVA BARRA DE PESQUISA - SEM PARTE BRANCA */
        .search-container {
            background: linear-gradient(135deg, #E50914 0%, #8B0000 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
        }
        
        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #333;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .search-input-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 15px 25px;
            color: white;
            font-size: 16px;
            outline: none;
        }
        
        .search-input-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-input-wrapper button {
            background: #E50914;
            border: none;
            color: white;
            padding: 15px 30px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 18px;
        }
        
        .search-input-wrapper button:hover {
            background: #8B0000;
        }
        
        .btn-clear-search {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            border-radius: 50px;
            padding: 12px 25px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-clear-search:hover {
            background: white;
            color: #E50914;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-film me-2" style="color: #E50914;"></i>
                        Eventos Disponíveis
                    </h1>
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

                <!-- NOVA BARRA DE BUSCA -->
                <div class="search-container">
                    <form method="GET">
                        <div class="d-flex align-items-center">
                            <div class="search-input-wrapper flex-grow-1">
                                <input 
                                    type="text" 
                                    name="search" 
                                    placeholder="Buscar eventos por nome, descrição ou local..." 
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >
                                <button type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if (!empty($search)): ?>
                            <a href="eventos-disponiveis.php" class="btn-clear-search">
                                <i class="fas fa-times me-2"></i>Limpar
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Grade de Eventos -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                    <?php if (count($eventos_disponiveis) > 0): ?>
                        <?php foreach ($eventos_disponiveis as $evento): 
                            $vagas_disponiveis = $evento['limite_evento'] - $evento['inscritos_count'];
                            $percentual = $evento['limite_evento'] > 0 ? ($evento['inscritos_count'] / $evento['limite_evento']) * 100 : 0;
                            
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
                            <div class="poster-card" onclick="window.location.href='detalhes-evento.php?id=<?php echo $evento['id_evento']; ?>'">
                                <!-- Badge de Status -->
                                <div class="poster-badge bg-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </div>
                                
                                <!-- Badge de Inscrito -->
                                <?php if ($evento['usuario_inscrito'] > 0): ?>
                                <div class="poster-action">
                                    <span class="badge" style="background: rgba(23, 162, 184, 0.9); backdrop-filter: blur(10px); padding: 8px 15px; border-radius: 25px;">
                                        <i class="fas fa-check-circle me-1"></i> Inscrito
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Imagem ou Placeholder -->
                                <?php if (!empty($evento['imagem_evento'])): ?>
                                    <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                         class="poster-image">
                                <?php else: ?>
                                    <div class="poster-placeholder">
                                        <i class="fas fa-calendar-alt fa-5x text-white opacity-50"></i>
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
                                        <span><?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></span>
                                    </div>
                                    
                                    <div class="poster-info">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars(substr($evento['local'], 0, 30)); ?><?php echo strlen($evento['local']) > 30 ? '...' : ''; ?></span>
                                    </div>
                                    
                                    <!-- Indicador de Vagas -->
                                    <div class="mt-3">
                                        <div class="vagas-text">
                                            <strong><?php echo $vagas_disponiveis; ?></strong> de <strong><?php echo $evento['limite_evento']; ?></strong> vagas
                                        </div>
                                        <div class="mini-progress">
                                            <div class="mini-progress-bar bg-<?php echo $status_class; ?>" style="width: <?php echo $percentual; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                                <h3 class="text-muted">
                                    <?php echo empty($search) ? 'Nenhum evento disponível no momento' : 'Nenhum evento encontrado'; ?>
                                </h3>
                                <p class="text-muted">
                                    <?php echo empty($search) ? 'Novos eventos em breve!' : 'Tente buscar com outros termos'; ?>
                                </p>
                                <?php if (!empty($search)): ?>
                                <a href="eventos-disponiveis.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-arrow-left me-2"></i>Voltar para todos os eventos
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($eventos_disponiveis) > 0): ?>
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card" style="border: none; border-radius: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                            <div class="card-body p-4">
                                <h5 class="mb-4"><i class="fas fa-chart-pie me-2" style="color: #E50914;"></i>Estatísticas</h5>
                                <?php
                                $total_eventos = count($eventos_disponiveis);
                                $eventos_com_vagas = array_filter($eventos_disponiveis, fn($e) => ($e['limite_evento'] - $e['inscritos_count']) > 0);
                                $total_vagas = array_sum(array_column($eventos_disponiveis, 'limite_evento'));
                                $total_inscritos = array_sum(array_column($eventos_disponiveis, 'inscritos_count'));
                                ?>
                                <div class="row text-center">
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="p-3">
                                            <div class="h2 mb-0" style="color: #E50914;"><?php echo $total_eventos; ?></div>
                                            <small class="text-muted">Eventos Disponíveis</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="p-3">
                                            <div class="h2 mb-0 text-success"><?php echo count($eventos_com_vagas); ?></div>
                                            <small class="text-muted">Com Vagas</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="p-3">
                                            <div class="h2 mb-0 text-primary"><?php echo $total_vagas; ?></div>
                                            <small class="text-muted">Total de Vagas</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="p-3">
                                            <div class="h2 mb-0 text-warning"><?php echo $total_inscritos; ?></div>
                                            <small class="text-muted">Inscrições Totais</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Prevenir propagação do clique nos badges
        document.querySelectorAll('.poster-badge, .poster-action').forEach(element => {
            element.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>