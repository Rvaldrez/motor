# 📧 Nova Estrutura da Newsletter - Duas Seções

## Visão Geral

A newsletter agora possui **duas seções distintas** de veículos para maximizar as oportunidades de investimento.

## 📊 Estrutura do Email

```
┌─────────────────────────────────────────────────────────────┐
│                    [Logo MotorGo]                           │
│              Novos Veículos Disponíveis                     │
└─────────────────────────────────────────────────────────────┘

  👋 Olá, [Nome do Investidor]!
  
  Confira os veículos cadastrados nas últimas 24 horas. 
  Faça a sua oferta e garanta a oportunidade de lucrar 
  na revenda!

┌═════════════════════════════════════════════════════════════┐
║ 🚗 NOVOS VEÍCULOS (Últimas 24 horas)                       ║
└─────────────────────────────────────────────────────────────┘

   ┌─────────────┐  ┌─────────────┐
   │ [Imagem]    │  │ [Imagem]    │
   │ Veículo 1   │  │ Veículo 2   │
   │ Ano, KM     │  │ Ano, KM     │
   │ Localização │  │ Localização │
   │ [Ver +]     │  │ [Ver +]     │
   └─────────────┘  └─────────────┘

┌═════════════════════════════════════════════════════════════┐
║ 📋 CADASTROS RECENTES                                       ║
└─────────────────────────────────────────────────────────────┘

   ┌─────────────┐  ┌─────────────┐
   │ [Imagem]    │  │ [Imagem]    │
   │ Veículo 3   │  │ Veículo 4   │
   │ Ano, KM     │  │ Ano, KM     │
   │ Localização │  │ Localização │
   │ [Ver +]     │  │ [Ver +]     │
   └─────────────┘  └─────────────┘
   
   ┌─────────────┐  ┌─────────────┐
   │ [Imagem]    │  │ [Imagem]    │
   │ Veículo 5   │  │ Veículo 6   │
   │ Ano, KM     │  │ Ano, KM     │
   │ Localização │  │ Localização │
   │ [Ver +]     │  │ [Ver +]     │
   └─────────────┘  └─────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    Footer - MotorGo                         │
│                  contato@motorgo.co                         │
│                   © 2026 MotorGo                            │
└─────────────────────────────────────────────────────────────┘
```

## 🎯 Seção 1: Novos Veículos (Últimas 24h)

### Critérios
- ✅ Cadastrados nas **últimas 24 horas**
- ✅ Status = 'completo'
- ✅ Não em negociação (em_negociacao = 0)
- ✅ Ordenados por data de cadastro (mais recentes primeiro)

### Visual
- **Header**: Fundo preto (#1a1a1a) com borda vermelha
- **Ícone**: 🚗
- **Título**: "Novos Veículos (Últimas 24 horas)"

### Comportamento
- **Se houver veículos**: Mostra todos os cadastrados ontem
- **Se não houver**: Seção não aparece

## 📋 Seção 2: Cadastros Recentes (NOVO!)

### Critérios
- ✅ Os **4 veículos mais recentes** do sistema
- ✅ Status = 'completo'
- ✅ Não em negociação (em_negociacao = 0)
- ✅ **Exclui** veículos já mostrados na Seção 1
- ✅ Ordenados por data de cadastro (mais recentes primeiro)
- ✅ Limite de 4 veículos

### Visual
- **Header**: Fundo cinza escuro (#333333) com borda vermelha
- **Ícone**: 📋
- **Título**: "Cadastros Recentes"

### Comportamento
- **Se houver veículos**: Mostra até 4 veículos recentes
- **Se não houver**: Seção não aparece

## 📱 Responsividade

### Desktop (> 600px)
```
┌─────────────┐  ┌─────────────┐
│  Veículo 1  │  │  Veículo 2  │
└─────────────┘  └─────────────┘
┌─────────────┐  ┌─────────────┐
│  Veículo 3  │  │  Veículo 4  │
└─────────────┘  └─────────────┘
```
**2 colunas** por linha

### Mobile (≤ 600px)
```
┌─────────────────┐
│    Veículo 1    │
└─────────────────┘
┌─────────────────┐
│    Veículo 2    │
└─────────────────┘
┌─────────────────┐
│    Veículo 3    │
└─────────────────┘
```
**1 coluna** (largura total)

## 🔄 Cenários de Uso

### Cenário 1: Vários Veículos Novos
**Situação**: 3 veículos cadastrados ontem
```
📊 Resultado:
  🚗 Novos Veículos (24h): 3
  📋 Cadastros Recentes: 4 (excluindo os 3 acima)
  📦 Total: 7 veículos
```

### Cenário 2: Nenhum Veículo Novo
**Situação**: 0 veículos cadastrados ontem
```
📊 Resultado:
  🚗 Novos Veículos (24h): 0 (seção não aparece)
  📋 Cadastros Recentes: 4
  📦 Total: 4 veículos
```

### Cenário 3: Poucos Veículos no Sistema
**Situação**: Apenas 2 veículos no sistema total
```
📊 Resultado:
  🚗 Novos Veículos (24h): 1 (se cadastrado ontem)
  📋 Cadastros Recentes: 1 (o outro veículo)
  📦 Total: 2 veículos
```

### Cenário 4: Nenhum Veículo Disponível
**Situação**: Sistema sem veículos completos
```
📊 Resultado:
  ⚠️  Mensagem: "Nenhum veículo disponível no momento.
      Fique atento às próximas oportunidades!"
```

## 🎨 Diferenças Visuais

| Aspecto | Seção 1 (24h) | Seção 2 (Recentes) |
|---------|---------------|---------------------|
| **Header BG** | #1a1a1a (preto) | #333333 (cinza escuro) |
| **Ícone** | 🚗 | 📋 |
| **Título** | Novos Veículos | Cadastros Recentes |
| **Posição** | Topo | Abaixo da Seção 1 |
| **Prioridade** | Alta (novos!) | Média (recentes) |

## 💡 Benefícios

### Para Investidores
✅ **Mais opções**: Sempre veem veículos, mesmo sem novos  
✅ **Oportunidades perdidas**: Podem ver veículos que não viram antes  
✅ **Contexto claro**: Sabem quais são novíssimos e quais são recentes  
✅ **Sem spam**: Só recebem se houver veículos disponíveis  

### Para o Sistema
✅ **Maior engajamento**: Emails mais ricos em conteúdo  
✅ **Melhor conversão**: Mais veículos = mais chances de oferta  
✅ **Flexibilidade**: Adapta-se ao volume de cadastros  
✅ **Rastreamento**: Conta total de veículos por email enviado  

## 🔧 Implementação Técnica

### Funções Principais

#### 1. buscarVeiculosNovos()
```php
// Busca veículos das últimas 24h
WHERE DATE(v.data_cadastro) = DATE(DATE_SUB(NOW(), INTERVAL 1 DAY))
```

#### 2. buscarVeiculosRecentes()
```php
// Busca os 4 mais recentes, excluindo IDs já mostrados
WHERE v.id NOT IN ($idsExcluir)
ORDER BY v.data_cadastro DESC
LIMIT 4
```

#### 3. gerarHTMLEmail()
```php
// Gera HTML com AMBAS as seções
function gerarHTMLEmail($veiculosNovos, $veiculosRecentes, $nomeInvestidor)
```

### Fluxo de Execução

```
1. Buscar veículos novos (24h) → $veiculosNovos
2. Extrair IDs dos veículos novos
3. Buscar 4 recentes (excluindo IDs) → $veiculosRecentes
4. Calcular total: $totalVeiculos
5. Se total > 0:
   a. Gerar HTML com ambas seções
   b. Enviar para todos investidores
   c. Registrar no banco com total
6. Senão:
   Pular envio (nenhum veículo disponível)
```

## 📈 Estatísticas no Resumo

### Antes (versão antiga)
```
📊 RESUMO DO ENVIO:
  ✓ Enviados: 42
  ✗ Falhas: 0
  🚗 Veículos: 1
```

### Depois (nova versão)
```
📊 RESUMO DO ENVIO:
  ✓ Enviados com sucesso: 42
  ✗ Falhas: 0
  📧 Total de investidores: 42
  🚗 Veículos novos (24h): 1
  📋 Cadastros recentes: 4
  📦 Total de veículos: 5
  ⏱️  Tempo estimado: ~105 segundos
```

## 🧪 Como Testar

### 1. Gerar Preview
```bash
php preview_newsletter.php
```
Abre arquivo HTML no navegador para visualizar

### 2. Executar Envio Real
```bash
php enviar_newsletter_diario.php
```
Envia para todos os investidores

### 3. Verificar Resultado
- Checar inbox dos investidores
- Verificar tabela `newsletter` no banco
- Revisar `logs/email_erros.log` se houver falhas

## 📝 Notas Importantes

⚠️ **Não há duplicatas**: Seção de recentes exclui automaticamente veículos da seção de novos

⚠️ **Sempre CLI**: Script deve ser executado via linha de comando, não browser

⚠️ **Mínimo para envio**: Email só é enviado se houver PELO MENOS 1 veículo (novo OU recente)

⚠️ **Ordenação**: Ambas seções ordenadas por data de cadastro (mais recentes primeiro)

## 🎯 Próximos Passos

1. ✅ Testar preview: `php preview_newsletter.php`
2. ✅ Verificar layout no navegador
3. ✅ Testar responsividade (F12 > Device Toolbar)
4. ✅ Enviar teste: `php enviar_newsletter_diario.php`
5. ✅ Configurar CronJob para automação diária

---

**Versão**: 2.0 (Com duas seções)  
**Data**: 2026-01-29  
**Autor**: Sistema MotorGo  
