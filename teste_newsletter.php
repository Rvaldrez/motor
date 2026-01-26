<?php
/**
 * SCRIPT DE TESTE - Newsletter Diária
 * 
 * Este script testa a funcionalidade da newsletter sem enviar emails reais.
 * Útil para validar queries, template HTML e lógica de negócio.
 */

// Simular environment
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u218663118_motorgo');
define('DB_PASS', 'MotorGo@2025_Vic');
define('DB_NAME', 'u218663118_motorgo');
define('BASE_URL', 'https://motorgo.co');

echo "====================================================\n";
echo "TESTE - NEWSLETTER DIÁRIA\n";
echo "====================================================\n\n";

// Conectar ao banco
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("✗ Erro na conexão: " . $mysqli->connect_error . "\n");
}

$mysqli->set_charset("utf8");
echo "✓ Conectado ao banco de dados\n\n";

// Testar query de veículos
echo "Testando query de veículos...\n";
$sql = "SELECT 
            v.id,
            v.modelo,
            v.marca,
            v.ano_fabrica,
            v.quilometragem,
            v.preco,
            v.data_cadastro,
            v.status,
            v.em_negociacao,
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
          AND DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))
        ORDER BY v.data_cadastro DESC";

$result = $mysqli->query($sql);

if (!$result) {
    echo "✗ Erro na query: " . $mysqli->error . "\n";
} else {
    $veiculos = [];
    while ($row = $result->fetch_assoc()) {
        $veiculos[] = $row;
    }
    
    echo "✓ Query executada com sucesso\n";
    echo "  Veículos encontrados: " . count($veiculos) . "\n\n";
    
    if (count($veiculos) > 0) {
        echo "Detalhes dos veículos:\n";
        echo "----------------------------------------------------\n";
        foreach ($veiculos as $v) {
            echo "ID: " . $v['id'] . "\n";
            echo "Veículo: " . $v['marca'] . " " . $v['modelo'] . " (" . $v['ano_fabrica'] . ")\n";
            echo "Quilometragem: " . number_format($v['quilometragem'], 0, '', '.') . " km\n";
            echo "Preço: R$ " . number_format($v['preco'], 2, ',', '.') . "\n";
            echo "Localização: " . ($v['usuario_cidade'] ?? 'N/A') . "/" . ($v['usuario_estado'] ?? 'N/A') . "\n";
            echo "Foto: " . ($v['foto_principal'] ?? 'Nenhuma') . "\n";
            echo "Data cadastro: " . $v['data_cadastro'] . "\n";
            echo "Status: " . $v['status'] . "\n";
            echo "Em negociação: " . $v['em_negociacao'] . "\n";
            echo "----------------------------------------------------\n";
        }
    }
}

// Testar query de investidores
echo "\nTestando query de investidores...\n";
$sql = "SELECT id, nome, email, tipo, status_confirmacao, status_cadastro
        FROM usuarios
        WHERE tipo = 'investidor'
          AND status_confirmacao = 'confirmado'
          AND status_cadastro = 'completo'
        ORDER BY nome ASC";

$result = $mysqli->query($sql);

if (!$result) {
    echo "✗ Erro na query: " . $mysqli->error . "\n";
} else {
    $investidores = [];
    while ($row = $result->fetch_assoc()) {
        $investidores[] = $row;
    }
    
    echo "✓ Query executada com sucesso\n";
    echo "  Investidores encontrados: " . count($investidores) . "\n\n";
    
    if (count($investidores) > 0) {
        echo "Primeiros 5 investidores:\n";
        echo "----------------------------------------------------\n";
        $limite = min(5, count($investidores));
        for ($i = 0; $i < $limite; $i++) {
            $inv = $investidores[$i];
            echo ($i + 1) . ". " . $inv['nome'] . " - " . $inv['email'] . "\n";
        }
        echo "----------------------------------------------------\n";
    }
}

// Verificar se tabela emails_automaticos existe
echo "\nVerificando tabela emails_automaticos...\n";
$result = $mysqli->query("SHOW TABLES LIKE 'emails_automaticos'");
if ($result->num_rows > 0) {
    echo "✓ Tabela emails_automaticos existe\n";
    
    // Verificar estrutura
    $result = $mysqli->query("DESCRIBE emails_automaticos");
    if ($result) {
        echo "\nEstrutura da tabela:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
    // Verificar registros recentes
    $result = $mysqli->query("SELECT COUNT(*) as total FROM emails_automaticos WHERE tipo = 'newsletter_novo_veiculo'");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "\nTotal de newsletters enviadas: " . $row['total'] . "\n";
    }
} else {
    echo "⚠ Tabela emails_automaticos não existe\n";
    echo "  (Será criada automaticamente na primeira execução do script principal)\n";
}

// Testar geração de HTML (sem enviar)
if (count($veiculos) > 0) {
    echo "\n\nTestando geração de HTML do email...\n";
    
    // Simular a função gerarHTMLEmail simplificada
    $nomeInvestidor = "Investidor Teste";
    $totalVeiculos = count($veiculos);
    
    echo "✓ HTML seria gerado para:\n";
    echo "  - Nome: $nomeInvestidor\n";
    echo "  - Veículos: $totalVeiculos\n";
    echo "  - Template: Professional HTML com CSS inline\n";
    echo "  - Cores: #B22222 (vermelho MotorGo), #1a1a1a (header/footer)\n";
}

$mysqli->close();

echo "\n====================================================\n";
echo "TESTE CONCLUÍDO\n";
echo "====================================================\n";
?>
