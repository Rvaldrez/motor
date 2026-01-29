# FAQ: Quando a Newsletter é Enviada?

## Pergunta Principal

**"A newsletter será enviada somente se houver veículos novos cadastrados nas últimas 24h?"**

## Resposta Direta

**SIM** ✅

A newsletter é enviada **SOMENTE** quando há veículos cadastrados nas últimas 24 horas.

## Lógica Atual

### A newsletter É ENVIADA quando:

✅ Há veículos novos cadastrados nas últimas 24 horas  
✅ Existem investidores ativos  

### A newsletter NÃO é enviada quando:

❌ NÃO há veículos das últimas 24h (mesmo que existam veículos recentes de dias anteriores)  
❌ NÃO há investidores ativos  

## Código

**Arquivo**: `enviar_newsletter_diario.php`

**Linha 685** - Condição de envio:
```php
if (count($investidores) > 0 && count($veiculosNovos) > 0) {
    // ENVIA a newsletter SOMENTE se houver veículos das últimas 24h ✅
    echo "Iniciando envio de emails...\n";
}
```

**Linhas 745-750** - Mensagens quando NÃO envia:
```php
} elseif (count($veiculosNovos) == 0) {
    echo "⚠ Nenhum veículo novo cadastrado nas últimas 24h. Newsletter não enviada.\n";
    if (count($veiculosRecentes) > 0) {
        echo "   Há X veículo(s) recente(s), mas newsletter só é enviada com veículos das últimas 24h.\n";
    }
} elseif (count($investidores) == 0) {
    echo "⚠ Nenhum investidor ativo encontrado. Newsletter não enviada.\n";
}
```

## Cenários Detalhados

### Cenário 1: Veículos Novos (24h) + Veículos Recentes

**Banco de dados:**
- Veículos das últimas 24h: 3 veículos
- Veículos recentes (dias anteriores): 4 veículos

**Resultado:**
- ✅ **Newsletter ENVIADA**
- Seção 1: 3 veículos (últimas 24h)
- Seção 2: 4 veículos (dias anteriores)
- Total mostrado: 7 veículos

### Cenário 2: APENAS Veículos Recentes (SEM veículos de 24h)

**Banco de dados:**
- Veículos das últimas 24h: 0 veículos
- Veículos recentes (dias anteriores): 4 veículos

**Resultado:**
- ❌ **Newsletter NÃO ENVIADA**
- Mensagem: "⚠ Nenhum veículo novo cadastrado nas últimas 24h. Newsletter não enviada."
- Mensagem adicional: "Há 4 veículo(s) recente(s), mas newsletter só é enviada com veículos das últimas 24h."

### Cenário 3: Veículos Novos (24h) SEM Veículos Recentes

**Banco de dados:**
- Veículos das últimas 24h: 5 veículos
- Veículos recentes (dias anteriores): 0 veículos

**Resultado:**
- ✅ **Newsletter ENVIADA**
- Seção 1: 5 veículos (últimas 24h)
- Seção 2: (vazia - sem veículos recentes)
- Total mostrado: 5 veículos

### Cenário 4: Sem Veículos

**Banco de dados:**
- Veículos das últimas 24h: 0 veículos
- Veículos recentes (dias anteriores): 0 veículos

**Resultado:**
- ❌ **Newsletter NÃO ENVIADA**
- Mensagem: "⚠ Nenhum veículo novo cadastrado nas últimas 24h. Newsletter não enviada."

## Tabela de Decisão

| Veículos 24h | Veículos Recentes | Investidores | Newsletter Enviada? |
|--------------|-------------------|--------------|---------------------|
| 3 veículos   | 4 veículos        | Sim          | ✅ **SIM**         |
| 0 veículos   | 4 veículos        | Sim          | ❌ **NÃO**         |
| 5 veículos   | 0 veículos        | Sim          | ✅ **SIM**         |
| 0 veículos   | 0 veículos        | Sim          | ❌ **NÃO**         |
| 3 veículos   | 4 veículos        | Não          | ❌ **NÃO**         |

## Estrutura da Newsletter (quando enviada)

Quando a newsletter é enviada, ela sempre mostra duas seções:

### Seção 1 - Novos Veículos (Últimas 24 horas)
- **Obrigatória**: DEVE ter pelo menos 1 veículo para enviar
- Mostra TODOS os veículos cadastrados nas últimas 24h
- Header: 🚗 Novos Veículos (Últimas 24 horas)
- Layout: 2 colunas (desktop) / 1 coluna (mobile)

### Seção 2 - Cadastros Recentes (Dias Anteriores)
- **Opcional**: Pode estar vazia
- Mostra até 4 veículos mais recentes de ANTES das últimas 24h
- Header: 📋 Cadastros Recentes
- Layout: 2 colunas (desktop) / 1 coluna (mobile)

## Benefícios desta Abordagem

1. ✅ **Foco em Novidades**: Garante que a newsletter sempre traz conteúdo realmente novo
2. ✅ **Evita Spam**: Não envia newsletters sem novidades relevantes
3. ✅ **Contexto Adicional**: Quando há novidades, mostra também veículos recentes para comparação
4. ✅ **Clareza**: Investidores sabem que cada newsletter tem conteúdo fresco

## Resumo Rápido

**Requisito Principal:**
- A newsletter SÓ é enviada se houver veículos das últimas 24h

**Quando Envia:**
- ✅ Tem veículos de 24h + tem investidores = **ENVIA**

**Quando NÃO Envia:**
- ❌ Não tem veículos de 24h (mesmo com recentes) = **NÃO ENVIA**
- ❌ Não tem investidores = **NÃO ENVIA**

**Conteúdo da Newsletter:**
- Seção 1: Veículos das últimas 24h (obrigatório, sem limite)
- Seção 2: 4 veículos mais recentes de dias anteriores (opcional)

## Documentação Relacionada

- `ESTRUTURA_NEWSLETTER.md` - Estrutura completa do email
- `IMPLEMENTACAO_DUAS_SECOES.md` - Implementação das duas seções
- `CORRECAO_LOGICA_VEICULOS.md` - Explicação da lógica de veículos recentes
- `enviar_newsletter_diario.php` - Código fonte principal
