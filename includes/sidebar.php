<?php
// includes/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="col-md-3 col-lg-2 sidebar d-md-block">
    <div class="position-sticky">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-home me-2"></i> Início
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'meus-eventos.php' ? 'active' : ''; ?>" href="meus-eventos.php">
                    <i class="fas fa-calendar-check me-2"></i> Meus Eventos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'eventos-disponiveis.php' ? 'active' : ''; ?>" href="eventos-disponiveis.php">
                    <i class="fas fa-calendar-alt me-2"></i> Eventos Disponíveis
                </a>
            </li>
            
            <?php if ($_SESSION['usuario_tipo'] == 'administrador'): ?>
            <li class="nav-divider">
                <hr class="my-3">
                <small class="text-muted px-3">ADMINISTRAÇÃO</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'admin') !== false ? 'active' : ''; ?>" href="../admin/">
                    <i class="fas fa-tachometer-alt me-2"></i> Painel Admin
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'eventos.php' ? 'active' : ''; ?>" href="../admin/eventos.php">
                    <i class="fas fa-tasks me-2"></i> Gerenciar Eventos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" href="../admin/usuarios.php">
                    <i class="fas fa-users me-2"></i> Gerenciar Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : ''; ?>" href="../admin/relatorios.php">
                    <i class="fas fa-chart-bar me-2"></i> Relatórios
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>