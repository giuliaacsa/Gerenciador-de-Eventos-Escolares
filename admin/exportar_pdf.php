<?php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar se é administrador
if ($_SESSION['usuario_tipo'] != 'administrador') {
    header("Location: ../dashboard.php");
    exit();
}

// Verificar se foi passado o ID do evento
if (!isset($_GET['evento']) || empty($_GET['evento'])) {
    header("Location: relatorios.php");
    exit();
}

$id_evento = $_GET['evento'];

// Buscar informações do evento
try {
    $stmt = $pdo->prepare("SELECT * FROM eventos WHERE id_evento = ?");
    $stmt->execute([$id_evento]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        $_SESSION['erro'] = "Evento não encontrado!";
        header("Location: relatorios.php");
        exit();
    }
    
    // Buscar estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_inscritos,
            SUM(CASE WHEN status = 'presente' THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) as ausentes,
            SUM(CASE WHEN status = 'inscrito' THEN 1 ELSE 0 END) as apenas_inscritos
        FROM inscricoes 
        WHERE id_evento = ?
    ");
    $stmt->execute([$id_evento]);
    $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar lista de inscritos
    $stmt = $pdo->prepare("
        SELECT u.nome, u.email, u.cpf, u.tipo_usuario, i.data_inscricao, i.status
        FROM inscricoes i
        INNER JOIN usuarios u ON i.id_usuario = u.id_usuario
        WHERE i.id_evento = ?
        ORDER BY u.nome ASC
    ");
    $stmt->execute([$id_evento]);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estatísticas
    $total = $estatisticas['total_inscritos'];
    $presentes = $estatisticas['presentes'];
    $ausentes = $estatisticas['ausentes'];
    $apenas_inscritos = $estatisticas['apenas_inscritos'];
    $taxa_presenca = $total > 0 ? round(($presentes / $total) * 100, 2) : 0;
    $vagas_disponiveis = $evento['limite_evento'] - $total;
    
} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// Processar imagem do evento para base64
$imagem_base64 = '';
if (!empty($evento['imagem_evento'])) {
    $caminho_imagem = '../uploads/eventos/' . $evento['imagem_evento'];
    if (file_exists($caminho_imagem)) {
        $type = pathinfo($caminho_imagem, PATHINFO_EXTENSION);
        $data = file_get_contents($caminho_imagem);
        $imagem_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

// Função para gerar gráfico de pizza em base64
function gerarGraficoPizza($dados, $labels, $cores, $largura = 400, $altura = 300) {
    $imagem = imagecreatetruecolor($largura, $altura);
    
    // Cores de fundo
    $branco = imagecolorallocate($imagem, 255, 255, 255);
    imagefill($imagem, 0, 0, $branco);
    
    // Converter cores hex para RGB
    $coresRGB = [];
    foreach ($cores as $cor) {
        $r = hexdec(substr($cor, 1, 2));
        $g = hexdec(substr($cor, 3, 2));
        $b = hexdec(substr($cor, 5, 2));
        $coresRGB[] = imagecolorallocate($imagem, $r, $g, $b);
    }
    
    $total = array_sum($dados);
    if ($total == 0) {
        $total = 1;
    }
    
    // Desenhar pizza
    $centro_x = $largura / 2;
    $centro_y = ($altura - 60) / 2;
    $raio = min($centro_x, $centro_y) - 20;
    
    $angulo_inicial = 0;
    foreach ($dados as $i => $valor) {
        $angulo = ($valor / $total) * 360;
        imagefilledarc($imagem, $centro_x, $centro_y, $raio * 2, $raio * 2, 
                      $angulo_inicial, $angulo_inicial + $angulo, $coresRGB[$i], IMG_ARC_PIE);
        $angulo_inicial += $angulo;
    }
    
    // Adicionar legenda
    $preto = imagecolorallocate($imagem, 0, 0, 0);
    $y_legenda = $altura - 50;
    $x_legenda = 10;
    
    foreach ($labels as $i => $label) {
        imagefilledrectangle($imagem, $x_legenda, $y_legenda, $x_legenda + 15, $y_legenda + 15, $coresRGB[$i]);
        imagerectangle($imagem, $x_legenda, $y_legenda, $x_legenda + 15, $y_legenda + 15, $preto);
        imagestring($imagem, 3, $x_legenda + 20, $y_legenda, $label . ': ' . $dados[$i], $preto);
        $x_legenda += 130;
    }
    
    // Converter para base64
    ob_start();
    imagepng($imagem);
    $image_data = ob_get_contents();
    ob_end_clean();
    imagedestroy($imagem);
    
    return 'data:image/png;base64,' . base64_encode($image_data);
}

// Função para gerar gráfico de barras em base64
function gerarGraficoBarras($dados, $labels, $cores, $largura = 400, $altura = 300) {
    $imagem = imagecreatetruecolor($largura, $altura);
    
    // Cores
    $branco = imagecolorallocate($imagem, 255, 255, 255);
    $preto = imagecolorallocate($imagem, 0, 0, 0);
    $cinza_claro = imagecolorallocate($imagem, 200, 200, 200);
    imagefill($imagem, 0, 0, $branco);
    
    // Converter cores
    $coresRGB = [];
    foreach ($cores as $cor) {
        $r = hexdec(substr($cor, 1, 2));
        $g = hexdec(substr($cor, 3, 2));
        $b = hexdec(substr($cor, 5, 2));
        $coresRGB[] = imagecolorallocate($imagem, $r, $g, $b);
    }
    
    // Configurações
    $margem_esquerda = 50;
    $margem_direita = 20;
    $margem_superior = 30;
    $margem_inferior = 60;
    
    $largura_grafico = $largura - $margem_esquerda - $margem_direita;
    $altura_grafico = $altura - $margem_superior - $margem_inferior;
    
    // Encontrar valor máximo
    $max_valor = max($dados);
    if ($max_valor == 0) $max_valor = 1;
    
    // Desenhar eixos
    imageline($imagem, $margem_esquerda, $margem_superior, 
              $margem_esquerda, $altura - $margem_inferior, $preto);
    imageline($imagem, $margem_esquerda, $altura - $margem_inferior, 
              $largura - $margem_direita, $altura - $margem_inferior, $preto);
    
    // Desenhar barras
    $num_barras = count($dados);
    $largura_barra = ($largura_grafico / $num_barras) * 0.6;
    $espaco_entre_barras = ($largura_grafico / $num_barras) * 0.4;
    
    foreach ($dados as $i => $valor) {
        $altura_barra = ($valor / $max_valor) * $altura_grafico;
        $x1 = $margem_esquerda + ($i * ($largura_barra + $espaco_entre_barras)) + ($espaco_entre_barras / 2);
        $y1 = $altura - $margem_inferior - $altura_barra;
        $x2 = $x1 + $largura_barra;
        $y2 = $altura - $margem_inferior;
        
        imagefilledrectangle($imagem, $x1, $y1, $x2, $y2, $coresRGB[$i]);
        imagerectangle($imagem, $x1, $y1, $x2, $y2, $preto);
        
        // Valor no topo da barra
        $texto_largura = imagefontwidth(3) * strlen($valor);
        imagestring($imagem, 3, $x1 + ($largura_barra / 2) - ($texto_largura / 2), 
                   $y1 - 20, $valor, $preto);
        
        // Label embaixo
        $label = $labels[$i];
        $texto_largura = imagefontwidth(2) * strlen($label);
        imagestring($imagem, 2, $x1 + ($largura_barra / 2) - ($texto_largura / 2), 
                   $y2 + 10, $label, $preto);
    }
    
    // Converter para base64
    ob_start();
    imagepng($imagem);
    $image_data = ob_get_contents();
    ob_end_clean();
    imagedestroy($imagem);
    
    return 'data:image/png;base64,' . base64_encode($image_data);
}

// Gerar gráficos
$grafico_pizza = gerarGraficoPizza(
    [$presentes, $ausentes, $apenas_inscritos],
    ['Presentes', 'Ausentes', 'Inscritos'],
    ['#28a745', '#dc3545', '#6c757d']
);

$grafico_barras = gerarGraficoBarras(
    [$total, $vagas_disponiveis],
    ['Inscritos', 'Vagas Disp.'],
    ['#E50914', '#6c757d']
);

$grafico_completo = gerarGraficoPizza(
    [$presentes, $ausentes, $apenas_inscritos, $vagas_disponiveis],
    ['Presentes', 'Ausentes', 'Inscritos', 'Vagas'],
    ['#28a745', '#dc3545', '#ffc107', '#6c757d'],
    600, 300
);

// Criar HTML para o PDF
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            color: #E50914;
            border-bottom: 3px solid #E50914;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .info-section {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            page-break-inside: avoid;
        }
        .info-section h2 {
            color: #E50914;
            margin-top: 0;
            font-size: 18px;
            border-bottom: 2px solid #E50914;
            padding-bottom: 5px;
        }
        .info-row {
            margin: 8px 0;
            padding: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .event-content {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .event-image-col {
            display: table-cell;
            width: 35%;
            padding-right: 20px;
            vertical-align: top;
        }
        .event-info-col {
            display: table-cell;
            width: 65%;
            vertical-align: top;
        }
        .event-poster {
            width: 100%;
            max-width: 250px;
            height: auto;
            border: 3px solid #E50914;
            border-radius: 8px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .stats-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 15px;
            border: 1px solid #ddd;
        }
        .stats-number {
            font-size: 28px;
            font-weight: bold;
            color: #E50914;
        }
        .stats-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .chart-container {
            text-align: center;
            margin: 20px 0;
            page-break-inside: avoid;
        }
        .chart-container img {
            max-width: 100%;
            height: auto;
        }
        .chart-title {
            font-size: 16px;
            font-weight: bold;
            color: #E50914;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #E50914;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 12px;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-secondary { background-color: #6c757d; color: white; }
        .badge-info { background-color: #17a2b8; color: white; }
        .badge-warning { background-color: #ffc107; color: black; }
        
        .page-break {
            page-break-before: always;
        }
        .attendance-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .attendance-info {
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 2px solid #E50914;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .attendance-table th {
            background-color: #E50914;
            color: white;
            padding: 12px 8px;
            font-size: 11px;
        }
        .attendance-table td {
            padding: 20px 8px;
            border: 1px solid #333;
            font-size: 10px;
        }
        .signature-cell {
            min-height: 40px;
            border-bottom: 1px solid #999;
        }
        .charts-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .chart-cell {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Evento</h1>
        <p>Sistema de Gerenciamento de Eventos Escolares</p>
    </div>
    
    <div class="info-section">
        <h2>Informações do Evento</h2>
        <div class="event-content">';

// Adicionar imagem
if (!empty($imagem_base64)) {
    $html .= '
            <div class="event-image-col">
                <img src="' . $imagem_base64 . '" alt="Imagem do Evento" class="event-poster">
            </div>';
}

$html .= '
            <div class="event-info-col">
                <div class="info-row">
                    <span class="info-label">Nome:</span>
                    ' . htmlspecialchars($evento['nome_evento']) . '
                </div>
                <div class="info-row">
                    <span class="info-label">Descrição:</span>
                    ' . htmlspecialchars($evento['descricao_evento']) . '
                </div>
                <div class="info-row">
                    <span class="info-label">Data:</span>
                    ' . date('d/m/Y', strtotime($evento['data_evento'])) . '
                </div>
                <div class="info-row">
                    <span class="info-label">Horário:</span>
                    ' . date('H:i', strtotime($evento['hora_inicio'])) . ' às ' . date('H:i', strtotime($evento['hora_fim'])) . '
                </div>
                <div class="info-row">
                    <span class="info-label">Local:</span>
                    ' . htmlspecialchars($evento['local']) . '
                </div>
                <div class="info-row">
                    <span class="info-label">Limite:</span>
                    ' . $evento['limite_evento'] . ' participantes
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="badge badge-' . ($evento['status_evento'] == 'disponível' ? 'success' : ($evento['status_evento'] == 'esgotado' ? 'danger' : 'secondary')) . '">
                        ' . ucfirst($evento['status_evento']) . '
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="info-section">
        <h2>Estatísticas</h2>
        <div class="stats-grid">
            <div class="stats-item">
                <div class="stats-number">' . $total . '</div>
                <div class="stats-label">Total de Inscritos</div>
            </div>
            <div class="stats-item">
                <div class="stats-number" style="color: #28a745;">' . $presentes . '</div>
                <div class="stats-label">Presentes</div>
            </div>
            <div class="stats-item">
                <div class="stats-number" style="color: #dc3545;">' . $ausentes . '</div>
                <div class="stats-label">Ausentes</div>
            </div>
            <div class="stats-item">
                <div class="stats-number" style="color: #ffc107;">' . $taxa_presenca . '%</div>
                <div class="stats-label">Taxa de Presença</div>
            </div>
        </div>
    </div>
    
    <div class="info-section">
        <h2>Gráficos e Análises</h2>
        <div class="charts-row">
            <div class="chart-cell">
                <div class="chart-container">
                    <div class="chart-title">Status das Inscrições</div>
                    <img src="' . $grafico_pizza . '" alt="Gráfico de Pizza">
                </div>
            </div>
            <div class="chart-cell">
                <div class="chart-container">
                    <div class="chart-title">Ocupação do Evento</div>
                    <img src="' . $grafico_barras . '" alt="Gráfico de Barras">
                </div>
            </div>
        </div>
        <div class="chart-container">
            <div class="chart-title">Análise Completa do Evento</div>
            <img src="' . $grafico_completo . '" alt="Gráfico Completo" style="max-width: 600px;">
        </div>
    </div>
    
    <div class="info-section">
        <h2>Lista de Inscritos (' . count($inscritos) . ')</h2>';

if (count($inscritos) > 0) {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Email</th>
                    <th>Tipo</th>
                    <th>Data Inscrição</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($inscritos as $inscrito) {
        $status_badge = $inscrito['status'] == 'presente' ? 'success' : 
                       ($inscrito['status'] == 'ausente' ? 'danger' : 'secondary');
        
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($inscrito['nome']) . '</td>
                    <td>' . htmlspecialchars($inscrito['cpf']) . '</td>
                    <td>' . htmlspecialchars($inscrito['email']) . '</td>
                    <td>' . ucfirst($inscrito['tipo_usuario']) . '</td>
                    <td>' . date('d/m/Y', strtotime($inscrito['data_inscricao'])) . '</td>
                    <td><span class="badge badge-' . $status_badge . '">' . ucfirst($inscrito['status']) . '</span></td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
} else {
    $html .= '<p>Nenhum inscrito neste evento.</p>';
}

$html .= '
    </div>
    
    <div class="footer">
        <p>Relatório gerado em ' . date('d/m/Y H:i:s') . '</p>
        <p>Sistema de Gerenciamento de Eventos Escolares</p>
    </div>';

// LISTA DE PRESENÇA - Nova Página
if (count($inscritos) > 0) {
    $html .= '
    <div class="page-break"></div>
    
    <div class="attendance-header">
        <h1 style="color: #E50914; margin-bottom: 5px;">LISTA DE PRESENÇA</h1>
        <h2 style="color: #333; font-size: 16px; font-weight: normal;">' . htmlspecialchars($evento['nome_evento']) . '</h2>
    </div>
    
    <div class="attendance-info">
        <div style="display: table; width: 100%;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 50%; padding: 5px;">
                    <strong>Data:</strong> ' . date('d/m/Y', strtotime($evento['data_evento'])) . '
                </div>
                <div style="display: table-cell; width: 50%; padding: 5px;">
                    <strong>Horário:</strong> ' . date('H:i', strtotime($evento['hora_inicio'])) . ' às ' . date('H:i', strtotime($evento['hora_fim'])) . '
                </div>
            </div>
            <div style="display: table-row;">
                <div style="display: table-cell; padding: 5px;" colspan="2">
                    <strong>Local:</strong> ' . htmlspecialchars($evento['local']) . '
                </div>
            </div>
        </div>
    </div>
    
    <table class="attendance-table">
        <thead>
            <tr>
                <th style="width: 8%; text-align: center;">Nº</th>
                <th style="width: 40%;">Nome Completo</th>
                <th style="width: 20%;">CPF</th>
                <th style="width: 32%;">Assinatura</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 1;
    foreach ($inscritos as $inscrito) {
        $html .= '
            <tr>
                <td style="text-align: center; font-weight: bold;">' . $contador . '</td>
                <td>' . htmlspecialchars($inscrito['nome']) . '</td>
                <td>' . htmlspecialchars($inscrito['cpf']) . '</td>
                <td class="signature-cell">&nbsp;</td>
            </tr>';
        $contador++;
    }
    
    $html .= '
        </tbody>
    </table>
    
    <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #666;">
        <p>Total de Inscritos: <strong>' . count($inscritos) . '</strong></p>
        <p style="margin-top: 30px;">_____________________________________________</p>
        <p>Responsável pelo Evento</p>
    </div>';
}

$html .= '
</body>
</html>';

// Configurar Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$filename = 'Relatorio_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $evento['nome_evento']) . '_' . date('Y-m-d') . '.pdf';

// Fazer download
$dompdf->stream($filename, array('Attachment' => 1));
?>