<?php
$host = 'localhost';
$dbname = 'bd_eventosescolares';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    error_log("Conexão com banco de dados estabelecida com sucesso");
} catch (PDOException $e) {
    error_log("Erro na conexão: " . $e->getMessage());
    die("Erro na conexão com o banco de dados. Verifique o arquivo config.php");
}
?>