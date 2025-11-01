<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

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

// Verificar se o usuário é administrador
if ($_SESSION['usuario_tipo'] != 'administrador') {
    header("Location: ../dashboard.php");
    exit();
}

// Processar filtros
$filtro_evento = $_GET['evento'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Buscar eventos para o filtro
try {
    $stmt = $pdo->query("SELECT id_evento, nome_evento FROM eventos ORDER BY nome_evento ASC");
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao carregar eventos: " . $e->getMessage();
}

// Buscar relatórios
try {
    $query = "
        SELECT e.id_evento, e.nome_evento, e.data_evento, e.local,
               COUNT(i.id_inscricao) as total_inscritos,
               SUM(CASE WHEN i.status = 'presente' THEN 1 ELSE 0 END) as presentes,
               SUM(CASE WHEN i.status = 'ausente' THEN 1 ELSE 0 END) as ausentes
        FROM eventos e
        LEFT JOIN inscricoes i ON e.id_evento = i.id_evento
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filtro_evento)) {
        $query .= " AND e.id_evento = ?";
        $params[] = $filtro_evento;
    }
    
    if (!empty($filtro_data_inicio)) {
        $query .= " AND e.data_evento >= ?";
        $params[] = $filtro_data_inicio;
    }
    
    if (!empty($filtro_data_fim)) {
        $query .= " AND e.data_evento <= ?";
        $params[] = $filtro_data_fim;
    }
    
    $query .= " GROUP BY e.id_evento ORDER BY e.data_evento DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $relatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao gerar relatório: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Eventos Escolares</title>
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
                    <h1 class="h2">Relatórios e Estatísticas</h1>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">Filtros</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="evento" class="form-label">Evento</label>
                                <select class="form-select" id="evento" name="evento">
                                    <option value="">Todos os eventos</option>
                                    <?php foreach ($eventos as $evento): ?>
                                    <option value="<?php echo $evento['id_evento']; ?>" <?php echo $filtro_evento == $evento['id_evento'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($evento['nome_evento']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
        <label for="data_inicio" class="form-label">Data Início</label>
        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $filtro_data_inicio; ?>">
    </div>
    <div class="col-md-3">
        <label for="data_fim" class="form-label">Data Fim</label>
        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $filtro_data_fim; ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100 mt-4" style="margin-top: 35px !important;">Filtrar</button>
    </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Relatório de Eventos</div>
                    <div class="card-body">
                        <?php if (count($relatorios) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Evento</th>
                                            <th>Data</th>
                                            <th>Local</th>
                                            <th>Inscritos</th>
                                            <th>Presentes</th>
                                            <th>Ausentes</th>
                                            <th>Taxa de Presença</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($relatorios as $relatorio): 
                                            $taxa_presenca = $relatorio['total_inscritos'] > 0 ? 
                                                round(($relatorio['presentes'] / $relatorio['total_inscritos']) * 100, 2) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($relatorio['nome_evento']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($relatorio['data_evento'])); ?></td>
                                            <td><?php echo htmlspecialchars($relatorio['local']); ?></td>
                                            <td><?php echo $relatorio['total_inscritos']; ?></td>
                                            <td><?php echo $relatorio['presentes']; ?></td>
                                            <td><?php echo $relatorio['ausentes']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $taxa_presenca >= 70 ? 'success' : ($taxa_presenca >= 50 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $taxa_presenca; ?>%
                                                </span>
                                            </td>
                                            <td>
    <div class="btn-group" role="group">
        <a href="relatorio_detalhado.php?evento=<?php echo $relatorio['id_evento']; ?>" class="btn btn-sm btn-info">
            <i class="fas fa-chart-bar me-1"></i>Detalhes
        </a>
        <a href="exportar_pdf.php?evento=<?php echo $relatorio['id_evento']; ?>" class="btn btn-sm btn-danger">
            <i class="fas fa-file-pdf me-1"></i>PDF
        </a>
    </div>
</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Nenhum dado encontrado com os filtros aplicados.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>