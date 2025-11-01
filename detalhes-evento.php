<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

// Verificar se foi passado o ID do evento
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: eventos-disponiveis.php");
    exit();
}

$id_evento = $_GET['id'];
$id_usuario = $_SESSION['usuario_id'];

// Buscar informações do evento
try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COUNT(i.id_inscricao) as total_inscritos,
               (SELECT COUNT(*) FROM inscricoes WHERE id_evento = e.id_evento AND id_usuario = ?) as usuario_inscrito,
               (SELECT id_inscricao FROM inscricoes WHERE id_evento = e.id_evento AND id_usuario = ?) as id_inscricao_usuario
        FROM eventos e 
        LEFT JOIN inscricoes i ON e.id_evento = i.id_evento 
        WHERE e.id_evento = ?
        GROUP BY e.id_evento
    ");
    $stmt->execute([$id_usuario, $id_usuario, $id_evento]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        $_SESSION['erro'] = "Evento não encontrado!";
        header("Location: eventos-disponiveis.php");
        exit();
    }
    
    // Calcular vagas disponíveis
    $vagas_disponiveis = $evento['limite_evento'] - $evento['total_inscritos'];
    $percentual_ocupacao = $evento['limite_evento'] > 0 ? 
        round(($evento['total_inscritos'] / $evento['limite_evento']) * 100, 2) : 0;
    
} catch (PDOException $e) {
    $error = "Erro ao carregar evento: " . $e->getMessage();
}

// Processar inscrição
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inscrever'])) {
    try {
        // Verificar se já está inscrito
        $stmt = $pdo->prepare("SELECT id_inscricao FROM inscricoes WHERE id_evento = ? AND id_usuario = ?");
        $stmt->execute([$id_evento, $id_usuario]);
        
        if ($stmt->rowCount() == 0 && $vagas_disponiveis > 0) {
            $stmt = $pdo->prepare("INSERT INTO inscricoes (id_usuario, id_evento, data_inscricao, status) VALUES (?, ?, CURDATE(), 'inscrito')");
            if ($stmt->execute([$id_usuario, $id_evento])) {
                $_SESSION['sucesso'] = "Inscrição realizada com sucesso!";
                header("Location: meus-eventos.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        $error = "Erro ao realizar inscrição: " . $e->getMessage();
    }
}

// Processar cancelamento de inscrição
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancelar_inscricao'])) {
    $id_inscricao = $_POST['id_inscricao'];
    
    try {
        // Verificar se a inscrição pertence ao usuário
        $stmt = $pdo->prepare("SELECT id_inscricao FROM inscricoes WHERE id_inscricao = ? AND id_usuario = ?");
        $stmt->execute([$id_inscricao, $id_usuario]);
        
        if ($stmt->rowCount() > 0) {
            // Verificar se o evento ainda não ocorreu
            $data_evento = new DateTime($evento['data_evento']);
            $hoje = new DateTime();
            
            if ($data_evento > $hoje) {
                $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_inscricao = ? AND id_usuario = ?");
                if ($stmt->execute([$id_inscricao, $id_usuario])) {
                    $_SESSION['sucesso'] = "Inscrição cancelada com sucesso!";
                    header("Location: eventos-disponiveis.php");
                    exit();
                }
            } else {
                $_SESSION['erro'] = "Não é possível cancelar inscrição de evento que já ocorreu.";
            }
        } else {
            $_SESSION['erro'] = "Inscrição não encontrada.";
        }
    } catch (PDOException $e) {
        $error = "Erro ao cancelar inscrição: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($evento['nome_evento']); ?> - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .card-header {
            font-size: 1.1rem;
        }
        
        .img-fluid {
            max-width: 100%;
            height: auto;
        }
        
        p {
            margin-bottom: 1rem;
        }
        
        p strong {
            color: #333;
            margin-right: 5px;
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
                    <h1 class="h2">Detalhes do Evento</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['erro'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

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

                <!-- Card Principal -->
                <div class="card">
                    <div class="card-header" style="background-color: #E50914; color: white;">
                        <h5 class="mb-0">Detalhes do Evento: <?php echo htmlspecialchars($evento['nome_evento']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Coluna da Imagem -->
                            <div class="col-md-4 mb-4">
                                <?php if (!empty($evento['imagem_evento'])): ?>
                                    <img src="uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                         class="img-fluid rounded shadow">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded" 
                                         style="height: 400px;">
                                        <i class="fas fa-calendar-alt fa-5x opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Coluna das Informações -->
                            <div class="col-md-8">
                                <h5 class="mb-3">Informações do Evento</h5>
                                
                                <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></p>
                                
                                <p><strong>Horário:</strong> <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?> às <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></p>
                                
                                <p><strong>Local:</strong> <?php echo htmlspecialchars($evento['local']); ?></p>
                                
                                <p><strong>Limite:</strong> <?php echo $evento['limite_evento']; ?> participantes</p>
                                
                                <p><strong>Vagas Disponíveis:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $vagas_disponiveis > 5 ? 'success' : 
                                            ($vagas_disponiveis > 0 ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo $vagas_disponiveis; ?> de <?php echo $evento['limite_evento']; ?>
                                    </span>
                                </p>
                                
                                <p><strong>Status:</strong> 
                                    <?php if ($evento['usuario_inscrito'] > 0): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-check-circle me-1"></i>Você está inscrito
                                        </span>
                                    <?php elseif ($vagas_disponiveis <= 0): ?>
                                        <span class="badge bg-danger">Esgotado</span>
                                    <?php elseif ($vagas_disponiveis <= 5): ?>
                                        <span class="badge bg-warning text-dark">Últimas vagas</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Disponível</span>
                                    <?php endif; ?>
                                </p>

                                <hr class="my-4">

                                <h5 class="mb-3">Descrição</h5>
                                <p style="text-align: justify; line-height: 1.8;">
                                    <?php echo nl2br(htmlspecialchars($evento['descricao_evento'])); ?>
                                </p>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Botões de Ação -->
                        <div class="text-center">
                            <?php if ($evento['usuario_inscrito']): ?>
                                <?php
                                // Verificar se pode cancelar (evento ainda não ocorreu)
                                $data_evento = new DateTime($evento['data_evento']);
                                $hoje = new DateTime();
                                $pode_cancelar = $data_evento > $hoje;
                                ?>
                                
                                <span class="badge bg-info me-2" style="font-size: 1rem; padding: 10px 20px;">
                                    <i class="fas fa-check-circle me-2"></i>Você está inscrito neste evento
                                </span>
                                
                                <?php if ($pode_cancelar): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja cancelar sua inscrição neste evento?');">
                                    <input type="hidden" name="id_inscricao" value="<?php echo $evento['id_inscricao_usuario']; ?>">
                                    <button type="submit" name="cancelar_inscricao" class="btn btn-danger">
                                        <i class="fas fa-times me-2"></i>Cancelar Inscrição
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-clock me-2"></i>Evento já realizado
                                </button>
                                <?php endif; ?>
                                
                                <a href="meus-eventos.php" class="btn btn-outline-primary">
                                    <i class="fas fa-list me-2"></i>Ver Minhas Inscrições
                                </a>
                            <?php elseif ($vagas_disponiveis <= 0): ?>
                                <button class="btn btn-danger" disabled>
                                    <i class="fas fa-times me-2"></i>Evento Esgotado
                                </button>
                            <?php else: ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Deseja confirmar sua inscrição neste evento?');">
                                    <button type="submit" name="inscrever" class="btn btn-primary">
                                        <i class="fas fa-ticket-alt me-2"></i>Inscrever-se Agora
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="javascript:history.back()" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Voltar
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>