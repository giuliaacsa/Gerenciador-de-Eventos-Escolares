<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado - CORRIGIDO
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../index.html');
    exit;
}


// Função para verificar se o usuário é administrador
function isAdmin() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'administrador';
}

// Função para verificar se o usuário é professor
function isProfessor() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'professor';
}

// Função para verificar se o usuário é aluno
function isAluno() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'aluno';
}
?>