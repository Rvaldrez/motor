# 🧪 Guia Passo a Passo para Testar o Sistema de Newsletter

Este guia irá ajudá-lo a testar o sistema de newsletter de veículos passo a passo.

## 📋 Pré-requisitos

Antes de começar os testes, certifique-se de que você tem:
- ✅ PHP 7.4 ou superior instalado
- ✅ Acesso ao servidor/ambiente onde o sistema MotorGo está instalado
- ✅ Acesso ao banco de dados MySQL
- ✅ Credenciais de email SMTP válidas

---

## 🔧 PASSO 1: Configurar o Arquivo .env

### 1.1 Verificar se o arquivo .env existe

```bash
cd /caminho/para/motor
ls -la .env
```

**Se o arquivo NÃO existir**, crie-o:

```bash
cp .env.example .env
```

### 1.2 Editar o arquivo .env

Abra o arquivo `.env` e configure as credenciais de email:

```bash
nano .env
# ou
vi .env
# ou use seu editor preferido
```

**Conteúdo mínimo necessário do .env:**

```
# Credenciais de Email SMTP (OBRIGATÓRIO)
EMAIL_USUARIO=sac@motorgo.co
EMAIL_SENHA=sua_senha_smtp_aqui
```

**Salve o arquivo** (Ctrl+O no nano, :wq no vi)

### 1.3 Verificar permissões

```bash
chmod 600 .env  # Apenas o proprietário pode ler/escrever
```

---

## ✅ PASSO 2: Verificar Dependências do Sistema

### 2.1 Verificar se PHPMailer está instalado

```bash
cd /caminho/para/motor
ls -la vendor/phpmailer/
```

**Se NÃO existir**, instale via Composer:

```bash
composer install
# ou se não tiver composer.json
composer require phpmailer/phpmailer
composer require vlucas/phpdotenv
```

### 2.2 Verificar conexão com banco de dados

Execute este comando para testar:

```bash
php -r "require 'conexao_bd.php'; echo 'Conexão OK: ' . \$mysqli->host_info . PHP_EOL;"
```

**Resultado esperado:** 
```
Conexão OK: localhost via TCP/IP
```

---

## 🧪 PASSO 3: Teste Inicial (Sem Enviar Emails)

Este teste verifica se as queries estão funcionando SEM enviar emails reais.

### 3.1 Executar o script de teste

```bash
cd /caminho/para/motor
php teste_newsletter.php
```

### 3.2 Analisar o resultado

**O que você deve ver:**

```
====================================================
TESTE - NEWSLETTER DIÁRIA
====================================================

✓ Conectado ao banco de dados

Testando query de veículos...
✓ Query executada com sucesso
  Veículos encontrados: X veículo(s)

Detalhes dos veículos:
----------------------------------------------------
ID: 123
Veículo: Toyota Corolla (2020)
Quilometragem: 45.000 km
Preço: R$ 95.000,00
...
----------------------------------------------------

Testando query de investidores...
✓ Query executada com sucesso
  Investidores encontrados: X investidor(es)

Primeiros 5 investidores:
----------------------------------------------------
1. João Silva - joao@email.com
2. Maria Santos - maria@email.com
...
----------------------------------------------------

Verificando tabela emails_automaticos...
✓ Tabela emails_automaticos existe (ou será criada)
```

### 3.3 Possíveis Problemas e Soluções

**❌ Erro: "Fatal error: Uncaught Error: Class 'Dotenv\Dotenv' not found"**
- **Solução:** Instale a biblioteca dotenv
  ```bash
  composer require vlucas/phpdotenv
  ```

**❌ Nenhum veículo encontrado**
- **Normal!** O script busca veículos cadastrados ONTEM
- **Para testar:** Continue para o Passo 4 onde vamos simular dados

**❌ Nenhum investidor encontrado**
- **Solução:** Verifique se há usuários na tabela `usuarios` com:
  - `tipo = 'investidor'`
  - `status_confirmacao = 'confirmado'`
  - `status_cadastro = 'completo'`

---

## 🎯 PASSO 4: Teste com Dados Simulados (Opcional)

Se não houver veículos cadastrados ontem, vamos criar um teste modificado.

### 4.1 Criar script de teste com data flexível

Crie um arquivo `teste_newsletter_hoje.php`:

```bash
nano teste_newsletter_hoje.php
```

Cole este conteúdo:

```php
<?php
require_once __DIR__ . '/conexao_bd.php';

echo "====================================================\n";
echo "TESTE - VEÍCULOS DE HOJE (PARA TESTE)\n";
echo "====================================================\n\n";

// Buscar veículos de HOJE ao invés de ONTEM
$sql = "SELECT 
            v.id, v.modelo, v.marca, v.ano_fabrica, 
            v.quilometragem, v.preco, v.data_cadastro
        FROM veiculos v
        WHERE v.status = 'completo'
          AND v.em_negociacao = 0
          AND DATE(v.data_cadastro) = CURDATE()
        LIMIT 5";

$result = $mysqli->query($sql);

if ($result && $result->num_rows > 0) {
    echo "✓ Encontrados " . $result->num_rows . " veículo(s) de HOJE:\n\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['marca'] . " " . $row['modelo'] . " (" . $row['ano_fabrica'] . ")\n";
        echo "  Cadastrado em: " . $row['data_cadastro'] . "\n\n";
    }
} else {
    echo "⚠ Nenhum veículo encontrado hoje.\n";
    echo "Dica: Cadastre um veículo de teste no sistema para testar a newsletter.\n";
}

echo "====================================================\n";
?>
```

Execute:

```bash
php teste_newsletter_hoje.php
```

---

## 📧 PASSO 5: Teste de Envio Real (Cuidado!)

**⚠️ ATENÇÃO:** Este passo irá enviar emails REAIS para todos os investidores!

### 5.1 Teste com apenas 1 investidor (Recomendado)

Primeiro, vamos criar uma versão modificada que envia apenas para 1 email de teste.

Crie `teste_envio_unico.php`:

```bash
nano teste_envio_unico.php
```

Cole este conteúdo (substitua SEU_EMAIL_DE_TESTE):

```php
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexao_bd.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "====================================================\n";
echo "TESTE DE ENVIO ÚNICO\n";
echo "====================================================\n\n";

// Email de teste (MUDE AQUI PARA SEU EMAIL!)
$emailTeste = "seu_email@dominio.com";  // ⚠️ MUDE AQUI!
$nomeTeste = "Teste Newsletter";

echo "Enviando email de teste para: $emailTeste\n\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['EMAIL_USUARIO'];
    $mail->Password   = $_ENV['EMAIL_SENHA'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($_ENV['EMAIL_USUARIO'], 'MotorGo - Teste');
    $mail->addAddress($emailTeste, $nomeTeste);
    $mail->isHTML(true);
    $mail->Subject = 'Teste - Newsletter MotorGo';
    $mail->Body    = '<h1>Teste de Newsletter</h1><p>Se você recebeu este email, o sistema está funcionando!</p>';

    if ($mail->send()) {
        echo "✓ Email enviado com SUCESSO!\n";
        echo "  Verifique a caixa de entrada de: $emailTeste\n";
    }
} catch (Exception $e) {
    echo "✗ ERRO ao enviar: " . $e->getMessage() . "\n";
    echo "\nVerifique:\n";
    echo "  1. Credenciais no .env (EMAIL_USUARIO e EMAIL_SENHA)\n";
    echo "  2. Servidor SMTP está acessível\n";
    echo "  3. Porta 465 não está bloqueada no firewall\n";
}

echo "\n====================================================\n";
?>
```

**Execute:**

```bash
php teste_envio_unico.php
```

### 5.2 Verificar o email recebido

1. Abra seu email
2. Procure por "Teste - Newsletter MotorGo"
3. Se recebeu: ✅ Sistema de envio está OK!
4. Se não recebeu: Verifique spam ou veja os erros acima

---

## 🚀 PASSO 6: Teste Completo da Newsletter

**Apenas execute este passo se os testes anteriores funcionaram!**

### 6.1 Modificar temporariamente a query de data

Para testar com veículos de hoje, edite temporariamente o `enviar_newsletter_diario.php`:

```bash
nano enviar_newsletter_diario.php
```

Encontre esta linha (aproximadamente linha 76):
```php
AND DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))
```

**Mude para:**
```php
AND DATE(v.data_cadastro) = CURDATE()  -- TESTE: veículos de hoje
```

### 6.2 Executar o script completo

```bash
php enviar_newsletter_diario.php
```

### 6.3 Acompanhar o processo

Você verá:

```
====================================================
NEWSLETTER DIÁRIA - NOVOS VEÍCULOS
Início: 2026-01-26 15:30:00
====================================================

✓ Conectado ao banco de dados

Buscando veículos cadastrados ontem...
✓ Encontrados: 3 veículo(s)

Veículos encontrados:
  - Toyota Corolla (2020)
  - Honda Civic (2019)
  - Volkswagen Golf (2021)

Buscando investidores ativos...
✓ Encontrados: 15 investidor(es)

Iniciando envio de emails...
----------------------------------------------------
Enviando para: joao@email.com (João Silva)... ✓ Enviado
Enviando para: maria@email.com (Maria Santos)... ✓ Enviado
...
----------------------------------------------------

Resumo:
  ✓ Enviados com sucesso: 15
  ✗ Falhas: 0

====================================================
CONCLUSÃO: 2026-01-26 15:30:45
====================================================
```

### 6.4 Verificar logs no banco

```bash
mysql -u seu_usuario -p
```

```sql
USE u218663118_motorgo;

-- Ver últimos emails enviados
SELECT * FROM emails_automaticos 
WHERE tipo = 'newsletter_novo_veiculo' 
ORDER BY data_envio DESC 
LIMIT 10;

-- Contar por status
SELECT status, COUNT(*) as total 
FROM emails_automaticos 
WHERE tipo = 'newsletter_novo_veiculo'
GROUP BY status;
```

### 6.5 IMPORTANTE: Reverter a mudança de teste

**Não esqueça de voltar a query para o original!**

```bash
nano enviar_newsletter_diario.php
```

Volte para:
```php
AND DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))
```

---

## 📅 PASSO 7: Agendar para Produção (CronJob)

**Apenas faça isso após todos os testes passarem!**

### 7.1 Abrir o crontab

```bash
crontab -e
```

### 7.2 Adicionar a tarefa

Cole esta linha no final do arquivo:

```bash
# Newsletter diária MotorGo - Executa às 9:00 AM todos os dias
0 9 * * * /usr/bin/php /caminho/completo/para/motor/enviar_newsletter_diario.php >> /var/log/newsletter_motorgo.log 2>&1
```

**⚠️ IMPORTANTE:** Substitua `/caminho/completo/para/motor` pelo caminho real!

Para descobrir o caminho:
```bash
cd /caminho/para/motor
pwd  # Mostra o caminho completo
```

### 7.3 Verificar se foi adicionado

```bash
crontab -l
```

### 7.4 Testar o cron manualmente

```bash
# Executar o comando que o cron vai executar
/usr/bin/php /caminho/completo/para/motor/enviar_newsletter_diario.php
```

---

## 📊 PASSO 8: Monitoramento Contínuo

### 8.1 Verificar logs diários

```bash
tail -f /var/log/newsletter_motorgo.log
```

### 8.2 Criar script de monitoramento

Crie `verificar_newsletter.sh`:

```bash
#!/bin/bash
echo "=== Status Newsletter MotorGo ==="
echo ""
echo "Últimos envios (últimas 24h):"
mysql -u usuario -p -e "SELECT DATE(data_envio) as data, status, COUNT(*) as total FROM u218663118_motorgo.emails_automaticos WHERE tipo='newsletter_novo_veiculo' AND data_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY DATE(data_envio), status;"
echo ""
echo "Último envio:"
mysql -u usuario -p -e "SELECT * FROM u218663118_motorgo.emails_automaticos WHERE tipo='newsletter_novo_veiculo' ORDER BY data_envio DESC LIMIT 1;"
```

Dê permissão e execute:
```bash
chmod +x verificar_newsletter.sh
./verificar_newsletter.sh
```

---

## ❓ Solução de Problemas Comuns

### Problema 1: "Class 'Dotenv\Dotenv' not found"
**Solução:**
```bash
composer require vlucas/phpdotenv
```

### Problema 2: "SMTP connect() failed"
**Verificar:**
1. Credenciais no .env estão corretas?
2. Firewall permite porta 465?
3. Servidor SMTP está acessível?

```bash
# Testar conexão SMTP
telnet smtp.hostinger.com 465
```

### Problema 3: Emails não chegam
**Verificar:**
1. Spam/lixo eletrônico
2. Logs de erro: `logs/email_erros.log`
3. Status na tabela emails_automaticos

### Problema 4: "Nenhum veículo encontrado"
**Normal!** O script busca veículos de ONTEM. Para testar:
- Use o `teste_newsletter_hoje.php` criado no Passo 4
- Ou cadastre um veículo e aguarde até amanhã

---

## ✅ Checklist Final

Antes de colocar em produção, confirme:

- [ ] Arquivo .env configurado com credenciais corretas
- [ ] PHPMailer instalado (vendor/phpmailer existe)
- [ ] Teste de conexão com banco passou
- [ ] teste_newsletter.php executou sem erros
- [ ] Teste de envio único funcionou
- [ ] Email de teste foi recebido
- [ ] Newsletter completa enviou corretamente
- [ ] Logs estão sendo gravados na tabela emails_automaticos
- [ ] CronJob foi configurado
- [ ] Query foi revertida para buscar veículos de ONTEM

---

## 📞 Suporte

Se tiver problemas:

1. Verifique os logs: `logs/email_erros.log`
2. Execute `teste_newsletter.php` para diagnóstico
3. Verifique a tabela `emails_automaticos` no banco

**Arquivos importantes:**
- `/caminho/para/motor/enviar_newsletter_diario.php` - Script principal
- `/caminho/para/motor/teste_newsletter.php` - Teste sem enviar
- `/caminho/para/motor/.env` - Credenciais (NÃO versionar!)
- `/caminho/para/motor/logs/email_erros.log` - Erros de envio

---

**Versão do Guia:** 1.0  
**Data:** Janeiro 2026  
**Sistema:** MotorGo Newsletter
