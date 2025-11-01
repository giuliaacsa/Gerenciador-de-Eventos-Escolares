<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';

// Função para atualizar status dos eventos automaticamente
function atualizarStatusEventos($pdo) {
    try {
        // Atualizar para EXPIRADO - eventos que já passaram da data
        $stmt = $pdo->prepare("
            UPDATE eventos 
            SET status_evento = 'expirado' 
            WHERE data_evento < CURDATE() 
            AND status_evento != 'cancelado'
            AND status_evento != 'expirado'
        ");
        $stmt->execute();
        
        // Atualizar para ESGOTADO - eventos que atingiram o limite
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
        
        // Atualizar para DISPONÍVEL - eventos com vagas E data futura
        // INCLUINDO eventos que eram "expirado" mas a data foi alterada
        $stmt = $pdo->prepare("
            UPDATE eventos e
            LEFT JOIN (
                SELECT id_evento, COUNT(*) as total_inscritos
                FROM inscricoes
                GROUP BY id_evento
            ) i ON e.id_evento = i.id_evento
            SET e.status_evento = 'disponível'
            WHERE (i.total_inscritos < e.limite_evento OR i.total_inscritos IS NULL)
            AND (e.status_evento = 'esgotado' OR e.status_evento = 'expirado')
            AND e.data_evento >= CURDATE()
            AND e.status_evento != 'cancelado'
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

// Processar ações
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Listar eventos
if ($action == 'list') {
    try {
        $stmt = $pdo->query("
            SELECT e.*, COUNT(i.id_inscricao) as inscritos 
            FROM eventos e 
            LEFT JOIN inscricoes i ON e.id_evento = i.id_evento 
            GROUP BY e.id_evento 
            ORDER BY e.data_evento DESC
        ");
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Erro ao carregar eventos: " . $e->getMessage();
    }
}

// Adicionar/editar evento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome_evento'];
    $descricao = $_POST['descricao_evento'];
    $data = $_POST['data_evento'];
    $local = $_POST['local'];
    $limite = $_POST['limite_evento'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $status = $_POST['status_evento'];
    $imagem_atual = $_POST['imagem_atual'] ?? '';
    
    // Processar upload de imagem
    $imagem_nome = $imagem_atual;
    
    if (isset($_FILES['imagem_evento']) && $_FILES['imagem_evento']['error'] == 0) {
        $arquivo = $_FILES['imagem_evento'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extensao, $extensoes_permitidas)) {
            // Verificar tamanho (máximo 5MB)
            if ($arquivo['size'] <= 5242880) {
                // Criar pasta se não existir
                $pasta_upload = '../uploads/eventos/';
                if (!file_exists($pasta_upload)) {
                    mkdir($pasta_upload, 0755, true);
                }
                
                // Gerar nome único para o arquivo
                $imagem_nome = uniqid() . '_' . time() . '.' . $extensao;
                $caminho_completo = $pasta_upload . $imagem_nome;
                
                if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
                    // Se havia imagem anterior, deletar
                    if (!empty($imagem_atual) && file_exists($pasta_upload . $imagem_atual)) {
                        unlink($pasta_upload . $imagem_atual);
                    }
                } else {
                    $error = "Erro ao fazer upload da imagem.";
                    $imagem_nome = $imagem_atual;
                }
            } else {
                $error = "A imagem deve ter no máximo 5MB.";
            }
        } else {
            $error = "Formato de imagem não permitido. Use: JPG, PNG, GIF ou WEBP.";
        }
    }
    
    if (!isset($error)) {
        try {
            if (empty($_POST['id_evento'])) {
                // Novo evento
                $stmt = $pdo->prepare("
                    INSERT INTO eventos (nome_evento, descricao_evento, data_evento, local, limite_evento, hora_inicio, hora_fim, status_evento, id_usuario, imagem_evento)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $descricao, $data, $local, $limite, $hora_inicio, $hora_fim, $status, $_SESSION['usuario_id'], $imagem_nome]);
                $success = "Evento criado com sucesso!";
            } else {
                // Editar evento
                $stmt = $pdo->prepare("
                    UPDATE eventos 
                    SET nome_evento = ?, descricao_evento = ?, data_evento = ?, local = ?, limite_evento = ?, hora_inicio = ?, hora_fim = ?, status_evento = ?, imagem_evento = ?
                    WHERE id_evento = ?
                ");
                $stmt->execute([$nome, $descricao, $data, $local, $limite, $hora_inicio, $hora_fim, $status, $imagem_nome, $_POST['id_evento']]);
                $success = "Evento atualizado com sucesso!";
            }
        } catch (PDOException $e) {
            $error = "Erro ao salvar evento: " . $e->getMessage();
        }
    }
}

// Deletar evento
if ($action == 'delete' && $id) {
    try {
        // Buscar imagem antes de deletar
        $stmt = $pdo->prepare("SELECT imagem_evento FROM eventos WHERE id_evento = ?");
        $stmt->execute([$id]);
        $evento_deletar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Primeiro, deletar todas as inscrições deste evento
        $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_evento = ?");
        $stmt->execute([$id]);
        
        // Depois, deletar o evento
        $stmt = $pdo->prepare("DELETE FROM eventos WHERE id_evento = ?");
        $stmt->execute([$id]);
        
        // Deletar imagem se existir
        if (!empty($evento_deletar['imagem_evento'])) {
            $caminho_imagem = '../uploads/eventos/' . $evento_deletar['imagem_evento'];
            if (file_exists($caminho_imagem)) {
                unlink($caminho_imagem);
            }
        }
        
        $_SESSION['sucesso'] = "Evento e suas inscrições excluídos com sucesso!";
        header("Location: eventos.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir evento: " . $e->getMessage();
        header("Location: eventos.php");
        exit();
    }
}

// Buscar evento para edição
if (($action == 'edit' || $action == 'view') && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id_evento = ?");
        $stmt->execute([$id]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$evento) {
            $error = "Evento não encontrado!";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = "Erro ao carregar evento: " . $e->getMessage();
        $action = 'list';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Eventos - Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .event-image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .event-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .image-upload-area:hover {
            border-color: #E50914;
            background-color: #f8f9fa;
        }
        .image-upload-area i {
            font-size: 3rem;
            color: #E50914;
            margin-bottom: 10px;
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
                    <h1 class="h2">Gerenciar Eventos</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="eventos.php?action=add" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i>Adicionar Evento
                        </a>
                    </div>
                </div>

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
                                <th>Imagem</th>
                                <th>Evento</th>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Inscrições</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($eventos as $evento): 
    $vagas_disponiveis = $evento['limite_evento'] - $evento['inscritos'];
    $percentual = $evento['limite_evento'] > 0 ? ($evento['inscritos'] / $evento['limite_evento']) * 100 : 0;
    
    // Determinar cor baseada no STATUS DO BANCO, não em cálculos
    switch($evento['status_evento']) {
        case 'disponível':
            $status_class = 'success';
            break;
        case 'esgotado':
            $status_class = 'danger';
            break;
        case 'expirado':
            $status_class = 'warning';
            break;
        case 'cancelado':
            $status_class = 'secondary';
            break;
        default:
            $status_class = 'info';
    }
?>
                            <tr>
                                <td>
                                    <?php if (!empty($evento['imagem_evento'])): ?>
                                        <img src="../uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                             alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                             class="event-thumbnail">
                                    <?php else: ?>
                                        <div class="event-thumbnail bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($evento['nome_evento']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></td>
                                <td><?php echo htmlspecialchars($evento['local']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="me-2"><?php echo $evento['inscritos']; ?>/<?php echo $evento['limite_evento']; ?></span>
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                 style="width: <?php echo $percentual; ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($evento['status_evento']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="eventos.php?action=edit&id=<?php echo $evento['id_evento']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                    <a href="eventos.php?action=view&id=<?php echo $evento['id_evento']; ?>" class="btn btn-sm btn-info">Ver</a>
                                    <a href="eventos.php?action=delete&id=<?php echo $evento['id_evento']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Tem certeza que deseja excluir este evento?')">Excluir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($action == 'add' || $action == 'edit'): ?>
                <div class="card">
                    <div class="card-header">
                        <?php echo $action == 'add' ? 'Adicionar Evento' : 'Editar Evento'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_evento" value="<?php echo $evento['id_evento'] ?? ''; ?>">
                            <input type="hidden" name="imagem_atual" value="<?php echo $evento['imagem_evento'] ?? ''; ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nome_evento" class="form-label">Nome do Evento</label>
                                                <input type="text" class="form-control" id="nome_evento" name="nome_evento" 
                                                       value="<?php echo $evento['nome_evento'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="data_evento" class="form-label">Data do Evento</label>
                                                <input type="date" class="form-control" id="data_evento" name="data_evento" 
                                                       value="<?php echo $evento['data_evento'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="hora_inicio" class="form-label">Hora de Início</label>
                                                <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" 
                                                       value="<?php echo $evento['hora_inicio'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="hora_fim" class="form-label">Hora de Término</label>
                                                <input type="time" class="form-control" id="hora_fim" name="hora_fim" 
                                                       value="<?php echo $evento['hora_fim'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="local" class="form-label">Local</label>
                                        <input type="text" class="form-control" id="local" name="local" 
                                               value="<?php echo $evento['local'] ?? ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="descricao_evento" class="form-label">Descrição</label>
                                        <textarea class="form-control" id="descricao_evento" name="descricao_evento" rows="3" required><?php echo $evento['descricao_evento'] ?? ''; ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="limite_evento" class="form-label">Limite de Participantes</label>
                                                <input type="number" class="form-control" id="limite_evento" name="limite_evento" 
                                                       value="<?php echo $evento['limite_evento'] ?? ''; ?>" min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status_evento" class="form-label">Status</label>
                                                <select class="form-select" id="status_evento" name="status_evento" required>
                                                    <option value="disponível" <?php echo ($evento['status_evento'] ?? '') == 'disponível' ? 'selected' : ''; ?>>Disponível</option>
                                                    <option value="esgotado" <?php echo ($evento['status_evento'] ?? '') == 'esgotado' ? 'selected' : ''; ?>>Esgotado</option>
                                                    <option value="expirado" <?php echo ($evento['status_evento'] ?? '') == 'expirado' ? 'selected' : ''; ?>>Expirado</option>
                                                    <option value="cancelado" <?php echo ($evento['status_evento'] ?? '') == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Imagem do Evento</label>
                                        <div class="image-upload-area" onclick="document.getElementById('imagem_evento').click()">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p class="mb-0">Clique para fazer upload</p>
                                            <small class="text-muted">JPG, PNG, GIF ou WEBP (max 5MB)</small>
                                        </div>
                                        <input type="file" class="d-none" id="imagem_evento" name="imagem_evento" accept="image/*" onchange="previewImage(this)">
                                        
                                        <?php if (!empty($evento['imagem_evento'])): ?>
                                            <img src="../uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                                 id="preview" class="event-image-preview" alt="Preview">
                                        <?php else: ?>
                                            <img id="preview" class="event-image-preview d-none" alt="Preview">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Salvar</button>
                            <a href="eventos.php" class="btn btn-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
                <?php elseif ($action == 'view'): ?>
                <div class="card">
                    <div class="card-header">
                        Detalhes do Evento: <?php echo htmlspecialchars($evento['nome_evento']); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <?php if (!empty($evento['imagem_evento'])): ?>
                                    <img src="../uploads/eventos/<?php echo htmlspecialchars($evento['imagem_evento']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['nome_evento']); ?>"
                                         class="img-fluid rounded">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                         style="height: 300px; border-radius: 8px;">
                                        <i class="fas fa-image fa-5x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <h5>Informações do Evento</h5>
                                <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_evento'])); ?></p>
                                <p><strong>Horário:</strong> <?php echo $evento['hora_inicio']; ?> às <?php echo $evento['hora_fim']; ?></p>
                                <p><strong>Local:</strong> <?php echo htmlspecialchars($evento['local']); ?></p>
                                <p><strong>Limite:</strong> <?php echo $evento['limite_evento']; ?> participantes</p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $evento['status_evento'] == 'disponível' ? 'success' : 
                                            ($evento['status_evento'] == 'esgotado' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($evento['status_evento']); ?>
                                    </span>
                                </p>
                                <h5 class="mt-4">Descrição</h5>
                                <p><?php echo nl2br(htmlspecialchars($evento['descricao_evento'])); ?></p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="eventos.php?action=edit&id=<?php echo $evento['id_evento']; ?>" class="btn btn-primary">Editar</a>
                            <a href="eventos.php" class="btn btn-secondary">Voltar</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>