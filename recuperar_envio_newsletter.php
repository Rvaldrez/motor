<?php
/**
 * ============================================================================
 * SCRIPT DE RECUPERAÇÃO - VERIFICAR STATUS DE ENVIO DA NEWSLETTER
 * ============================================================================
 * 
 * Este script mostra:
 * - Quantos emails da newsletter foram enviados hoje
 * - Quais investidores já receberam
 * - Quais ainda precisam receber
 * - Estatísticas de sucesso/falha
 * 
 * Use após problemas de execução para verificar o que foi enviado.
 * 
 * USO:
 * php recuperar_envio_newsletter.php
 * 
 * ============================================================================
 */

// Carregar conexão com banco de dados
require_once __DIR__ . '/conexao_bd.php';

echo "====================================================\n";
echo "RECUPERAÇÃO - STATUS DE ENVIO DA NEWSLETTER\n";
echo "====================================================\n\n";

// Conectar ao banco
$mysqli = $conn;

// Verificar envios de hoje
$dataHoje = date('Y-m-d');

echo "📅 Verificando envios de hoje ($dataHoje)...\n\n";

// Query para buscar envios de hoje
$query = "
    SELECT 
        id,
        usuario_id,
        email,
        assunto,
        status,
        veiculos_enviados,
        data_envio,
        erro_mensagem
    FROM newsletter 
    WHERE DATE(data_envio) = ?
    ORDER BY data_envio DESC
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('s', $dataHoje);
$stmt->execute();
$result = $stmt->get_result();

$envios = [];
while ($row = $result->fetch_assoc()) {
    $envios[] = $row;
}

echo "----------------------------------------------------\n";
echo "📊 ESTATÍSTICAS DE ENVIO\n";
echo "----------------------------------------------------\n\n";

if (count($envios) == 0) {
    echo "⚠️  Nenhum envio registrado hoje.\n\n";
    echo "Possíveis motivos:\n";
    echo "  1. Newsletter ainda não foi executada hoje\n";
    echo "  2. Não havia veículos novos para enviar\n";
    echo "  3. Script travou antes de registrar envios\n";
    echo "  4. Tabela 'newsletter' não existe\n\n";
} else {
    // Contar sucessos e falhas
    $sucessos = 0;
    $falhas = 0;
    
    foreach ($envios as $envio) {
        if ($envio['status'] == 'enviado') {
            $sucessos++;
        } else {
            $falhas++;
        }
    }
    
    echo "Total de envios registrados: " . count($envios) . "\n";
    echo "✓ Enviados com sucesso: $sucessos\n";
    echo "✗ Falhas: $falhas\n\n";
    
    // Mostrar detalhes dos envios
    echo "----------------------------------------------------\n";
    echo "DETALHES DOS ENVIOS\n";
    echo "----------------------------------------------------\n\n";
    
    foreach ($envios as $i => $envio) {
        $numero = $i + 1;
        $status_icon = ($envio['status'] == 'enviado') ? '✓' : '✗';
        $status_text = ($envio['status'] == 'enviado') ? 'SUCESSO' : 'FALHA';
        
        echo "$numero. [$status_icon] {$envio['email']}\n";
        echo "   Status: $status_text\n";
        echo "   Horário: {$envio['data_envio']}\n";
        echo "   Veículos enviados: {$envio['veiculos_enviados']}\n";
        
        if (!empty($envio['erro_mensagem'])) {
            echo "   ⚠️  Erro: {$envio['erro_mensagem']}\n";
        }
        
        echo "\n";
    }
}

// Verificar total de investidores vs enviados
echo "----------------------------------------------------\n";
echo "VERIFICAÇÃO DE COBERTURA\n";
echo "----------------------------------------------------\n\n";

$queryInvestidores = "
    SELECT COUNT(*) as total 
    FROM usuarios 
    WHERE tipo = 'investidor' 
    AND status_confirmacao = 'confirmado' 
    AND status_cadastro = 'completo'
";

$resultInvestidores = $mysqli->query($queryInvestidores);
$rowInvestidores = $resultInvestidores->fetch_assoc();
$totalInvestidores = $rowInvestidores['total'];

echo "Total de investidores ativos: $totalInvestidores\n";
echo "Emails enviados hoje: " . count($envios) . "\n";

if (count($envios) < $totalInvestidores && count($envios) > 0) {
    $faltam = $totalInvestidores - count($envios);
    echo "\n⚠️  ATENÇÃO: Faltam $faltam investidores!\n";
    echo "O envio pode ter sido interrompido.\n\n";
    
    echo "INVESTIDORES QUE NÃO RECEBERAM HOJE:\n";
    echo "----------------------------------------------------\n";
    
    // Buscar investidores que não receberam
    $emailsEnviados = array_column($envios, 'email');
    $emailsEnviadosStr = "'" . implode("','", $emailsEnviados) . "'";
    
    $queryFaltantes = "
        SELECT id, nome, email 
        FROM usuarios 
        WHERE tipo = 'investidor' 
        AND status_confirmacao = 'confirmado' 
        AND status_cadastro = 'completo'
        AND email NOT IN ($emailsEnviadosStr)
        ORDER BY nome
    ";
    
    $resultFaltantes = $mysqli->query($queryFaltantes);
    
    while ($faltante = $resultFaltantes->fetch_assoc()) {
        echo "- {$faltante['nome']} ({$faltante['email']})\n";
    }
    
    echo "\n";
}

// Verificar último envio (qualquer dia)
echo "----------------------------------------------------\n";
echo "ÚLTIMO ENVIO (QUALQUER DIA)\n";
echo "----------------------------------------------------\n\n";

$queryUltimo = "
    SELECT 
        DATE(data_envio) as data,
        COUNT(*) as quantidade,
        SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as sucessos
    FROM newsletter 
    GROUP BY DATE(data_envio)
    ORDER BY data_envio DESC
    LIMIT 5
";

$resultUltimo = $mysqli->query($queryUltimo);

echo "Histórico dos últimos 5 dias:\n\n";

while ($row = $resultUltimo->fetch_assoc()) {
    echo "📅 {$row['data']}: {$row['sucessos']}/{$row['quantidade']} emails enviados\n";
}

echo "\n";

// Recomendações
echo "====================================================\n";
echo "RECOMENDAÇÕES\n";
echo "====================================================\n\n";

if (count($envios) == 0) {
    echo "✓ Execute a newsletter manualmente:\n";
    echo "  php enviar_newsletter_diario.php\n\n";
} elseif (count($envios) < $totalInvestidores) {
    echo "⚠️  Envio incompleto detectado!\n\n";
    echo "Ações recomendadas:\n";
    echo "1. Verifique logs de erro:\n";
    echo "   cat logs/email_erros.log\n\n";
    echo "2. Execute novamente (vai enviar apenas para quem não recebeu):\n";
    echo "   php enviar_newsletter_diario.php\n\n";
    echo "   OBS: O script não envia duplicado para quem já recebeu hoje.\n\n";
} else {
    echo "✅ Tudo OK! Todos os investidores receberam a newsletter.\n\n";
}

echo "📖 Para mais informações, consulte:\n";
echo "   - IMPORTANTE_NAO_USAR_BROWSER.md\n";
echo "   - NEWSLETTER_SETUP.md\n";
echo "   - logs/email_erros.log\n\n";

echo "====================================================\n";

$mysqli->close();
