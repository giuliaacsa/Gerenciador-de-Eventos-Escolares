<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_inscricao'])) {
    $id_inscricao = $_POST['id_inscricao'];
    $id_usuario = $_SESSION['usuario_id'];
    
    try {
        // Verificar se a inscrição pertence ao usuário
        $stmt = $pdo->prepare("SELECT id_inscricao FROM inscricoes WHERE id_inscricao = ? AND id_usuario = ?");
        $stmt->execute([$id_inscricao, $id_usuario]);
        
        if ($stmt->rowCount() > 0) {
            // Cancelar a inscrição
            $stmt = $pdo->prepare("DELETE FROM inscricoes WHERE id_inscricao = ?");
            if ($stmt->execute([$id_inscricao])) {
                $_SESSION['sucesso'] = "Inscrição cancelada com sucesso!";
            } else {
                $_SESSION['erro'] = "Erro ao cancelar inscrição.";
            }
        } else {
            $_SESSION['erro'] = "Inscrição não encontrada.";
        }
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao cancelar inscrição: " . $e->getMessage();
    }
    
    header("Location: meus-eventos.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>