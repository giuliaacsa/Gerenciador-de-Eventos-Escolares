<?php
// includes/footer.php
?>
    </div> <!-- Fim do container-fluid -->
    
   <!-- Modal de Perfil -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">Meu Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php 
                // Detectar se está na pasta admin
                $is_admin_area = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
                $form_action = $is_admin_area ? 'atualizar_perfil.php' : 'atualizar_perfil.php';
                $foto_path = $is_admin_area ? '../uploads/perfil/' : 'uploads/perfil/';
                $delete_action = $is_admin_area ? 'excluir_perfil.php' : 'excluir_perfil.php';
                ?>
                <form id="profileForm" action="<?php echo $form_action; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="foto_atual" value="<?php echo $_SESSION['foto_perfil'] ?? ''; ?>">
                    
                    <div class="row">
                        <!-- Coluna da Foto -->
                        <div class="col-md-4 text-center mb-3">
                            <div class="profile-photo-container mb-3">
                                <?php if (!empty($_SESSION['foto_perfil'])): ?>
                                    <img src="<?php echo $foto_path . htmlspecialchars($_SESSION['foto_perfil']); ?>" 
                                         id="previewFoto" 
                                         class="img-fluid rounded-circle" 
                                         style="width: 200px; height: 200px; object-fit: cover; border: 4px solid #E50914;">
                                <?php else: ?>
                                    <div id="previewFoto" 
                                         class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                         style="width: 200px; height: 200px; margin: 0 auto; border: 4px solid #E50914;">
                                        <i class="fas fa-user fa-5x text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto_perfil" class="btn btn-primary btn-sm">
                                    <i class="fas fa-camera me-2"></i>Escolher Foto
                                </label>
                                <input type="file" class="d-none" id="foto_perfil" name="foto_perfil" accept="image/*" onchange="previewImage(this)">
                                <p class="text-muted small mt-2">JPG, PNG, GIF ou WEBP<br>Máximo 2MB</p>
                            </div>
                        </div>
                        
                        <!-- Coluna dos Campos -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="profileNome" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="profileNome" name="nome" 
                                       value="<?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="profileEmail" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="profileEmail" name="email" 
                                       value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="profileGenero" class="form-label">Gênero</label>
                                <select class="form-select" id="profileGenero" name="genero">
                                    <option value="masculino" <?php echo ($_SESSION['genero'] ?? '') == 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="feminino" <?php echo ($_SESSION['genero'] ?? '') == 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="outro" <?php echo ($_SESSION['genero'] ?? '') == 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    <option value="nao_informar" <?php echo ($_SESSION['genero'] ?? 'nao_informar') == 'nao_informar' ? 'selected' : ''; ?>>Prefiro não informar</option>
                                </select>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Alterar Senha (opcional)</h6>
                            
                            <div class="mb-3">
                                <label for="profileSenha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="profileSenha" name="senha" 
                                       placeholder="Deixe em branco para não alterar">
                            </div>
                            
                            <div class="mb-3">
                                <label for="profileConfirmarSenha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="profileConfirmarSenha" name="confirmar_senha">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Salvar Alterações
                        </button>
                        
                        <!-- Botão para abrir modal de confirmação de exclusão -->
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProfileModal">
                            <i class="fas fa-trash-alt me-2"></i>Excluir Minha Conta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="deleteProfileModal" tabindex="-1" aria-labelledby="deleteProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProfileModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6 class="alert-heading">Atenção!</h6>
                    <p class="mb-2">Você está prestes a excluir permanentemente sua conta. Esta ação:</p>
                    <ul class="mb-2">
                        <li>Removerá todos os seus dados pessoais</li>
                        <li>Cancelará todas as suas inscrições em eventos</li>
                        <li>Não poderá ser desfeita</li>
                    </ul>
                    <p class="mb-0"><strong>Esta ação é irreversível!</strong></p>
                </div>
                
                <div class="mb-3">
                    <label for="confirmDelete" class="form-label">
                        Digite <strong>EXCLUIR</strong> para confirmar:
                    </label>
                    <input type="text" class="form-control" id="confirmDelete" placeholder="Digite EXCLUIR aqui">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled onclick="deleteProfile()">
                    <i class="fas fa-trash-alt me-2"></i>Excluir Minha Conta
                </button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview da imagem
        function previewImage(input) {
            const preview = document.getElementById('previewFoto');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // Substituir o placeholder por uma imagem real
                        const newImg = document.createElement('img');
                        newImg.id = 'previewFoto';
                        newImg.src = e.target.result;
                        newImg.className = 'img-fluid rounded-circle';
                        newImg.style.cssText = 'width: 200px; height: 200px; object-fit: cover; border: 4px solid #E50914;';
                        preview.parentNode.replaceChild(newImg, preview);
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Validação do formulário de perfil
        document.getElementById('profileForm')?.addEventListener('submit', function(e) {
            const senha = document.getElementById('profileSenha').value;
            const confirmarSenha = document.getElementById('profileConfirmarSenha').value;
            
            if (senha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
            }
        });

        // Destacar link ativo na sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href').split('/').pop();
                if (linkPage === currentPage || 
                    (currentPage === '' && linkPage === 'dashboard.php') ||
                    (currentPage === 'index.php' && linkPage === '')) {
                    link.classList.add('active');
                }
            });
        });
    </script>

<script>
// ... código existente ...

// ========== TOGGLE SIDEBAR MOBILE ==========
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (sidebarToggle && sidebar) {
        // Abrir/Fechar sidebar ao clicar no botão
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('show');
            }
        });
        
        // Fechar sidebar ao clicar no overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        }
        
        // Fechar sidebar ao clicar em um link (em mobile)
        if (window.innerWidth <= 768) {
            const sidebarLinks = sidebar.querySelectorAll('.nav-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });
        }
        
        // Fechar sidebar ao redimensionar para desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
            }
        });
    }
});

// Validação do campo de confirmação de exclusão
document.getElementById('confirmDelete')?.addEventListener('input', function(e) {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const confirmText = e.target.value.trim().toUpperCase();
    
    confirmBtn.disabled = confirmText !== 'EXCLUIR';
});

// Função para excluir perfil
function deleteProfile() {
    const confirmText = document.getElementById('confirmDelete').value.trim().toUpperCase();
    
    if (confirmText === 'EXCLUIR') {
        // Redirecionar para a página de exclusão
        const isAdminArea = window.location.pathname.includes('/admin/');
        const deleteUrl = isAdminArea ? 'excluir_perfil.php' : 'excluir_perfil.php';
        
        window.location.href = deleteUrl;
    }
}
</script>
</body>
</html>