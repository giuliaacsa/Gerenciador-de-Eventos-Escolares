<?php
// includes/admin-sidebar.php - ARQUIVO CORRIGIDO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// NÃO fazer verificação de admin aqui para evitar loops de redirecionamento
// A verificação deve ser feita apenas nas páginas principais (admin/index.php, admin/eventos.php, etc.)
?>
<div class="col-md-3 col-lg-2 sidebar d-md-block">
    <div class="position-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Painel Admin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'eventos.php' ? 'active' : ''; ?>" href="eventos.php">
                    <i class="fas fa-tasks me-2"></i> Gerenciar Eventos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
                    <i class="fas fa-users me-2"></i> Gerenciar Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : ''; ?>" href="relatorios.php">
                    <i class="fas fa-chart-bar me-2"></i> Relatórios
                </a>
            </li>
            <li class="nav-divider">
                <hr class="my-3">
                <small class="text-muted px-3">USUÁRIO</small>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-home me-2"></i> Voltar ao Início
                </a>
            </li>
        </ul>
    </div>
</div>