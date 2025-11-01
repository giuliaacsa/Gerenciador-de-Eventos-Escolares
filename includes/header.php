<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: /EventosEscolares/index.html");
    exit();
}

$is_admin_area = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;

// Definir base path para assets
$base_path = '/EventosEscolares';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos Escolares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <!-- Botão Hamburger para Mobile -->
            <button class="btn btn-dark d-md-none me-2" id="sidebarToggle" type="button">
                <i class="fas fa-bars"></i>
            </button>
            
            <a class="navbar-brand" href="<?php echo $_SESSION['usuario_tipo'] == 'administrador' ? ($is_admin_area ? $base_path . '/admin/index.php' : $base_path . '/admin/index.php') : $base_path . '/dashboard.php'; ?>">
                <i class="fas fa-calendar-day me-2"></i>Eventos Escolares
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link text-white">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                            <small class="badge bg-<?php echo $_SESSION['usuario_tipo'] == 'administrador' ? 'danger' : ($_SESSION['usuario_tipo'] == 'professor' ? 'warning' : 'info'); ?> ms-1">
                                <?php echo ucfirst($_SESSION['usuario_tipo']); ?>
                            </small>
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="fas fa-user-edit me-2"></i>Meu Perfil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Sair
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Overlay para fechar sidebar em mobile -->
    <div class="sidebar-overlay d-md-none" id="sidebarOverlay"></div>