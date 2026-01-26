<?php
/**
 * Teste rápido - Busca veículos de HOJE (para facilitar testes)
 */

require_once __DIR__ . '/conexao_bd.php';

echo "====================================================\n";
echo "TESTE - VEÍCULOS DE HOJE (PARA TESTE)\n";
echo "====================================================\n\n";

// Buscar veículos de HOJE ao invés de ONTEM
$sql = "SELECT 
            v.id, v.modelo, v.marca, v.ano_fabrica, 
            v.quilometragem, v.preco, v.data_cadastro,
            v.status, v.em_negociacao
        FROM veiculos v
        WHERE v.status = 'completo'
          AND v.em_negociacao = 0
          AND DATE(v.data_cadastro) = CURDATE()
        LIMIT 10";

$result = $mysqli->query($sql);

if (!$result) {
    echo "✗ Erro na query: " . $mysqli->error . "\n";
    exit(1);
}

if ($result->num_rows > 0) {
    echo "✓ Encontrados " . $result->num_rows . " veículo(s) de HOJE:\n\n";
    while ($row = $result->fetch_assoc()) {
        echo "----------------------------------------------------\n";
        echo "ID: " . $row['id'] . "\n";
        echo "Veículo: " . $row['marca'] . " " . $row['modelo'] . " (" . $row['ano_fabrica'] . ")\n";
        echo "Quilometragem: " . number_format($row['quilometragem'], 0, '', '.') . " km\n";
        echo "Preço: R$ " . number_format($row['preco'], 2, ',', '.') . "\n";
        echo "Cadastrado em: " . $row['data_cadastro'] . "\n";
        echo "Status: " . $row['status'] . "\n";
        echo "Em negociação: " . $row['em_negociacao'] . "\n";
    }
    echo "----------------------------------------------------\n";
} else {
    echo "⚠ Nenhum veículo encontrado HOJE com status='completo' e em_negociacao=0.\n\n";
    
    // Verificar se há veículos hoje com qualquer status
    $sqlTodos = "SELECT COUNT(*) as total FROM veiculos WHERE DATE(data_cadastro) = CURDATE()";
    $resultTodos = $mysqli->query($sqlTodos);
    if ($resultTodos) {
        $total = $resultTodos->fetch_assoc()['total'];
        echo "Total de veículos cadastrados hoje (qualquer status): $total\n\n";
    }
    
    echo "Dica: Cadastre um veículo de teste no sistema com:\n";
    echo "  - status = 'completo'\n";
    echo "  - em_negociacao = 0\n";
    echo "  - data_cadastro = hoje\n";
}

echo "\n====================================================\n";
?>
