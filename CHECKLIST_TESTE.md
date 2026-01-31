# ✅ Checklist de Teste - Newsletter MotorGo

Use este checklist para garantir que todos os passos foram seguidos corretamente.

## Configuração Inicial

- [ ] Arquivo `.env` foi criado na raiz do projeto
- [ ] Credenciais `EMAIL_USUARIO` e `EMAIL_SENHA` foram configuradas no `.env`
- [ ] PHPMailer está instalado (pasta `vendor/phpmailer` existe)
- [ ] Biblioteca Dotenv está instalada (para carregar .env)
- [ ] Conexão com banco de dados está funcionando

**Comando para verificar:**
```bash
ls -la .env vendor/phpmailer vendor/vlucas
php -r "require 'conexao_bd.php'; echo 'OK\n';"
```

---

## Testes Básicos

- [ ] `teste_newsletter.php` executou sem erros
- [ ] Query de veículos retornou resultados (ou confirmado que não há veículos de ontem)
- [ ] Query de investidores retornou resultados
- [ ] Tabela `emails_automaticos` existe ou foi criada

**Comando:**
```bash
php teste_newsletter.php
```

---

## Teste de Veículos de Hoje (Opcional)

- [ ] `teste_newsletter_hoje.php` foi executado
- [ ] Foram encontrados veículos cadastrados hoje (se houver)
- [ ] Status dos veículos está correto (completo, em_negociacao=0)

**Comando:**
```bash
php teste_newsletter_hoje.php
```

---

## Teste de Envio de Email

- [ ] Arquivo `teste_envio_unico.php` foi editado (alterado o email de destino)
- [ ] Script foi executado sem erros
- [ ] Email de teste foi RECEBIDO na caixa de entrada
- [ ] Email não foi para SPAM
- [ ] Layout do email está correto (HTML renderizado)

**Comando:**
```bash
# ANTES: editar teste_envio_unico.php linha 18
nano teste_envio_unico.php  # alterar $emailTeste
php teste_envio_unico.php
```

---

## Teste Completo da Newsletter

### Preparação para Teste

- [ ] Há veículos disponíveis para teste (hoje ou modificar query temporariamente)
- [ ] Há investidores cadastrados corretamente no sistema
- [ ] Backup do banco foi feito (recomendado)

### Modificação Temporária (se necessário)

- [ ] Linha 76 do `enviar_newsletter_diario.php` foi modificada para buscar veículos de HOJE
- [ ] Query modificada: `AND DATE(v.data_cadastro) = CURDATE()`
- [ ] **LEMBRETE**: Reverter após o teste!

### Execução

- [ ] Script `enviar_newsletter_diario.php` foi executado
- [ ] Emails foram enviados com sucesso
- [ ] Nenhum erro foi reportado
- [ ] Resumo final mostra envios bem-sucedidos

**Comando:**
```bash
php enviar_newsletter_diario.php
```

### Verificação Pós-Envio

- [ ] Emails foram recebidos pelos destinatários
- [ ] Layout está correto e profissional
- [ ] Links e imagens estão funcionando
- [ ] Registros foram salvos na tabela `emails_automaticos`

**Verificar no banco:**
```sql
SELECT * FROM emails_automaticos 
WHERE tipo = 'newsletter_novo_veiculo' 
ORDER BY data_envio DESC LIMIT 10;
```

### Reversão

- [ ] Query foi revertida para buscar veículos de ONTEM
- [ ] Linha 76: `AND DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))`
- [ ] Arquivo foi salvo

---

## Configuração de Produção (CronJob)

- [ ] Caminho completo do PHP foi identificado: `which php`
- [ ] Caminho completo do script foi identificado: `pwd`
- [ ] CronJob foi adicionado com: `crontab -e`
- [ ] Linha correta foi adicionada: `0 9 * * * /usr/bin/php /caminho/script.php >> /var/log/newsletter.log 2>&1`
- [ ] CronJob foi listado e está correto: `crontab -l`

---

## Monitoramento

- [ ] Log de execução está sendo gerado: `/var/log/newsletter_motorgo.log`
- [ ] Log pode ser visualizado: `tail -f /var/log/newsletter_motorgo.log`
- [ ] Tabela `emails_automaticos` está registrando os envios
- [ ] Erros de email estão sendo logados em: `logs/email_erros.log`

---

## Validação Final

- [ ] Newsletter foi enviada pelo menos 1 vez com sucesso
- [ ] Investidores receberam os emails
- [ ] Não há reclamações de spam
- [ ] Sistema está agendado para rodar diariamente
- [ ] Documentação foi revisada e está clara
- [ ] Equipe foi treinada para monitorar o sistema

---

## Em Caso de Problemas

### Email não envia
- [ ] Verificado credenciais no `.env`
- [ ] Testado conexão SMTP: `telnet smtp.hostinger.com 465`
- [ ] Verificado firewall não bloqueia porta 465
- [ ] Consultado `logs/email_erros.log`

### Nenhum veículo encontrado
- [ ] Confirmado que é normal (busca veículos de ontem)
- [ ] Verificado se há veículos com status='completo' e em_negociacao=0
- [ ] Testado com `teste_newsletter_hoje.php` para veículos de hoje

### Nenhum investidor encontrado
- [ ] Verificado tabela `usuarios` tem investidores
- [ ] Confirmado filtros: tipo='investidor', status_confirmacao='confirmado', status_cadastro='completo'
- [ ] Cadastrado investidor de teste se necessário

---

## Data do Último Teste

**Data:** ___/___/______  
**Testado por:** _______________________  
**Status:** [ ] Aprovado  [ ] Com pendências  
**Observações:**
```
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

**Sistema:** MotorGo Newsletter Daily  
**Versão:** 1.0  
**Última atualização:** Janeiro 2026
