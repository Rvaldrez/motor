# FAQ: Quando a Newsletter é Enviada?

## Pergunta Principal

**"a newsletter será enviada somente se tiverem veículos cadastrados nas últimas 24h?"**

## Resposta Direta

**NÃO** ❌

A newsletter é enviada se houver **QUALQUER veículo disponível**, seja:
- Veículos das últimas 24 horas (Seção 1), OU
- Veículos recentes dos dias anteriores (Seção 2), OU
- Ambos

## Lógica Atual

### A newsletter É ENVIADA quando:

✅ Há veículos novos das últimas 24h (mesmo sem recentes)  
✅ Há veículos recentes dos dias anteriores (mesmo sem novos de 24h)  
✅ Ambas as seções têm veículos  

### A newsletter NÃO é enviada quando:

❌ Não há nenhum veículo disponível (total = 0)  
❌ Não há investidores ativos  

## Código

**Arquivo**: `enviar_newsletter_diario.php`

**Linha 677** - Cálculo do total:
```php
$totalVeiculos = count($veiculosNovos) + count($veiculosRecentes);
```

**Linha 685** - Decisão de envio:
```php
if (count($investidores) > 0 && $totalVeiculos > 0) {
    // ENVIA a newsletter ✅
    echo "Iniciando envio de emails...\n";
}
```

**Linhas 745-749** - Mensagens quando NÃO envia:
```php
} elseif ($totalVeiculos == 0) {
    echo "⚠ Nenhum veículo disponível para enviar. Newsletter não enviada.\n";
} elseif (count($investidores) == 0) {
    echo "⚠ Nenhum investidor ativo encontrado. Newsletter não enviada.\n";
}
```

## Cenários Detalhados

### Cenário 1: Ambas as seções com veículos

**Banco de dados:**
- Veículos novos (24h): 3 veículos
- Cadastros recentes: 4 veículos
- Total: 7 veículos

**Resultado:** ✅ Newsletter ENVIADA

**Email mostra:**
- Seção "Novos Veículos (Últimas 24h)": 3 veículos
- Seção "Cadastros Recentes": 4 veículos

---

### Cenário 2: SEM veículos novos, MAS COM recentes (IMPORTANTE!)

**Banco de dados:**
- Veículos novos (24h): 0 veículos
- Cadastros recentes: 4 veículos
- Total: 4 veículos

**Resultado:** ✅ Newsletter ENVIADA

**Email mostra:**
- Seção "Novos Veículos (Últimas 24h)": (vazia/oculta)
- Seção "Cadastros Recentes": 4 veículos

**Este é o ponto chave!** Mesmo sem veículos nas últimas 24h, a newsletter é enviada se houver veículos recentes.

---

### Cenário 3: COM veículos novos, SEM recentes

**Banco de dados:**
- Veículos novos (24h): 5 veículos
- Cadastros recentes: 0 veículos
- Total: 5 veículos

**Resultado:** ✅ Newsletter ENVIADA

**Email mostra:**
- Seção "Novos Veículos (Últimas 24h)": 5 veículos
- Seção "Cadastros Recentes": (vazia/oculta)

---

### Cenário 4: Nenhum veículo disponível

**Banco de dados:**
- Veículos novos (24h): 0 veículos
- Cadastros recentes: 0 veículos
- Total: 0 veículos

**Resultado:** ❌ Newsletter NÃO ENVIADA

**Mensagem no console:**
```
⚠ Nenhum veículo disponível para enviar. Newsletter não enviada.
```

## Tabela de Decisão Rápida

| Novos (24h) | Recentes | Total | Newsletter? | Seções no Email |
|-------------|----------|-------|-------------|-----------------|
| 3 veículos | 4 veículos | 7 | ✅ SIM | Ambas |
| 0 veículos | 4 veículos | 4 | ✅ SIM | Só Recentes |
| 5 veículos | 0 veículos | 5 | ✅ SIM | Só Novos |
| 0 veículos | 0 veículos | 0 | ❌ NÃO | - |

## Benefícios da Abordagem Atual

### 1. ✅ Sempre Fornece Valor

Mesmo sem veículos novos de ontem, os investidores ainda veem opções disponíveis dos dias anteriores.

### 2. ✅ Mantém o Engajamento

Comunicação regular com os investidores, mantendo-os informados sobre o inventário disponível.

### 3. ✅ Flexível e Adaptável

O sistema se adapta a diferentes níveis de inventário:
- Muitos cadastros novos? Mostra todos + recentes
- Poucos cadastros novos? Mostra poucos + recentes
- Nenhum cadastro novo? Mostra só recentes

### 4. ✅ Amigável ao Usuário

Investidores nunca recebem newsletters vazias. Se recebem email, sempre há conteúdo útil.

## Resumo Rápido

**Pergunta:** Newsletter só é enviada se houver veículos nas últimas 24h?

**Resposta:** NÃO. É enviada se houver QUALQUER veículo (novos OU recentes).

**Requisito Mínimo:** Pelo menos 1 veículo no total (pode ser novo ou recente).

**Lógica:** Newsletter enviada = (Veículos Novos > 0) OU (Veículos Recentes > 0)

**Benefício:** Maximiza o engajamento garantindo comunicação regular e valiosa.

## Documentação Relacionada

- **ESTRUTURA_NEWSLETTER.md** - Estrutura do email com ambas as seções
- **IMPLEMENTACAO_DUAS_SECOES.md** - Como as duas seções funcionam
- **CORRECAO_LOGICA_VEICULOS.md** - Lógica de veículos recentes
- **enviar_newsletter_diario.php** - Código fonte

---

**Última atualização:** 2026-01-29  
**Sistema:** MotorGo Newsletter Diária
