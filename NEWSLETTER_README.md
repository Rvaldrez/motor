# Newsletter Diário de Novos Veículos

## Descrição

Este sistema envia automaticamente newsletters diários para usuários investidores com informações sobre novos veículos cadastrados no dia anterior.

## Funcionalidades

- **Filtro de Veículos**: Seleciona apenas veículos cadastrados no dia anterior com:
  - `status = 'completo'`
  - `em_negociacao = 0` (não em negociação)
  
- **Filtro de Usuários**: Envia emails apenas para investidores que atendem:
  - `status_cadastro = 'completo'`
  - `status_confirmacao = 'confirmado'`
  - `tipo = 'investidor'`

- **Conteúdo do Email**: 
  - Lista de veículos com foto principal (ordem_exibicao = 1)
  - Modelo do veículo
  - Ano de fabricação
  - Quilometragem (se disponível)
  - Design profissional e responsivo

- **Rastreamento**: Cada envio é registrado na tabela `emails_automaticos` com:
  - `usuario_id`: ID do destinatário
  - `tipo`: 'newsletter_novo_veiculo'
  - `data_envio`: Timestamp atual

## Estrutura de Arquivos

```
motor/
├── cron/
│   └── enviar_newsletter_diario.php  # Script principal
├── sql/
│   └── criar_tabela_emails_automaticos.sql  # Migration da tabela
├── logs/
│   └── newsletter_diario.log  # Arquivo de log (gerado automaticamente)
└── .env.example  # Template de configuração
```

## Configuração

### 1. Configurar Variáveis de Ambiente

Crie um arquivo `.env` na raiz do projeto (se ainda não existir) baseado no `.env.example`:

```bash
cp .env.example .env
```

Edite o arquivo `.env` e configure as credenciais de email:

```env
EMAIL_USUARIO=sac@motorgo.co
EMAIL_SENHA=sua_senha_aqui
```

### 2. Criar Tabela no Banco de Dados

Execute o script SQL para criar a tabela `emails_automaticos`:

```bash
mysql -u seu_usuario -p nome_do_banco < sql/criar_tabela_emails_automaticos.sql
```

Ou execute manualmente via phpMyAdmin ou outro cliente MySQL.

### 3. Configurar Permissões

Certifique-se de que o diretório `logs/` existe e tem permissões de escrita:

```bash
mkdir -p logs
chmod 755 logs
```

## Automação com Cron

### Configurar Cron Job

Para executar o script automaticamente todos os dias às 9h da manhã:

1. Abra o editor de crontab:
```bash
crontab -e
```

2. Adicione a seguinte linha:
```cron
0 9 * * * /usr/bin/php /caminho/completo/para/motor/cron/enviar_newsletter_diario.php
```

**Importante**: Substitua `/caminho/completo/para/motor` pelo caminho absoluto real do projeto no servidor.

### Exemplos de Configuração de Horários

```cron
# Todos os dias às 9h da manhã
0 9 * * * /usr/bin/php /var/www/html/motor/cron/enviar_newsletter_diario.php

# Todos os dias às 8h30
30 8 * * * /usr/bin/php /var/www/html/motor/cron/enviar_newsletter_diario.php

# De segunda a sexta às 9h
0 9 * * 1-5 /usr/bin/php /var/www/html/motor/cron/enviar_newsletter_diario.php
```

### Verificar Caminho do PHP

Para encontrar o caminho correto do PHP no servidor:

```bash
which php
```

Use o caminho retornado no cron job.

## Teste Manual

Antes de configurar o cron, teste o script manualmente:

```bash
php /caminho/completo/para/motor/cron/enviar_newsletter_diario.php
```

Verifique o arquivo de log para confirmar a execução:

```bash
cat /caminho/completo/para/motor/logs/newsletter_diario.log
```

## Logs

O sistema gera logs automáticos em `logs/newsletter_diario.log` com informações sobre:

- Início e fim da execução
- Quantidade de veículos encontrados
- Quantidade de usuários investidores
- Emails enviados com sucesso
- Emails que falharam
- Erros e exceções

### Exemplo de Log

```
[2026-01-23 09:00:01] === Início da execução do newsletter diário ===
[2026-01-23 09:00:01] Total de veículos encontrados: 3
[2026-01-23 09:00:01] Total de usuários investidores: 25
[2026-01-23 09:00:05] Email enviado com sucesso para: investidor1@email.com (ID: 15)
[2026-01-23 09:00:06] Email enviado com sucesso para: investidor2@email.com (ID: 18)
...
[2026-01-23 09:00:30] === Resumo do envio ===
[2026-01-23 09:00:30] Total de veículos: 3
[2026-01-23 09:00:30] Total de usuários: 25
[2026-01-23 09:00:30] Emails enviados: 25
[2026-01-23 09:00:30] Emails falhados: 0
[2026-01-23 09:00:30] === Fim da execução ===
```

## Monitoramento

### Verificar Última Execução do Cron

```bash
grep "newsletter" /var/log/syslog
```

ou

```bash
grep CRON /var/log/syslog | grep newsletter
```

### Verificar Emails Enviados no Banco de Dados

```sql
SELECT 
    u.nome,
    u.email,
    e.data_envio
FROM emails_automaticos e
JOIN usuarios u ON u.id = e.usuario_id
WHERE e.tipo = 'newsletter_novo_veiculo'
ORDER BY e.data_envio DESC
LIMIT 50;
```

## Solução de Problemas

### Nenhum Email Enviado

1. Verifique se existem veículos novos:
```sql
SELECT COUNT(*) 
FROM veiculos 
WHERE DATE(data_cadastro) = CURDATE() - INTERVAL 1 DAY
AND status = 'completo'
AND em_negociacao = 0;
```

2. Verifique se existem usuários investidores:
```sql
SELECT COUNT(*) 
FROM usuarios 
WHERE status_cadastro = 'completo'
AND status_confirmacao = 'confirmado'
AND tipo = 'investidor';
```

### Erro de Conexão SMTP

- Verifique as credenciais em `.env`
- Confirme que o servidor SMTP está acessível
- Verifique o firewall do servidor

### Permissões de Arquivo

Se houver erro ao escrever logs:

```bash
chmod 755 logs/
chmod 644 logs/newsletter_diario.log
```

## Segurança

- **Nunca** commite o arquivo `.env` com credenciais reais
- Use o arquivo `.env.example` como template
- Mantenha as credenciais de email seguras
- Monitore regularmente os logs de envio

## Customização

### Alterar Horário de Envio

Edite o cron job para o horário desejado.

### Alterar Template de Email

Edite o arquivo `cron/enviar_newsletter_diario.php` na seção que gera o `$mail->Body`.

### Alterar Critérios de Filtro

Modifique as queries SQL no arquivo `cron/enviar_newsletter_diario.php`.

## Suporte

Para dúvidas ou problemas, consulte os logs em `logs/newsletter_diario.log` e verifique a documentação do PHPMailer em https://github.com/PHPMailer/PHPMailer
