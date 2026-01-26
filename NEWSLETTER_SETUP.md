# Configuração da Newsletter Diária de Veículos

## 📋 Visão Geral

Este documento descreve como configurar e agendar o envio automático de newsletters diárias sobre novos veículos cadastrados para investidores.

## 🎯 Funcionalidades

O script `enviar_newsletter_diario.php` realiza as seguintes tarefas:

1. **Filtra veículos novos**: Busca veículos cadastrados no dia anterior com:
   - `status = 'completo'`
   - `em_negociacao = 0`

2. **Busca investidores ativos**: Filtra usuários com:
   - `tipo = 'investidor'`
   - `status_confirmacao = 'confirmado'`
   - `status_cadastro = 'completo'`

3. **Envia emails personalizados**: Com informações detalhadas dos veículos incluindo:
   - Foto principal do veículo
   - Marca e modelo
   - Ano de fabricação
   - Quilometragem
   - Localização
   - Valor FIPE

4. **Registra envios**: Armazena logs na tabela `newsletter` com:
   - `tipo = 'newsletter_novo_veiculo'`
   - Status do envio (enviado/erro)
   - Quantidade de veículos enviados

## ⚙️ Requisitos

### Dependências PHP
- PHP 7.4 ou superior
- Extensões: `mysqli`, `mbstring`
- PHPMailer (via Composer)

### Banco de Dados
- MySQL 5.7 ou superior
- Tabela `newsletter` (criada automaticamente pelo script)

### Servidor SMTP
- Credenciais válidas de servidor SMTP
- Porta adequada (587 para TLS, 465 para SSL)

## 🔧 Configuração

### Passo 1: Configurar Credenciais

O script utiliza os arquivos de configuração existentes do sistema:
- **Banco de Dados**: `conexao_bd.php` (já configurado)
- **Email SMTP**: Arquivo `.env` com variáveis `EMAIL_USUARIO` e `EMAIL_SENHA`

#### Configurar arquivo .env

Crie ou edite o arquivo `.env` na raiz do projeto com as credenciais de email:

```
EMAIL_USUARIO=seu_email@dominio.com
EMAIL_SENHA=sua_senha_smtp
```

**Exemplo completo do .env:**
```
# Credenciais de Email SMTP
EMAIL_USUARIO=sac@motorgo.co
EMAIL_SENHA=sua_senha_smtp_aqui

# Configurações opcionais
BASE_URL=https://motorgo.co
EMAIL_SUBJECT=Novos Veículos Disponíveis - MotorGo
```

> **Nota**: O arquivo `.env` não deve ser versionado no Git. Já existe um `.env.example` como template.

### Passo 2: Testar o Script Manualmente

Antes de agendar, teste o script manualmente:

```bash
cd /caminho/para/motor
php enviar_newsletter_diario.php
```

Você verá uma saída similar a:

```
====================================================
NEWSLETTER DIÁRIA - NOVOS VEÍCULOS
Início: 2026-01-26 09:00:00
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
Enviando para: investidor1@email.com (João Silva)... ✓ Enviado
Enviando para: investidor2@email.com (Maria Santos)... ✓ Enviado
...
----------------------------------------------------

Resumo:
  ✓ Enviados com sucesso: 15
  ✗ Falhas: 0

====================================================
CONCLUSÃO: 2026-01-26 09:05:12
====================================================
```

## 📅 Agendamento via CronJob

### Linux / macOS

#### Método 1: Editor Crontab

1. Abra o editor crontab:
```bash
crontab -e
```

2. Adicione a seguinte linha para executar diariamente às 9:00 AM:
```bash
0 9 * * * /usr/bin/php /caminho/completo/para/motor/enviar_newsletter_diario.php >> /var/log/newsletter_diario.log 2>&1
```

3. Salve e feche o editor.

#### Método 2: Arquivo Crontab do Sistema

Crie um arquivo em `/etc/cron.d/newsletter-motorgo`:

```bash
# Newsletter Diária MotorGo - Executa às 9:00 AM todos os dias
0 9 * * * www-data /usr/bin/php /var/www/html/motor/enviar_newsletter_diario.php >> /var/log/newsletter_diario.log 2>&1
```

**Observações:**
- Substitua `/usr/bin/php` pelo caminho correto do PHP (encontre com `which php`)
- Substitua `/caminho/completo/para/motor` pelo caminho real do projeto
- Substitua `www-data` pelo usuário do servidor web (pode ser `apache`, `nginx`, etc.)
- Os logs serão salvos em `/var/log/newsletter_diario.log`

### Windows (Task Scheduler)

1. Abra o **Agendador de Tarefas** (Task Scheduler)

2. Clique em **Criar Tarefa Básica**

3. Configure:
   - **Nome**: Newsletter Diária MotorGo
   - **Descrição**: Envia newsletter sobre novos veículos para investidores
   
4. **Gatilho**: 
   - Diariamente
   - Hora: 09:00:00
   - Recorrente a cada 1 dia

5. **Ação**: 
   - Iniciar um programa
   - Programa/script: `C:\php\php.exe`
   - Argumentos: `C:\caminho\para\motor\enviar_newsletter_diario.php`
   - Iniciar em: `C:\caminho\para\motor`

6. Salve a tarefa

### Hospedagem Compartilhada (cPanel)

1. Acesse o **cPanel** da sua hospedagem

2. Localize **Tarefas Cron** (Cron Jobs)

3. Configure:
   - **Minuto**: 0
   - **Hora**: 9
   - **Dia**: *
   - **Mês**: *
   - **Dia da Semana**: *
   - **Comando**: `/usr/bin/php /home/usuario/public_html/motor/enviar_newsletter_diario.php`

4. Clique em **Adicionar Nova Tarefa Cron**

## 🔍 Verificação e Monitoramento

### Verificar se o CronJob está ativo (Linux)

```bash
crontab -l
```

### Verificar logs de execução

```bash
tail -f /var/log/newsletter_diario.log
```

### Monitorar envios no banco de dados

```sql
-- Ver últimos 20 envios
SELECT * FROM newsletter 
ORDER BY data_envio DESC 
LIMIT 20;

-- Contar envios por dia
SELECT DATE(data_envio) as data, 
       COUNT(*) as total,
       SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as sucessos,
       SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as falhas,
       AVG(veiculos_enviados) as media_veiculos
FROM newsletter
GROUP BY DATE(data_envio)
ORDER BY data DESC;

-- Investidores mais ativos
SELECT email, COUNT(*) as total_recebidos
FROM newsletter
WHERE status = 'enviado'
GROUP BY email
ORDER BY total_recebidos DESC
LIMIT 10;
```

## 🎨 Personalização do Email

### Modificar o design do email

O template HTML está na função `gerarHTMLEmail()`. Para personalizar:

1. Abra `enviar_newsletter_diario.php`
2. Localize a função `gerarHTMLEmail()`
3. Modifique o HTML e CSS inline conforme necessário

### Alterar o horário de envio

Modifique o CronJob para o horário desejado:

```bash
# Para enviar às 8:00 AM
0 8 * * * /usr/bin/php /caminho/para/enviar_newsletter_diario.php

# Para enviar às 14:00 (2:00 PM)
0 14 * * * /usr/bin/php /caminho/para/enviar_newsletter_diario.php

# Para enviar duas vezes por dia (9 AM e 6 PM)
0 9,18 * * * /usr/bin/php /caminho/para/enviar_newsletter_diario.php
```

### Modificar o assunto do email

Edite a constante no arquivo:

```php
define('EMAIL_SUBJECT', 'Seu novo assunto aqui');
```

## ❌ Desativar a Newsletter

### Temporariamente

Comente a linha no crontab:

```bash
crontab -e
# Adicione # no início da linha
# 0 9 * * * /usr/bin/php /caminho/para/enviar_newsletter_diario.php
```

### Permanentemente

Remova a linha do crontab:

```bash
crontab -e
# Delete a linha completamente e salve
```

## 🐛 Solução de Problemas

### Email não está sendo enviado

1. **Verifique as credenciais SMTP**:
   - Confirme usuário e senha
   - Verifique se o host e porta estão corretos
   - Teste manualmente com um cliente de email

2. **Verifique os logs**:
   ```bash
   tail -n 50 /var/log/newsletter_diario.log
   ```

3. **Teste o script manualmente**:
   ```bash
   php enviar_newsletter_diario.php
   ```

### CronJob não está executando

1. **Verifique se o cron está rodando**:
   ```bash
   systemctl status cron  # Ubuntu/Debian
   systemctl status crond # CentOS/RHEL
   ```

2. **Verifique os logs do sistema**:
   ```bash
   grep CRON /var/log/syslog
   ```

3. **Teste o caminho do PHP**:
   ```bash
   which php
   /usr/bin/php -v
   ```

### Nenhum veículo encontrado

- Verifique se há veículos cadastrados no dia anterior
- Confirme os filtros: `status='completo'` e `em_negociacao=0`
- Execute a query SQL manualmente para debug:

```sql
SELECT COUNT(*) FROM veiculos 
WHERE status = 'completo' 
  AND em_negociacao = 0 
  AND DATE(data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY));
```

### Nenhum investidor encontrado

- Verifique se há investidores no sistema
- Confirme os filtros no banco de dados:

```sql
SELECT COUNT(*) FROM usuarios 
WHERE tipo = 'investidor' 
  AND status_confirmacao = 'confirmado' 
  AND status_cadastro = 'completo';
```

## 📊 Estrutura da Tabela newsletter

A tabela é criada automaticamente pelo script com a seguinte estrutura:

```sql
CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    veiculos_enviados INT DEFAULT 0,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    erro_mensagem TEXT NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_data (data_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos:**
- `id`: Identificador único
- `usuario_id`: ID do investidor
- `email`: Email do destinatário
- `assunto`: Assunto do email enviado
- `status`: 'enviado' ou 'erro'
- `veiculos_enviados`: Quantidade de veículos no email
- `data_envio`: Data/hora do envio
- `erro_mensagem`: Mensagem de erro (se houver)

## 📝 Notas Importantes

1. **Segurança**: Nunca versione o arquivo com credenciais reais. Use variáveis de ambiente em produção.

2. **Performance**: O script adiciona um `sleep(1)` entre envios para evitar sobrecarga do servidor SMTP.

3. **Logs**: Mantenha os logs por tempo limitado para não ocupar espaço em disco.

4. **Teste antes de agendar**: Sempre teste o script manualmente antes de agendar via CronJob.

5. **Backup**: Faça backup regular da tabela `newsletter` para auditoria.

## 📞 Suporte

Para dúvidas ou problemas, entre em contato com a equipe de desenvolvimento MotorGo.

---

**Versão**: 1.0  
**Última atualização**: Janeiro 2026  
**Autor**: MotorGo Development Team
