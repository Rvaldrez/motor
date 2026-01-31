# ⛔ IMPORTANTE: NÃO EXECUTAR NEWSLETTER PELO NAVEGADOR!

## 🚨 PROBLEMA CRÍTICO

O script `enviar_newsletter_diario.php` **NÃO DEVE SER EXECUTADO PELO NAVEGADOR (BROWSER)**.

### O que aconteceu:

Usuário executou o script acessando:
```
http://motorgo.co/enviar_newsletter_diario.php
```

**Resultado:** Script travou após mostrar "Enviando 1/42..." e ficou assim por **12 horas**.

## Por que NÃO funciona no navegador?

### 1. ⏱️ Timeout do PHP
- **Browser:** 30-60 segundos (padrão)
- **Newsletter:** 60-90 segundos necessários
- **Resultado:** Script é morto pelo timeout antes de terminar

### 2. 🔌 Timeout do Navegador
- Navegadores fecham conexões após 2-5 minutos
- Script continua rodando mas saída não é exibida
- Parece "travado" mas pode estar executando

### 3. 📡 Timeout do Servidor Web
- Apache/Nginx têm limites de tempo (30-300s)
- Conexão é encerrada antes do script terminar
- Processo fica "órfão" no servidor

### 4. 💾 Limitações de Recursos
- Buffer de saída (output buffering) atrasa exibição
- Limites de memória mais restritivos
- Não é adequado para processamento em lote

## ✅ FORMA CORRETA: Linha de Comando (CLI)

### Passo 1: Conectar ao Servidor

```bash
ssh usuario@motorgo.co
```

### Passo 2: Navegar até o diretório

```bash
cd /home/usuario/public_html/motor
# ou
cd /caminho/completo/para/motor
```

### Passo 3: Executar o Script

```bash
php enviar_newsletter_diario.php
```

### Saída Esperada

```
====================================================
NEWSLETTER DIÁRIA - NOVOS VEÍCULOS
Início: 2026-01-29 09:00:00
====================================================

✓ Conectado ao banco de dados

Buscando veículos cadastrados ontem...
✓ Encontrados: 1 veículo(s)

Veículos encontrados:
- Fiat Strada 1.4 mpi Fire Flex 8V CE (2008)

Buscando investidores ativos...
✓ Encontrados: 42 investidor(es)

Iniciando envio de emails...
----------------------------------------------------
Enviando 1/42: sociteblack@gmail.com... ✓ Enviado
Enviando 2/42: aljrjunior@gmail.com... ✓ Enviado
Enviando 3/42: valecarlinho@gmail.com... ✓ Enviado
...
Enviando 42/42: ultimo@example.com... ✓ Enviado
----------------------------------------------------

📊 RESUMO DO ENVIO:
  ✓ Enviados com sucesso: 42
  ✗ Falhas: 0
  📧 Total de investidores: 42
  🚗 Veículos na newsletter: 1
  ⏱️  Tempo de execução: 87 segundos

====================================================
CONCLUSÃO: 2026-01-29 09:01:27
====================================================
```

## 🔧 O que Fazer se Travou no Navegador

### 1. Feche o Navegador
Simplesmente feche a aba/janela. O processo já está morto no servidor.

### 2. Verifique Logs de Erro
```bash
ssh usuario@servidor
cd /caminho/para/motor
cat logs/email_erros.log
```

### 3. Verifique Quantos Emails Foram Enviados
```bash
php recuperar_envio_newsletter.php
```

Isso mostrará:
- Quantos emails foram realmente enviados (provavelmente 0 ou 1)
- Quais investidores receberam
- Se precisa reenviar

### 4. Execute Corretamente (CLI)
```bash
php enviar_newsletter_diario.php
```

## 🤖 Automação: CronJob (Recomendado)

### cPanel

1. Acesse **cPanel → Tarefas Cron (Cron Jobs)**
2. Adicione:
   - **Minuto:** 0
   - **Hora:** 9
   - **Dia:** *
   - **Mês:** *
   - **Dia da Semana:** *
   - **Comando:** `/usr/bin/php /home/usuario/public_html/motor/enviar_newsletter_diario.php`

### Linux (crontab)

```bash
crontab -e
```

Adicione:
```bash
# Newsletter diária às 9h da manhã
0 9 * * * /usr/bin/php /caminho/completo/para/motor/enviar_newsletter_diario.php >> /var/log/newsletter.log 2>&1
```

## 🛡️ Proteções Implementadas

O script agora tem **proteção contra execução no browser**:

```php
// Bloqueia execução no browser
if (php_sapi_name() !== 'cli') {
    die('ERRO: Execute via linha de comando!');
}
```

**Resultado:** Se tentar executar no browser, verá uma mensagem de erro clara.

## 📊 Comparação: Browser vs CLI

| Aspecto | Browser ❌ | CLI ✅ |
|---------|-----------|--------|
| **Timeout** | 30-60s (trava) | Ilimitado |
| **Saída** | Bufferizada (atrasada) | Tempo real |
| **Recursos** | Limitados | Completos |
| **Confiabilidade** | Baixa (pode travar) | Alta |
| **Adequação** | Scripts curtos | Processamento longo |
| **Recomendado para Newsletter** | ❌ NUNCA | ✅ SEMPRE |

## ❓ Perguntas Frequentes

### P: Por que funcionou o teste_envio_unico.php no browser?
**R:** Porque envia apenas 1 email (~3-5 segundos). Está dentro do limite de timeout.

### P: Posso criar uma página web para disparar a newsletter?
**R:** Não recomendado. Use CronJob para automação ou SSH para execução manual.

### P: Como saber se o script está rodando?
**R:** Em CLI, você verá progresso em tempo real. No browser, você não saberá.

### P: O script travou por 12 horas. Os emails foram enviados?
**R:** Provavelmente não. O processo foi morto pelo timeout. Execute via CLI novamente.

## 📞 Suporte

Se tiver problemas:

1. Verifique os logs: `cat logs/email_erros.log`
2. Execute o script de recuperação: `php recuperar_envio_newsletter.php`
3. Certifique-se de usar CLI, não browser
4. Consulte a documentação completa em `NEWSLETTER_SETUP.md`

## ✅ Checklist Final

- [ ] Entendi que não devo usar o navegador
- [ ] Sei conectar via SSH ao servidor
- [ ] Sei executar: `php enviar_newsletter_diario.php`
- [ ] Configurei o CronJob para automação
- [ ] Testei e funcionou corretamente

---

**LEMBRE-SE:** 🚫 Browser = Travamento | ✅ CLI = Sucesso
