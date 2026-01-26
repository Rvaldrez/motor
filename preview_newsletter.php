<?php
/**
 * PREVIEW DO LAYOUT DA NEWSLETTER
 * 
 * Este script gera uma prévia do email da newsletter sem enviar.
 * Útil para visualizar o layout antes de ativar o envio automático.
 */

require_once __DIR__ . '/conexao_bd.php';

define('BASE_URL', 'https://motorgo.co');
define('EMAIL_SUBJECT', 'Novos Veículos Disponíveis - MotorGo');

echo "====================================================\n";
echo "GERANDO PREVIEW DO EMAIL DA NEWSLETTER\n";
echo "====================================================\n\n";

// Buscar alguns veículos de exemplo (últimos 3 cadastrados com status completo)
$sql = "SELECT 
            v.id,
            v.modelo,
            v.marca,
            v.ano_fabrica,
            v.quilometragem,
            v.preco,
            u.cidade AS usuario_cidade,
            u.estado AS usuario_estado,
            (SELECT caminho_foto 
             FROM fotos_veiculos 
             WHERE veiculo_id = v.id 
             ORDER BY ordem_exibicao ASC, id ASC 
             LIMIT 1) AS foto_principal
        FROM veiculos v
        LEFT JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.status = 'completo'
          AND v.em_negociacao = 0
        ORDER BY v.data_cadastro DESC
        LIMIT 3";

$result = $mysqli->query($sql);
$veiculos = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $veiculos[] = $row;
    }
}

if (count($veiculos) == 0) {
    echo "⚠ Nenhum veículo encontrado para preview.\n";
    echo "  Usando dados de exemplo...\n\n";
    
    // Dados de exemplo para demonstração
    $veiculos = [
        [
            'id' => 1,
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'ano_fabrica' => 2020,
            'quilometragem' => 45000,
            'preco' => 95000.00,
            'usuario_cidade' => 'São Paulo',
            'usuario_estado' => 'SP',
            'foto_principal' => null
        ],
        [
            'id' => 2,
            'marca' => 'Honda',
            'modelo' => 'Civic',
            'ano_fabrica' => 2019,
            'quilometragem' => 62000,
            'preco' => 78500.00,
            'usuario_cidade' => 'Rio de Janeiro',
            'usuario_estado' => 'RJ',
            'foto_principal' => null
        ]
    ];
} else {
    echo "✓ Usando " . count($veiculos) . " veículo(s) real(is) do banco de dados\n\n";
}

// Nome de exemplo do investidor
$nomeInvestidor = "João Silva";

// Gerar HTML do email (mesma função do script principal)
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo EMAIL_SUBJECT; ?></title>
    <style>
        /* Reset básico */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        
        /* Container principal */
        .email-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        /* Header */
        .email-header {
            background-color: #1a1a1a;
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header h1 {
            color: #B22222;
            font-size: 28px;
            margin: 0;
            font-weight: bold;
        }
        
        .email-header .logo-text {
            color: #ffffff;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* Introdução */
        .email-intro {
            padding: 30px 20px;
            background-color: #ffffff;
        }
        
        .email-intro h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .email-intro p {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        /* Container de veículos */
        .veiculos-container {
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        /* Card de veículo */
        .veiculo-card {
            background-color: #ffffff;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .veiculo-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        
        .veiculo-info {
            padding: 20px;
        }
        
        .veiculo-info h3 {
            color: #B22222;
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: bold;
        }
        
        .veiculo-info p {
            color: #333;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 8px;
        }
        
        .veiculo-info strong {
            color: #000;
        }
        
        .veiculo-preco {
            font-size: 18px;
            color: #B22222;
            font-weight: bold;
            margin-top: 15px;
        }
        
        /* Botão CTA */
        .btn-cta {
            display: inline-block;
            background: linear-gradient(135deg, #B22222 0%, #8B0000 100%);
            color: #ffffff !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 15px;
            text-align: center;
        }
        
        .btn-cta:hover {
            background: linear-gradient(135deg, #8B0000 0%, #B22222 100%);
        }
        
        /* Footer */
        .email-footer {
            background-color: #1a1a1a;
            padding: 25px 20px;
            text-align: center;
        }
        
        .email-footer p {
            color: #999;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 5px;
        }
        
        .email-footer a {
            color: #B22222;
            text-decoration: none;
        }
        
        /* Responsividade */
        @media only screen and (max-width: 600px) {
            .email-header h1 {
                font-size: 22px;
            }
            
            .email-intro h2 {
                font-size: 18px;
            }
            
            .veiculo-card img {
                height: 200px;
            }
            
            .veiculo-info h3 {
                font-size: 18px;
            }
            
            .veiculo-info p {
                font-size: 14px;
            }
        }
        
        /* Mensagem quando não há veículos */
        .sem-veiculos {
            padding: 40px 20px;
            text-align: center;
            color: #666;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo-text">MOTORGO</div>
            <h1>Novos Veículos Disponíveis</h1>
        </div>
        
        <!-- Introdução -->
        <div class="email-intro">
            <h2>Olá, <?php echo htmlspecialchars($nomeInvestidor); ?>!</h2>
            <p>Temos <?php echo count($veiculos); ?> <?php echo count($veiculos) == 1 ? 'novo veículo' : 'novos veículos'; ?> disponível<?php echo count($veiculos) == 1 ? '' : 'eis'; ?> para investimento cadastrado<?php echo count($veiculos) == 1 ? '' : 's'; ?> nas últimas 24 horas.</p>
            <p>Confira abaixo as oportunidades e garanta o melhor investimento!</p>
        </div>
        
        <!-- Veículos -->
        <?php if (count($veiculos) > 0): ?>
        <div class="veiculos-container">
            <?php foreach ($veiculos as $veiculo): 
                // Usar foto do veículo ou placeholder base64 se não houver foto
                if (!empty($veiculo['foto_principal'])) {
                    $foto = BASE_URL . '/' . $veiculo['foto_principal'];
                } else {
                    // Placeholder SVG inline em base64 - não depende de arquivo externo
                    $foto = 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8"?><svg width="400" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="400" height="300" fill="#e0e0e0"/><text x="50%" y="50%" font-family="Arial" font-size="24" fill="#666" text-anchor="middle" dominant-baseline="middle">Imagem do Veículo</text></svg>');
                }
                $preco = number_format($veiculo['preco'], 2, ',', '.');
                $km = number_format($veiculo['quilometragem'], 0, '', '.');
                $localizacao = trim($veiculo['usuario_cidade'] . '/' . $veiculo['usuario_estado']);
                if ($localizacao == '/') $localizacao = 'Não informado';
            ?>
            <div class="veiculo-card">
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="<?php echo htmlspecialchars($veiculo['modelo']); ?>">
                <div class="veiculo-info">
                    <h3><?php echo htmlspecialchars($veiculo['marca'] . ' ' . $veiculo['modelo']); ?></h3>
                    <p><strong>Ano de Fabricação:</strong> <?php echo htmlspecialchars($veiculo['ano_fabrica']); ?></p>
                    <p><strong>Quilometragem:</strong> <?php echo htmlspecialchars($km); ?> km</p>
                    <p><strong>Localização:</strong> <?php echo htmlspecialchars($localizacao); ?></p>
                    <p class="veiculo-preco">Valor FIPE: R$ <?php echo htmlspecialchars($preco); ?></p>
                    <a href="<?php echo BASE_URL; ?>/painel_investidor.php" class="btn-cta">Ver Detalhes e Investir</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="sem-veiculos">
            <p>Nenhum veículo novo disponível hoje. Fique atento às próximas oportunidades!</p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="email-footer">
            <p><strong>MotorGo</strong> - Sua plataforma de investimento em veículos</p>
            <p>Este é um email automático. Para dúvidas, entre em contato: <a href="mailto:sac@motorgo.co">sac@motorgo.co</a></p>
            <p>&copy; <?php echo date('Y'); ?> MotorGo. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
<?php
$htmlEmail = ob_get_clean();

// Salvar em arquivo HTML
$nomeArquivo = 'preview_newsletter_' . date('Y-m-d_H-i-s') . '.html';
file_put_contents($nomeArquivo, $htmlEmail);

echo "✓ Preview gerado com sucesso!\n\n";
echo "Arquivo salvo: $nomeArquivo\n\n";
echo "====================================================\n";
echo "COMO VISUALIZAR:\n";
echo "====================================================\n";
echo "1. Abra o arquivo no navegador:\n";
echo "   - Windows: start $nomeArquivo\n";
echo "   - Mac: open $nomeArquivo\n";
echo "   - Linux: xdg-open $nomeArquivo\n";
echo "   - Ou abra manualmente no navegador\n\n";
echo "2. O arquivo está em: " . __DIR__ . "/$nomeArquivo\n\n";
echo "3. Para testar responsividade:\n";
echo "   - Redimensione a janela do navegador\n";
echo "   - Use DevTools (F12) e teste em modo mobile\n\n";
echo "====================================================\n";
echo "PRÓXIMOS PASSOS:\n";
echo "====================================================\n";
echo "Se o layout estiver OK:\n";
echo "1. Execute: php enviar_newsletter_diario.php (para teste completo)\n";
echo "2. Configure o CronJob para envio automático diário\n\n";
?>
