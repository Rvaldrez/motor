# Guia - Interface Web de Newsletter

## 📧 Sistema de Envio Manual via Navegador

A interface web permite enviar a newsletter manualmente pelo navegador, sem necessidade de SSH ou linha de comando.

---

## 🌐 Como Acessar

**URL**: `http://motorgo.co/enviar_newsletter_web.php`

Ou: `https://motorgo.co/enviar_newsletter_web.php`

---

## 🎯 Funcionalidades

### 1. ✅ Visualização de Informações

Ao acessar a página, você vê:

- **🚗 Veículos novos (Últimas 24h)**: Quantidade de veículos cadastrados ontem
- **📋 Cadastros recentes**: Quantidade de veículos de dias anteriores
- **📦 Total de veículos**: Soma total
- **📧 Investidores ativos**: Quantos receberão o email
- **⏱️ Tempo estimado**: Quanto tempo levará o envio

### 2. 👁️ Preview do Email

**Botão**: "👁️ Visualizar Preview do Email"

**O que faz**:
- Abre uma janela modal (popup)
- Mostra exatamente como o email aparecerá
- Exibe todas as seções (novos veículos + recentes)
- Usa nome "Investidor Exemplo" como exemplo

**Como usar**:
1. Clique no botão "👁️ Visualizar Preview do Email"
2. Revise o conteúdo do email
3. Feche o preview de 3 formas:
   - Clique no **X** no canto superior direito
   - Pressione a tecla **ESC**
   - Clique fora da janela (na área escura)

**Benefícios**:
- ✅ Confere se os veículos estão corretos
- ✅ Verifica o layout antes de enviar
- ✅ Evita erros em envios massivos
- ✅ Garante qualidade do email

### 3. 🚀 Envio com Acompanhamento

**Botão**: "🚀 Enviar Newsletter Agora"

**O que acontece**:
1. **Confirmação**: Popup pergunta "Confirma o envio para X investidor(es)?"
2. **Início**: Página muda para modo de envio
3. **Progresso em tempo real**:
   - Barra de progresso visual (0% → 100%)
   - Console com log detalhado
   - Contador: "Enviando 1/42", "2/42", etc.
   - Status: ✓ (sucesso) ou ✗ (falha)

**Exemplo de saída**:
```
════════════════════════════════════════════════════
MOTORGO - ENVIO DE NEWSLETTER
Início: 2026-01-30 14:20:00
════════════════════════════════════════════════════

🔍 Buscando veículos cadastrados ontem...
✓ Encontrados: 1 veículo(s) das últimas 24h
  - Renault Clio Hi-Flex 1.0 16V 5p (2012)

🔍 Buscando cadastros recentes...
✓ Encontrados: 4 veículo(s) recentes

🔍 Buscando investidores ativos...
✓ Encontrados: 42 investidor(es)

📧 Iniciando envio de emails...
────────────────────────────────────────────────────
Enviando 1/42: sociteblack@gmail.com (André Luis)...
  ✓ Enviado com sucesso!
Enviando 2/42: aljrjunior@gmail.com (Antonio Lopes)...
  ✓ Enviado com sucesso!
...
Enviando 42/42: ultimo@example.com (Nome)...
  ✓ Enviado com sucesso!
────────────────────────────────────────────────────

✅ ENVIO CONCLUÍDO!
Fim: 2026-01-30 14:21:27
```

### 4. 📊 Resumo Final

Após o envio completo, aparece um resumo:

```
📊 Resumo do Envio
✅ Enviados com sucesso: 42
❌ Falhas: 0
📧 Total de investidores: 42
🚗 Veículos novos (24h): 1
📋 Cadastros recentes: 4
⏱️ Tempo de execução: 87 segundos
```

---

## 📋 Passo a Passo Completo

### Passo 1: Acessar a Interface
```
http://motorgo.co/enviar_newsletter_web.php
```

### Passo 2: Revisar Informações
- Confira quantos veículos serão enviados
- Veja quantos investidores receberão
- Verifique o tempo estimado

### Passo 3: Visualizar Preview (RECOMENDADO)
1. Clique em "👁️ Visualizar Preview do Email"
2. Revise todo o conteúdo do email
3. Confira se os veículos estão corretos
4. Verifique o layout e formatação
5. Feche o preview (X, ESC, ou clique fora)

### Passo 4: Enviar Newsletter
1. Clique em "🚀 Enviar Newsletter Agora"
2. Confirme no popup que aparece
3. Aguarde o processo (acompanhe em tempo real)
4. Não feche a página durante o envio!

### Passo 5: Verificar Resultado
1. Veja o resumo final
2. Confira se houve falhas
3. Se necessário, verifique os emails recebidos

---

## ⚠️ Situações Especiais

### Sem Veículos Novos (24h)

Se não houver veículos cadastrados nas últimas 24h:

**Aparece**:
```
⚠️ Atenção: Não há veículos novos cadastrados nas últimas 24 horas.

A newsletter NÃO será enviada porque o requisito é ter pelo menos 
1 veículo cadastrado nas últimas 24h.
```

**Botões**:
- ❌ "Visualizar Preview" - Desabilitado
- ❌ "Enviar Newsletter Agora" - Desabilitado

**Solução**: Aguarde até que um novo veículo seja cadastrado.

### Sem Investidores Ativos

Se não houver investidores no sistema:

**Aparece**:
```
Não há investidores ativos no sistema
```

**Botões**: Desabilitados

**Solução**: Cadastre investidores no sistema.

### Falhas no Envio

Se algum email falhar:

**Aparece no log**:
```
Enviando 5/42: email@example.com...
  ✗ Falha no envio
```

**No resumo**:
```
✅ Enviados com sucesso: 41
❌ Falhas: 1
```

**O que fazer**:
1. Anote qual email falhou
2. Verifique se o email está correto no cadastro
3. Tente novamente mais tarde (o script continuará enviando para os demais)

---

## 🎨 Interface Visual

### Cores e Indicadores

**Cores**:
- 🟢 **Verde**: Sucesso (✓)
- 🔴 **Vermelho**: Erro (✗)
- 🟡 **Amarelo**: Informação
- 🔵 **Azul/Roxo**: Fundo da página

**Barra de Progresso**:
- Vai de 0% a 100%
- Cor vermelho/laranja (MotorGo)
- Mostra porcentagem exata

**Console de Log**:
- Fundo preto estilo terminal
- Texto colorido (verde/vermelho/amarelo)
- Auto-scroll para última linha
- Rolagem manual disponível

---

## 💡 Dicas de Uso

### ✅ Boas Práticas

1. **Sempre visualize o preview** antes de enviar
2. **Não feche a página** durante o envio
3. **Aguarde o resumo final** para confirmar
4. **Anote horário** de cada envio
5. **Verifique inbox** após envio

### ❌ Evite

1. **Não clique duas vezes** no botão de enviar
2. **Não atualize a página** durante envio
3. **Não feche o navegador** antes de terminar
4. **Não envie múltiplas vezes** no mesmo dia

### 🔧 Resolução de Problemas

**Página não carrega**:
- Verifique a URL
- Teste conexão com internet
- Limpe cache do navegador

**Botão desabilitado**:
- Verifique se há veículos novos (24h)
- Confirme que há investidores ativos

**Envio travado**:
- Aguarde pelo menos 5 minutos
- Verifique conexão SMTP
- Execute `teste_smtp_diagnostico.php`

**Muitas falhas**:
- Verifique credenciais SMTP no .env
- Confira emails dos investidores
- Teste com `teste_envio_unico.php`

---

## 🔒 Segurança

### Acesso

- **URL pública**: Qualquer pessoa com a URL pode acessar
- **Recomendação**: Adicione autenticação se necessário
- **Logs**: Todos os envios são registrados no banco

### Dados Sensíveis

- **Credenciais SMTP**: Armazenadas em arquivo .env (seguro)
- **Emails investidores**: Vêm do banco de dados
- **Logs**: Salvos em `logs/email_erros.log`

---

## 📱 Responsividade

A interface funciona em:

- ✅ **Desktop** (recomendado)
- ✅ **Tablet** (funciona bem)
- ⚠️ **Mobile** (funciona, mas melhor em desktop)

**Recomendação**: Use em desktop para melhor experiência.

---

## 🤖 Automação Futura

Esta interface manual pode ser automatizada:

### Opção 1: CronJob no Servidor
```bash
0 9 * * * /usr/bin/php /caminho/enviar_newsletter_diario.php
```

### Opção 2: Web Cron
- Use serviços como cron-job.org
- Configure para acessar: `motorgo.co/enviar_newsletter_web.php?acao=enviar`
- Defina horário diário

### Opção 3: Script Agendado
- Mantenha interface manual para testes
- Use CLI para automação (mais confiável)

---

## 📞 Suporte

### Documentação Relacionada

- `LEIA-ME_NEWSLETTER.md` - Guia principal
- `NEWSLETTER_SETUP.md` - Setup do sistema
- `SOLUCAO_TRAVAMENTO_EMAIL.md` - Se travar
- `FAQ_ENVIO_NEWSLETTER.md` - Perguntas frequentes

### Testes

- `teste_smtp_diagnostico.php` - Testa SMTP
- `teste_envio_unico.php` - Testa 1 email
- `preview_newsletter.php` - Preview local

---

## ✅ Checklist de Uso

Antes de enviar:
- [ ] Acessei a interface web
- [ ] Revisei as informações (veículos, investidores)
- [ ] Visualizei o preview do email
- [ ] Confirmei que está tudo correto
- [ ] Cliquei em "Enviar Newsletter Agora"
- [ ] Confirmei no popup
- [ ] Aguardei o envio completo
- [ ] Verifiquei o resumo final
- [ ] Anotei resultado (sucessos/falhas)

---

## 🎉 Resumo

**URL**: `http://motorgo.co/enviar_newsletter_web.php`

**Funcionalidades**:
1. ✅ Visualização de informações
2. 👁️ Preview do email
3. 🚀 Envio com progresso em tempo real
4. 📊 Resumo estatístico

**Vantagens**:
- Sem necessidade de SSH
- Interface visual amigável
- Acompanhamento em tempo real
- Preview antes de enviar
- Fácil de usar

**Status**: 100% Funcional e Pronto para Uso! 🎉
