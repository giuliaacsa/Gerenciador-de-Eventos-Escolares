<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Verificar se o usuário é administrador
if ($_SESSION['usuario_tipo'] != 'administrador') {
    header("Location: ../dashboard.php");
    exit();
}

// Verificar se foi passado o ID do evento
if (!isset($_GET['evento']) || empty($_GET['evento'])) {
    header("Location: relatorios.php");
    exit();
}

$id_evento = $_GET['evento'];

// Buscar informações do evento
try {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id_evento = ?");
    $stmt->execute([$id_evento]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        $_SESSION['erro'] = "Evento não encontrado!";
        header("Location: relatorios.php");
        exit();
    }
    
    // Buscar estatísticas de inscrições
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_inscritos,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN status = 'inscrito' THEN 1 ELSE 0 END) as apenas_inscritos
        FROM inscricoes 
        WHERE id_evento = ?
    ");
    $stmt->execute([$id_evento]);
    $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar lista de inscritos com detalhes
    $stmt = $pdo->prepare("
        SELECT u.nome, u.email, u.cpf, u.tipo_usuario, i.data_inscricao, i.status
        FROM inscricoes i
        INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
        WHERE i.id_evento = ?
        ORDER BY u.nome ASC
    ");
    $stmt->execute([$id_evento]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular taxa de presença
    $total_inscritos = $estatisticas['total_inscritos'];
    $presentes = $estatisticas['presentes'];
    $ausentes = $estatisticas['ausentes'];
    $apenas_inscritos = $estatisticas['apenas_inscritos'];
    
    $taxa_presenca = $total_inscritos > 0 ? round(($presentes / $total_inscritos) * 100, 2) : 0;
    $vagas_disponiveis = $evento['limite_evento'] - $total_inscritos;
    $percentual_ocupacao = $evento['limite_evento'] > 0 ? 
        round(($total_inscritos / $evento['limite_evento']) * 100, 2) : 0;
    
} catch (PDOException $e) {
    $error = "Erro ao carregar relatório: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Detalhado - <?php echo htmlspecialchars($evento['nome_evento']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .event-poster {
            width: 100%;
            max-width: 300px;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 3px solid #E50914;
        }

        .event-poster-placeholder {
            width: 100%;
            max-width: 300px;
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 3px solid #E50914;
        }

        .info-section {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin-sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Relatório Detalhado do Evento</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="relatorios.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                        <a href="exportar_pdf.php?evento=<?php echo $id_evento; ?>" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Informações do Evento -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars($evento['nome_evento']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Coluna da Imagem -->
                            <div class="col-md-3 mb-4 text-center">
                                <?php if (!empty($evento['imagem_evento'])): ?>
                                    <img src="../uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                         class="event-poster">
                                <?php else: ?>
                                    <div class="event-poster-placeholder">
                                        <i class="fas fa-calendar-alt fa-5x text-white opacity-50"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Coluna das Informações -->
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($evento['descricao_evento']); ?></p>
                                        <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></p>
                                        <p><strong>Horário:</strong> <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?> às <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Local:</strong> <?php echo htmlspecialchars($evento['local']); ?></p>
                                        <p><strong>Limite de Participantes:</strong> <?php echo $evento['limite_evento']; ?></p>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $evento['status_evento'] == 'disponível' ? 'success' : 
                                                    ($evento['status_evento'] == 'esgotado' ? 'danger' : 
                                                    ($evento['status_evento'] == 'cancelado' ? 'secondary' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($evento['status_evento']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body text-center">
                                <h3><?php echo $total_inscritos; ?></h3>
                                <p class="mb-0">Total de Inscritos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h3><?php echo $presentes; ?></h3>
                                <p class="mb-0">Presentes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body text-center">
                                <h3><?php echo $ausentes; ?></h3>
                                <p class="mb-0">Ausentes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <h3><?php echo $taxa_presenca; ?>%</h3>
                                <p class="mb-0">Taxa de Presença</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <!-- Gráfico de Pizza - Status das Inscrições -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i>Status das Inscrições
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Barras - Ocupação do Evento -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-bar me-2"></i>Ocupação do Evento
                            </div>
                            <div class="card-body">
                                <canvas id="ocupacaoChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Rosca - Análise Completa -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-doughnut me-2"></i>Análise Completa do Evento
                            </div>
                            <div class="card-body">
                                <canvas id="analiseChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Inscritos -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users me-2"></i>Lista de Inscritos (<?php echo count($inscritos); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (count($inscritos) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>CPF</th>
                                            <th>Email</th>
                                            <th>Tipo</th>
                                            <th>Data Inscrição</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inscritos as $inscrito): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inscrito['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($inscrito['cpf']); ?></td>
                                            <td><?php echo htmlspecialchars($inscrito['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $inscrito['tipo_usuario'] == 'professor' ? 'warning' : 'info'; 
                                                ?>">
                                                    <?php echo ucfirst($inscrito['tipo_usuario']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($inscrito['data_inscricao'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $inscrito['status'] == 'presente' ? 'success' : 
                                                        ($inscrito['status'] == 'ausente' ? 'danger' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($inscrito['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">Nenhum inscrito neste evento ainda.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dados do PHP para JavaScript
        const presentes = <?php echo $presentes; ?>;
        const ausentes = <?php echo $ausentes; ?>;
        const apenasInscritos = <?php echo $apenas_inscritos; ?>;
        const totalInscritos = <?php echo $total_inscritos; ?>;
        const limiteEvento = <?php echo $evento['limite_evento']; ?>;
        const vagasDisponiveis = <?php echo $vagas_disponiveis; ?>;

        // Gráfico de Pizza - Status das Inscrições
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Presentes', 'Ausentes', 'Apenas Inscritos'],
                datasets: [{
                    data: [presentes, ausentes, apenasInscritos],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Gráfico de Barras - Ocupação do Evento
        const ocupacaoCtx = document.getElementById('ocupacaoChart').getContext('2d');
        const ocupacaoChart = new Chart(ocupacaoCtx, {
            type: 'bar',
            data: {
                labels: ['Inscritos', 'Vagas Disponíveis'],
                datasets: [{
                    label: 'Quantidade',
                    data: [totalInscritos, vagasDisponiveis],
                    backgroundColor: [
                        'rgba(229, 9, 20, 0.8)',
                        'rgba(108, 117, 125, 0.6)'
                    ],
                    borderColor: [
                        'rgba(229, 9, 20, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfico de Rosca - Análise Completa
        const analiseCtx = document.getElementById('analiseChart').getContext('2d');
        const analiseChart = new Chart(analiseCtx, {
            type: 'doughnut',
            data: {
                labels: ['Presentes', 'Ausentes', 'Apenas Inscritos', 'Vagas Disponíveis'],
                datasets: [{
                    data: [presentes, ausentes, apenasInscritos, vagasDisponiveis],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(108, 117, 125, 0.6)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
</body>
</html>