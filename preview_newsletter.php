<?php
/**
 * PREVIEW DO LAYOUT DA NEWSLETTER
 * 
 * Este script gera uma prévia do email da newsletter sem enviar.
 * Útil para visualizar o layout antes de ativar o envio automático.
 */

require_once __DIR__ . '/conexao_bd.php';

// Incluir funções do script principal
// Precisamos definir as constantes antes de incluir
define('BASE_URL', 'https://motorgo.co');
define('EMAIL_SUBJECT', 'Novos Veículos Disponíveis - MotorGo');

// Carregar dependências do Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Incluir as funções do script principal
require_once __DIR__ . '/enviar_newsletter_diario.php';

echo "====================================================\n";
echo "GERANDO PREVIEW DO EMAIL DA NEWSLETTER\n";
echo "====================================================\n\n";

// Buscar veículos novos (últimas 24h) para preview
echo "Buscando veículos novos (últimas 24h)...\n";
$veiculosNovos = buscarVeiculosNovos($mysqli);
echo "✓ Encontrados: " . count($veiculosNovos) . "\n\n";

// Buscar os 4 cadastros mais recentes (DOS DIAS ANTERIORES)
echo "Buscando cadastros recentes (dias anteriores)...\n";
$veiculosRecentes = buscarVeiculosRecentes($mysqli);
echo "✓ Encontrados: " . count($veiculosRecentes) . "\n\n";

// Se não houver veículos reais, usar dados de exemplo
if (count($veiculosNovos) == 0 && count($veiculosRecentes) == 0) {
    echo "⚠ Nenhum veículo encontrado no banco.\n";
    echo "  Usando dados de exemplo para demonstração...\n\n";
    
    // Dados de exemplo para demonstração
    $veiculosNovos = [
        [
            'id' => 1,
            'marca' => 'Toyota',
            'modelo' => 'Corolla XEi',
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
            'modelo' => 'Civic Sport',
            'ano_fabrica' => 2019,
            'quilometragem' => 62000,
            'preco' => 78500.00,
            'usuario_cidade' => 'Rio de Janeiro',
            'usuario_estado' => 'RJ',
            'foto_principal' => null
        ]
    ];
    
    $veiculosRecentes = [
        [
            'id' => 3,
            'marca' => 'Volkswagen',
            'modelo' => 'Golf GTI',
            'ano_fabrica' => 2018,
            'quilometragem' => 78000,
            'preco' => 65000.00,
            'usuario_cidade' => 'Belo Horizonte',
            'usuario_estado' => 'MG',
            'foto_principal' => null
        ],
        [
            'id' => 4,
            'marca' => 'Ford',
            'modelo' => 'Fusion Titanium',
            'ano_fabrica' => 2017,
            'quilometragem' => 95000,
            'preco' => 55000.00,
            'usuario_cidade' => 'Curitiba',
            'usuario_estado' => 'PR',
            'foto_principal' => null
        ],
        [
            'id' => 5,
            'marca' => 'Chevrolet',
            'modelo' => 'Cruze LTZ',
            'ano_fabrica' => 2019,
            'quilometragem' => 52000,
            'preco' => 72000.00,
            'usuario_cidade' => 'Porto Alegre',
            'usuario_estado' => 'RS',
            'foto_principal' => null
        ],
        [
            'id' => 6,
            'marca' => 'Fiat',
            'modelo' => 'Toro Freedom',
            'ano_fabrica' => 2020,
            'quilometragem' => 38000,
            'preco' => 88000.00,
            'usuario_cidade' => 'Brasília',
            'usuario_estado' => 'DF',
            'foto_principal' => null
        ]
    ];
}

// Nome de exemplo do investidor
$nomeInvestidor = "João Silva (Preview)";

// Gerar HTML do email usando a função do script principal
echo "Gerando HTML do email...\n";
$htmlEmail = gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $nomeInvestidor);
echo "✓ HTML gerado\n\n";

// Salvar em arquivo HTML
$nomeArquivo = 'preview_newsletter_' . date('Y-m-d_H-i-s') . '.html';
file_put_contents($nomeArquivo, $htmlEmail);

echo "✓ Preview gerado com sucesso!\n\n";
echo "Arquivo salvo: $nomeArquivo\n\n";
echo "📊 Resumo do preview:\n";
echo "  🚗 Veículos novos (24h): " . count($veiculosNovos) . "\n";
echo "  📋 Cadastros recentes: " . count($veiculosRecentes) . "\n";
echo "  📦 Total de veículos: " . (count($veiculosNovos) + count($veiculosRecentes)) . "\n\n";
echo "====================================================\n";
echo "COMO VISUALIZAR:\n";
echo "====================================================\n";
echo "1. Abra o arquivo no navegador:\n";
echo "   - Windows: start $nomeArquivo\n";
echo "   - Mac: open $nomeArquivo\n";
echo "   - Linux: xdg-open $nomeArquivo\n";
echo "\n2. Ou abra manualmente o arquivo:\n";
echo "   $nomeArquivo\n\n";
echo "====================================================\n";
echo "PRÓXIMOS PASSOS:\n";
echo "====================================================\n";
echo "1. Verifique o layout no navegador\n";
echo "2. Teste a responsividade (F12 > Device Toolbar)\n";
echo "3. Se estiver ok, execute: php enviar_newsletter_diario.php\n";
echo "4. Configure o CronJob para automação\n\n";

?>
