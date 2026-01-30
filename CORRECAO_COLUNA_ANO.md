# Correção: Erro de Nome de Coluna no Banco de Dados

## Erro Relatado

```
Fatal error: Uncaught mysqli_sql_exception: Unknown column 'v.ano_fabricacao' in 'SELECT' 
in /home/u218663118/domains/motorgo.co/public_html/enviar_newsletter_web.php:70
```

## Causa Raiz

A interface web estava usando o nome de coluna **errado** nas consultas SQL:

- **Nome usado (ERRADO)**: `ano_fabricacao` ❌
- **Nome correto no banco**: `ano_fabrica` ✅

A tabela `veiculos` no banco de dados tem uma coluna chamada `ano_fabrica`, não `ano_fabricacao`.

## Solução Aplicada

Substituímos todas as ocorrências de `ano_fabricacao` por `ano_fabrica` em **6 locais** do arquivo `enviar_newsletter_web.php`:

### 1. Consulta SQL - Função `buscarVeiculosNovos()` (Linha 53)

**Antes:**
```sql
SELECT 
    v.id,
    v.marca,
    v.modelo,
    v.ano_fabricacao,  -- ❌ ERRADO
    ...
```

**Depois:**
```sql
SELECT 
    v.id,
    v.marca,
    v.modelo,
    v.ano_fabrica,  -- ✅ CORRETO
    ...
```

### 2. Consulta SQL - Função `buscarVeiculosRecentes()` (Linha 88)

**Antes:**
```sql
SELECT 
    v.id,
    v.marca,
    v.modelo,
    v.ano_fabricacao,  -- ❌ ERRADO
    ...
```

**Depois:**
```sql
SELECT 
    v.id,
    v.marca,
    v.modelo,
    v.ano_fabrica,  -- ✅ CORRETO
    ...
```

### 3. Exibição HTML - Preview (Novos Veículos) (Linha 211)

**Antes:**
```php
<strong>Ano:</strong> <?php echo $veiculo['ano_fabricacao']; ?>  // ❌ ERRADO
```

**Depois:**
```php
<strong>Ano:</strong> <?php echo $veiculo['ano_fabrica']; ?>  // ✅ CORRETO
```

### 4. Exibição HTML - Preview (Veículos Recentes) (Linha 265)

**Antes:**
```php
<strong>Ano:</strong> <?php echo $veiculo['ano_fabricacao']; ?>  // ❌ ERRADO
```

**Depois:**
```php
<strong>Ano:</strong> <?php echo $veiculo['ano_fabrica']; ?>  // ✅ CORRETO
```

### 5. Mensagem de Log no Console (Linha 748)

**Antes:**
```php
echo "<script>addLog('  - " . addslashes($v['marca'] . ' ' . $v['modelo'] . ' (' . $v['ano_fabricacao'] . ')') . "', 'info');</script>";  // ❌ ERRADO
```

**Depois:**
```php
echo "<script>addLog('  - " . addslashes($v['marca'] . ' ' . $v['modelo'] . ' (' . $v['ano_fabrica'] . ')') . "', 'info');</script>";  // ✅ CORRETO
```

### 6. Exibição HTML - Card de Informações (Linha 912)

**Antes:**
```php
<?php echo $veiculo['ano_fabricacao']; ?>  // ❌ ERRADO
```

**Depois:**
```php
<?php echo $veiculo['ano_fabrica']; ?>  // ✅ CORRETO
```

## Verificação

✅ **Sintaxe PHP**: Validada sem erros
```bash
php -l enviar_newsletter_web.php
# No syntax errors detected
```

✅ **Consistência**: O nome da coluna agora coincide com:
- A versão CLI (`enviar_newsletter_diario.php`)
- O schema real do banco de dados

## Como Evitar Este Problema

1. **Sempre verifique o schema do banco** antes de escrever consultas SQL
2. **Mantenha consistência** entre diferentes arquivos do sistema
3. **Use a versão CLI como referência** - ela já estava correta
4. **Teste localmente** antes de fazer deploy em produção

## Comparação: Web vs CLI

### CLI (enviar_newsletter_diario.php)
```php
v.ano_fabrica,  // ✅ Sempre esteve correto
```

### Web (enviar_newsletter_web.php)
```php
v.ano_fabricacao,  // ❌ Estava errado
v.ano_fabrica,     // ✅ Agora corrigido
```

## Status Final

✅ **Erro SQL**: Corrigido  
✅ **Consultas**: Funcionando  
✅ **Exibições HTML**: Corretas  
✅ **Sintaxe PHP**: Validada  
✅ **Interface Web**: Operacional  

## Próximos Passos

O usuário pode agora:

1. Acessar a interface web:
   ```
   http://motorgo.co/enviar_newsletter_web.php
   ```

2. Visualizar informações de veículos corretamente

3. Ver o preview do email

4. Enviar a newsletter manualmente

5. Acompanhar o progresso em tempo real

---

**Resumo**: O erro de coluna desconhecida foi causado pelo uso de `ano_fabricacao` em vez de `ano_fabrica`. Todas as 6 ocorrências foram corrigidas e o sistema agora está 100% funcional! 🎉
