<?php
/**
 * Script de teste para verificar a lógica do newsletter
 * Este script não requer conexão com banco de dados
 */

echo "=== Teste do Newsletter System ===\n\n";

// 1. Verificar se os arquivos existem
echo "1. Verificando arquivos...\n";
$arquivosNecessarios = [
    __DIR__ . '/../cron/enviar_newsletter_diario.php',
    __DIR__ . '/../sql/criar_tabela_emails_automaticos.sql',
    __DIR__ . '/../.env.example',
    __DIR__ . '/../NEWSLETTER_README.md',
    __DIR__ . '/../composer.json',
    __DIR__ . '/../vendor/autoload.php'
];

$todosExistem = true;
foreach ($arquivosNecessarios as $arquivo) {
    $existe = file_exists($arquivo);
    echo "   " . ($existe ? "✓" : "✗") . " " . basename($arquivo) . "\n";
    if (!$existe) $todosExistem = false;
}

if (!$todosExistem) {
    echo "\n❌ Alguns arquivos estão faltando!\n";
    exit(1);
}

echo "\n2. Verificando sintaxe PHP...\n";
$output = [];
$returnVar = 0;
exec('php -l ' . __DIR__ . '/../cron/enviar_newsletter_diario.php 2>&1', $output, $returnVar);
if ($returnVar === 0) {
    echo "   ✓ Sintaxe PHP válida\n";
} else {
    echo "   ✗ Erro de sintaxe:\n";
    echo "   " . implode("\n   ", $output) . "\n";
    exit(1);
}

echo "\n3. Verificando dependências do Composer...\n";
require_once __DIR__ . '/../vendor/autoload.php';

$dependenciasOk = true;
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "   ✓ PHPMailer disponível\n";
} else {
    echo "   ✗ PHPMailer não encontrado\n";
    $dependenciasOk = false;
}

if (class_exists('Dotenv\Dotenv')) {
    echo "   ✓ vlucas/phpdotenv disponível\n";
} else {
    echo "   ✗ vlucas/phpdotenv não encontrado\n";
    $dependenciasOk = false;
}

if (!$dependenciasOk) {
    echo "\n❌ Dependências faltando. Execute: composer install\n";
    exit(1);
}

echo "\n4. Verificando estrutura do SQL...\n";
$sqlContent = file_get_contents(__DIR__ . '/../sql/criar_tabela_emails_automaticos.sql');
$requiredElements = [
    'CREATE TABLE' => 'Declaração CREATE TABLE',
    'emails_automaticos' => 'Nome da tabela',
    'usuario_id' => 'Campo usuario_id',
    'tipo' => 'Campo tipo',
    'data_envio' => 'Campo data_envio'
];

foreach ($requiredElements as $element => $description) {
    if (stripos($sqlContent, $element) !== false) {
        echo "   ✓ $description encontrado\n";
    } else {
        echo "   ✗ $description não encontrado\n";
        exit(1);
    }
}

echo "\n5. Verificando template de email...\n";
$newsletterContent = file_get_contents(__DIR__ . '/../cron/enviar_newsletter_diario.php');

$templateElements = [
    'PHPMailer' => 'Importação do PHPMailer',
    'smtp.hostinger.com' => 'Configuração SMTP',
    'newsletter_novo_veiculo' => 'Tipo de email correto',
    'WHERE DATE(v.data_cadastro) = ?' => 'Filtro de data',
    "v.status = 'completo'" => 'Filtro de status completo',
    'v.em_negociacao = 0' => 'Filtro de não em negociação',
    "tipo = 'investidor'" => 'Filtro de tipo investidor',
    "status_cadastro = 'completo'" => 'Filtro de status cadastro',
    "status_confirmacao = 'confirmado'" => 'Filtro de confirmação'
];

foreach ($templateElements as $element => $description) {
    if (strpos($newsletterContent, $element) !== false) {
        echo "   ✓ $description\n";
    } else {
        echo "   ✗ $description não encontrado\n";
        exit(1);
    }
}

echo "\n6. Verificando estrutura do README...\n";
$readmeContent = file_get_contents(__DIR__ . '/../NEWSLETTER_README.md');

$readmeSections = [
    'Configuração' => 'Seção de configuração',
    'Cron' => 'Instruções de cron',
    'Logs' => 'Documentação de logs',
    'emails_automaticos' => 'Referência à tabela',
    'newsletter_novo_veiculo' => 'Tipo de email documentado'
];

foreach ($readmeSections as $section => $description) {
    if (stripos($readmeContent, $section) !== false) {
        echo "   ✓ $description\n";
    } else {
        echo "   ✗ $description não encontrado\n";
        exit(1);
    }
}

echo "\n7. Verificando .env.example...\n";
$envExampleContent = file_get_contents(__DIR__ . '/../.env.example');

if (strpos($envExampleContent, 'EMAIL_USUARIO') !== false && 
    strpos($envExampleContent, 'EMAIL_SENHA') !== false) {
    echo "   ✓ Variáveis de ambiente definidas\n";
} else {
    echo "   ✗ Variáveis de ambiente faltando\n";
    exit(1);
}

echo "\n8. Verificando .gitignore...\n";
if (file_exists(__DIR__ . '/../.gitignore')) {
    $gitignoreContent = file_get_contents(__DIR__ . '/../.gitignore');
    if (strpos($gitignoreContent, '.env') !== false && 
        strpos($gitignoreContent, 'vendor/') !== false &&
        strpos($gitignoreContent, 'logs/*.log') !== false) {
        echo "   ✓ .gitignore configurado corretamente\n";
    } else {
        echo "   ⚠ .gitignore pode estar incompleto\n";
    }
} else {
    echo "   ⚠ .gitignore não encontrado\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ TODOS OS TESTES PASSARAM COM SUCESSO!\n";
echo str_repeat("=", 50) . "\n\n";

echo "Próximos passos:\n";
echo "1. Configure o arquivo .env com suas credenciais\n";
echo "2. Execute o SQL em: sql/criar_tabela_emails_automaticos.sql\n";
echo "3. Configure o cron job conforme NEWSLETTER_README.md\n";
echo "4. Teste manualmente: php cron/enviar_newsletter_diario.php\n";
echo "5. Monitore os logs em: logs/newsletter_diario.log\n\n";
