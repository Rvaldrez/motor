<?php
/**
 * MotorGo – Script de Migração do Banco de Dados
 * ================================================
 * Execute este arquivo UMA VEZ no browser após fazer o deploy:
 *   https://dinolilo.com/migrar.php  (ou motorgo.co/migrar.php)
 *
 * O que faz: adiciona as colunas que existem no novo sistema mas
 * NÃO existiam na tabela original do sistema legado (baseado no
 * schema documentado em "Estrutura Banco de Dados.pdf").
 *
 * Seguro para executar múltiplas vezes – verifica se cada coluna
 * já existe antes de tentar adicioná-la.
 *
 * ⚠️ DELETE ESTE ARQUIVO DO SERVIDOR APÓS EXECUTAR!
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexao.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$resultados = [];

/**
 * Verifica se uma coluna existe e a adiciona caso não exista.
 */
function addColumnIfMissing(
    mysqli $conn,
    string $tabela,
    string $coluna,
    string $definicao
): string {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('ss', $tabela, $coluna);
    $stmt->execute();
    $stmt->bind_result($existe);
    $stmt->fetch();
    $stmt->close();

    if ($existe) {
        return "⏭️ <code>{$tabela}.{$coluna}</code> já existe — nenhuma alteração.";
    }

    if ($conn->query("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}")) {
        return "✅ <code>{$tabela}.{$coluna}</code> adicionada com sucesso.";
    }
    return "❌ Erro em <code>{$tabela}.{$coluna}</code>: "
         . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
}

/**
 * Modifica uma coluna existente (para expandir um ENUM, por exemplo).
 */
function modifyColumn(
    mysqli $conn,
    string $tabela,
    string $coluna,
    string $novaDefinicao
): string {
    if ($conn->query("ALTER TABLE `{$tabela}` MODIFY COLUMN `{$coluna}` {$novaDefinicao}")) {
        return "✅ <code>{$tabela}.{$coluna}</code> modificada com sucesso.";
    }
    return "❌ Erro ao modificar <code>{$tabela}.{$coluna}</code>: "
         . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
}

// ═══════════════════════════════════════════════════════════════
//  TABELA: veiculos
// ═══════════════════════════════════════════════════════════════

// 1. foto_principal — caminho relativo da foto de capa (novo sistema)
//    Causa o erro fatal: "Unknown column 'v.foto_principal'"
$resultados[] = addColumnIfMissing(
    $conn, 'veiculos', 'foto_principal',
    "VARCHAR(255) NULL DEFAULT NULL"
);

// 2. veiculos.status — o sistema legado usa ENUM('incompleto','completo').
//    O novo sistema precisa de valores como 'disponivel', 'pendente', etc.
//    Convertemos para VARCHAR(50) mantendo o DEFAULT 'completo' do legado.
$resultados[] = "<strong>Alterando <code>veiculos.status</code> de ENUM para VARCHAR(50)…</strong> "
              . modifyColumn(
                    $conn, 'veiculos', 'status',
                    "VARCHAR(50) NOT NULL DEFAULT 'completo'"
                );

// ═══════════════════════════════════════════════════════════════
//  TABELA: usuarios
// ═══════════════════════════════════════════════════════════════

// 3. bairro — obrigatório no formulário de cadastro do novo sistema;
//    o INSERT em actions/cadastro.php já inclui esta coluna.
$resultados[] = addColumnIfMissing(
    $conn, 'usuarios', 'bairro',
    "VARCHAR(100) NULL DEFAULT NULL AFTER `complemento`"
);

// 4. status — campo de status geral da conta (ativo/inativo/bloqueado).
//    Diferente de status_confirmacao e status_cadastro que já existem.
$resultados[] = addColumnIfMissing(
    $conn, 'usuarios', 'status',
    "VARCHAR(30) NOT NULL DEFAULT 'ativo' AFTER `tipo`"
);

// 5. foto — foto de perfil do usuário
$resultados[] = addColumnIfMissing(
    $conn, 'usuarios', 'foto',
    "VARCHAR(255) NULL DEFAULT NULL"
);

// ═══════════════════════════════════════════════════════════════
//  TABELA: propostas
// ═══════════════════════════════════════════════════════════════

// 6. proposta_origem_id — chave estrangeira para contropropostas (auto-referência).
//    Se NULL: proposta original. Se preenchido: contra-proposta da proposta original.
//    O novo sistema filtra por proposta_origem_id IS NULL para exibir apenas originais.
$resultados[] = addColumnIfMissing(
    $conn, 'propostas', 'proposta_origem_id',
    "INT(11) NULL DEFAULT NULL"
);

// ═══════════════════════════════════════════════════════════════
//  COLUNAS JÁ EXISTENTES NO SCHEMA LEGADO (apenas informativo)
// ═══════════════════════════════════════════════════════════════
$jaExistem = [
    'usuarios.numero'              => 'confirmado pelo PDF',
    'usuarios.complemento'         => 'confirmado pelo PDF',
    'propostas.mensagem'           => 'confirmado pelo PDF',
    'fotos_veiculos.ordem_exibicao'=> 'confirmado pelo PDF (default 1)',
    'veiculos.cor'                 => 'confirmado pelo PDF',
    'veiculos.em_negociacao'       => 'confirmado pelo PDF',
];
$resultados[] = "<hr><strong>Colunas que já existiam no banco e não precisaram de migração:</strong><br>"
    . implode('<br>', array_map(
        fn($k, $v) => "⏭️ <code>{$k}</code> — {$v}",
        array_keys($jaExistem),
        $jaExistem
    ));

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>MotorGo – Migração do Banco de Dados</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 820px; margin: 40px auto; padding: 20px; background: #f8f9fa; color: #212529; }
  h1 { color: #1a1a2e; }
  .resultado { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 16px; margin: 8px 0; font-size: 14px; line-height: 1.6; }
  .aviso { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-top: 24px; }
  code { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  hr { border: none; border-top: 1px solid #dee2e6; margin: 8px 0; }
</style>
</head>
<body>
<h1>🔧 MotorGo – Migração do Banco de Dados</h1>
<p>Baseado no schema documentado em <em>Estrutura Banco de Dados.pdf</em>.</p>
<h3>Resultados:</h3>
<?php foreach ($resultados as $r): ?>
  <div class="resultado"><?= $r ?></div>
<?php endforeach; ?>
<div class="aviso">
  <strong>⚠️ Importante:</strong> Após confirmar que todas as migrações foram aplicadas com sucesso,
  <strong>delete este arquivo do servidor</strong> (<code>migrar.php</code>) por segurança.
</div>
</body>
</html>
