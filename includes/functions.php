<?php
/**
 * Funções auxiliares para o sistema de Eventos Escolares
 */

/**
 * Validar CPF
 */
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula e verifica os dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

/**
 * Formatar CPF para exibição
 */
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

/**
 * Validar e-mail
 */
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Gerar senha hash
 */
function gerarHashSenha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

/**
 * Verificar senha
 */
function verificarSenha($senha, $hash) {
    return password_verify($senha, $hash);
}

/**
 * Redirecionar com mensagem de flash
 */
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    header("Location: $url");
    exit();
}

/**
 * Exibir mensagem flash
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        echo '<div class="alert alert-'.$message['type'].' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['flash_message']);
    }
}

/**
 * Validar data
 */
function validarData($data, $formato = 'Y-m-d') {
    $d = DateTime::createFromFormat($formato, $data);
    return $d && $d->format($formato) === $data;
}

/**
 * Validar horário
 */
function validarHorario($horario) {
    return preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $horario);
}

/**
 * Calcular idade a partir da data de nascimento
 */
function calcularIdade($dataNascimento) {
    $nascimento = new DateTime($dataNascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($nascimento);
    return $idade->y;
}

/**
 * Gerar código aleatório
 */
function gerarCodigo($tamanho = 8) {
    $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

/**
 * Formatar data para exibição
 */
function formatarData($data, $formato = 'd/m/Y') {
    if (empty($data) || $data == '0000-00-00') {
        return '';
    }
    return date($formato, strtotime($data));
}

/**
 * Formatar datetime para exibição
 */
function formatarDateTime($datetime, $formato = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '';
    }
    return date($formato, strtotime($datetime));
}

/**
 * Sanitizar entrada de dados
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar se uma string contém apenas letras e espaços
 */
function contemApenasLetras($string) {
    return preg_match('/^[a-zA-ZáàâãéêíóôõúçÁÀÂÃÉÊÍÓÔÕÚÇ\s]+$/', $string);
}

/**
 * Verificar força da senha
 */
function verificarForcaSenha($senha) {
    $forca = 0;
    $comprimento = strlen($senha);
    
    if ($comprimento >= 8) $forca += 1;
    if (preg_match('/[A-Z]/', $senha)) $forca += 1;
    if (preg_match('/[a-z]/', $senha)) $forca += 1;
    if (preg_match('/[0-9]/', $senha)) $forca += 1;
    if (preg_match('/[^A-Za-z0-9]/', $senha)) $forca += 1;
    
    return $forca;
}

/**
 * Gerar token CSRF
 */
function gerarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}