# Solução: Travamento no Envio de Emails

## O Problema

Ao executar a newsletter via SSH, o script travava no primeiro email:

```
Enviando 1/42: sociteblack@gmail.com (André Luis Corrêa Geraldo )... 
[TRAVOU AQUI - SEM RESPOSTA]
```

## Causa do Problema

**1. Timeout muito longo (60 segundos)**
- O script esperava 60 segundos antes de falhar
- Usuário não sabia se estava travado ou processando

**2. Sem mensagens de erro no CLI**
- Erros eram apenas salvos em arquivo de log
- Nada era exibido no terminal
- Impossível saber o que estava errado

**3. Possíveis problemas SMTP**
- Credenciais incorretas no .env
- Portas bloqueadas pelo firewall
- Servidor SMTP não respondendo
- Problemas de SSL/TLS

## Solução Implementada

### 1. Timeout Reduzido

**Antes:** 60 segundos (muito longo)  
**Depois:** 15 segundos (falha rápida)

**Arquivo:** `enviar_newsletter_diario.php` - Linha 567
```php
$mail->Timeout = 15;  // Reduzido de 60 para 15 segundos
```

**Benefício:** Erro aparece 4x mais rápido

### 2. Mensagens de Erro no CLI

**Arquivo:** `enviar_newsletter_diario.php` - Linhas 589-596

Agora mostra erro imediatamente:
```
❌ ERRO SMTP: Could not connect to SMTP host
💡 Verifique credenciais EMAIL_USUARIO e EMAIL_SENHA no arquivo .env
💡 Execute: php teste_smtp_diagnostico.php para diagnóstico completo
```

**Benefício:** Usuário vê exatamente o que está errado

### 3. Script de Diagnóstico SMTP (NOVO)

**Arquivo:** `teste_smtp_diagnostico.php`

Script completo para testar SMTP antes da newsletter:

**Funcionalidades:**
- ✅ Verifica se arquivo .env existe
- ✅ Valida credenciais EMAIL_USUARIO e EMAIL_SENHA
- ✅ Testa conexão SMTP em 3 portas (465, 587, 25)
- ✅ Envia email de teste para verificar tudo
- ✅ Mostra mensagens claras de erro
- ✅ Sugere soluções para cada problema

## Como Usar o Diagnóstico

### Passo 1: Execute o Diagnóstico

```bash
cd /home/u218663118/domains/motorgo.co/public_html
php teste_smtp_diagnostico.php
```

### Passo 2: Interprete o Resultado

**Se aparecer:**
```
====================================================
RESULTADO: ✓ SMTP FUNCIONANDO
====================================================

✅ Conexão SMTP está OK na porta 587!
✅ Credenciais estão corretas
✅ Email de teste foi enviado
```

**Ação:** Tudo OK! Pode executar a newsletter:
```bash
php enviar_newsletter_diario.php
```

---

**Se aparecer:**
```
====================================================
RESULTADO: ❌ SMTP NÃO ESTÁ FUNCIONANDO
====================================================
```

**Ação:** Siga as instruções mostradas pelo script

## Problemas Comuns e Soluções

### Problema 1: "SMTP connect() failed"

**Significado:** Não consegue conectar ao servidor SMTP

**Causas possíveis:**
- Porta 465 bloqueada pelo firewall
- Servidor SMTP incorreto
- Problema de rede

**Soluções:**
1. O script tenta automaticamente a porta 587
2. Entre em contato com seu provedor de hospedagem
3. Peça para desbloquear portas SMTP (465, 587)

### Problema 2: "Authentication failed"

**Significado:** Usuário ou senha incorretos

**Causas possíveis:**
- EMAIL_USUARIO errado no .env
- EMAIL_SENHA errada no .env
- Usando senha do email ao invés da senha SMTP

**Soluções:**
1. Verifique arquivo .env:
   ```bash
   nano .env
   ```
2. Confirme credenciais com provedor de email
3. Alguns provedores exigem "senha de aplicativo"

### Problema 3: "Connection timeout"

**Significado:** Servidor não responde

**Causas possíveis:**
- Todas as portas SMTP bloqueadas
- Firewall muito restritivo
- Servidor SMTP fora do ar

**Soluções:**
1. Verifique se internet está funcionando
2. Entre em contato com suporte da hospedagem
3. Peça para verificar firewall e portas SMTP

### Problema 4: Arquivo .env não encontrado

**Significado:** Configurações não existem

**Soluções:**
```bash
# Criar .env baseado no exemplo
cp .env.example .env

# Editar e adicionar credenciais
nano .env
```

Adicione:
```
EMAIL_USUARIO=sac@motorgo.co
EMAIL_SENHA=sua_senha_smtp_aqui
```

## Comportamento Atual

### Antes da Correção

```
Enviando 1/42: email@example.com... 
[espera 60 segundos em silêncio]
✗ Falha
[nenhum erro mostrado]

Enviando 2/42: email@example.com... 
[repete o problema]
```

### Depois da Correção

```
Enviando 1/42: email@example.com... 

   ❌ ERRO SMTP: Could not connect to SMTP host
   💡 Verifique credenciais EMAIL_USUARIO e EMAIL_SENHA no arquivo .env
   💡 Execute: php teste_smtp_diagnostico.php para diagnóstico completo

✗ Falha

Enviando 2/42: next@example.com... 
[continua com próximo investidor]
```

## Arquivos Modificados

1. **enviar_newsletter_diario.php**
   - Timeout reduzido: 60s → 15s
   - Mensagens de erro adicionadas no CLI
   - Melhor tratamento de exceções

2. **teste_smtp_diagnostico.php** (NOVO)
   - Script completo de diagnóstico
   - Testa múltiplas portas SMTP
   - Mostra soluções para cada problema

## Próximos Passos

1. **Execute o diagnóstico:**
   ```bash
   php teste_smtp_diagnostico.php
   ```

2. **Resolva problemas encontrados** (se houver)

3. **Execute a newsletter:**
   ```bash
   php enviar_newsletter_diario.php
   ```

4. **Configure o CronJob** (após confirmar que funciona)

## Logs

Erros são salvos em: `logs/email_erros.log`

Para ver últimos erros:
```bash
tail -20 logs/email_erros.log
```

## Resumo

| Aspecto | Antes | Depois |
|---------|-------|--------|
| **Timeout** | 60 segundos | 15 segundos |
| **Erro no CLI** | ❌ Não | ✅ Sim |
| **Diagnóstico** | ❌ Não | ✅ Script completo |
| **Tempo até erro** | 60s por email | 15s por email |
| **Clareza** | Baixa | Alta |

## Suporte

Se ainda tiver problemas após seguir este guia:

1. Execute o diagnóstico e copie a saída completa
2. Verifique o arquivo de log: `logs/email_erros.log`
3. Entre em contato com suporte da hospedagem
4. Tenha em mãos:
   - Saída do `teste_smtp_diagnostico.php`
   - Conteúdo de `logs/email_erros.log`
   - Informações do provedor de email

---

**Status:** ✅ Correção implementada e testada
**Versão:** 1.0 - Janeiro 2026
