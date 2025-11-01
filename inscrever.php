<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_evento'])) {
    $id_evento = $_POST['id_evento'];
    $id_usuario = $_SESSION['usuario_id'];
    
    try {
        // Verificar se já está inscrito
        $stmt = $pdo->prepare("SELECT id_inscricao FROM inscricoes WHERE id_evento = ? AND id_usuario = ?");
        $stmt->execute([$id_evento, $id_usuario]);
        
        if ($stmt->rowCount() == 0) {
            // Verificar se há vagas disponíveis
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_inscritos, limite_evento 
                FROM eventos e 
                LEFT JOIN inscricoes i ON e.id_evento = i.id_evento 
                WHERE e.id_evento = ?
            ");
            $stmt->execute([$id_evento]);
            $evento_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($evento_info['total_inscritos'] < $evento_info['limite_evento']) {
                // Fazer inscrição
                $stmt = $pdo->prepare("INSERT INTO inscricoes (id_usuario, id_evento, data_inscricao, status) VALUES (?, ?, CURDATE(), 'inscrito')");
                if ($stmt->execute([$id_usuario, $id_evento])) {
                    $_SESSION['sucesso'] = "Inscrição realizada com sucesso!";
                } else {
                    $_SESSION['erro'] = "Erro ao realizar inscrição.";
                }
            } else {
                $_SESSION['erro'] = "Não há vagas disponíveis para este evento.";
            }
        } else {
            $_SESSION['erro'] = "Você já está inscrito neste evento.";
        }
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao realizar inscrição: " . $e->getMessage();
    }
    
    header("Location: eventos-disponiveis.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>