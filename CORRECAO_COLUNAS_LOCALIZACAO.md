# Correção: Colunas de Localização (cidade e estado)

## Erro Relatado

```
Fatal error: Uncaught mysqli_sql_exception: Unknown column 'v.cidade' in 'SELECT' 
in /home/u218663118/domains/motorgo.co/public_html/enviar_newsletter_web.php:70
```

## Causa Raiz

A interface web estava tentando buscar as colunas `v.cidade` e `v.estado` da tabela `veiculos`, mas essas colunas não existem nessa tabela.

**Estrutura correta do banco de dados:**
- Tabela `veiculos`: Contém informações do veículo (marca, modelo, ano, etc.)
- Tabela `usuarios`: Contém informações do usuário/proprietário (cidade, estado, etc.)

A localização do veículo é determinada pela localização do **proprietário** (usuário), não do veículo em si.

## Solução Aplicada

Adicionamos um `LEFT JOIN` com a tabela `usuarios` e mudamos as referências de coluna.

### 1. Função `buscarVeiculosNovos()` (Linhas 45-70)

**ANTES:**
```php
$query = "
    SELECT 
        v.id,
        v.marca,
        v.modelo,
        v.ano_fabrica,
        v.quilometragem,
        v.cidade,        // ❌ Não existe
        v.estado,        // ❌ Não existe
        v.data_cadastro
    FROM veiculos v
    WHERE DATE(v.data_cadastro) = '$ontem'
";
```

**DEPOIS:**
```php
$query = "
    SELECT 
        v.id,
        v.marca,
        v.modelo,
        v.ano_fabrica,
        v.quilometragem,
        u.cidade AS usuario_cidade,   // ✅ Correto
        u.estado AS usuario_estado,   // ✅ Correto
        v.data_cadastro
    FROM veiculos v
    LEFT JOIN usuarios u ON v.usuario_id = u.id  // ✅ JOIN adicionado
    WHERE DATE(v.data_cadastro) = '$ontem'
";
```

### 2. Função `buscarVeiculosRecentes()` (Linhas 81-105)

Mesmas alterações aplicadas nesta função.

### 3. Exibições HTML (3 locais)

**ANTES:**
```php
<?php echo $veiculo['cidade']; ?>        // ❌
<?php echo $veiculo['estado']; ?>        // ❌
```

**DEPOIS:**
```php
<?php echo $veiculo['usuario_cidade']; ?> // ✅
<?php echo $veiculo['usuario_estado']; ?> // ✅
```

**Locais atualizados:**
- Linha 215: Preview - Veículos novos
- Linha 269: Preview - Veículos recentes
- Linha 914: Card de informação do veículo

## Mudanças Detalhadas

1. **Linha 55-56**: SQL SELECT - Mudou `v.cidade, v.estado` para `u.cidade AS usuario_cidade, u.estado AS usuario_estado`
2. **Linha 64**: Adicionou `LEFT JOIN usuarios u ON v.usuario_id = u.id`
3. **Linha 91-92**: SQL SELECT - Mesma mudança que #1
4. **Linha 100**: Adicionou `LEFT JOIN usuarios u ON v.usuario_id = u.id`
5. **Linhas 215, 269, 914**: HTML - Mudou array keys para `usuario_cidade` e `usuario_estado`

## Verificação

```bash
php -l enviar_newsletter_web.php
# Output: No syntax errors detected
```

## Comparação com Versão CLI

A versão CLI (`enviar_newsletter_diario.php`) já estava correta desde o início:
- ✅ Tinha LEFT JOIN com usuarios
- ✅ Usava u.cidade AS usuario_cidade
- ✅ Usava u.estado AS usuario_estado

A interface web agora está **consistente** com a versão CLI.

## Status Final

✅ **SQL Queries**: Ambas as funções têm JOIN correto  
✅ **Aliases de Coluna**: Usando usuario_cidade e usuario_estado  
✅ **Exibições HTML**: Todos os 3 locais atualizados  
✅ **Sintaxe PHP**: Validada sem erros  
✅ **Consistência**: Compatível com versão CLI  

## Próximos Passos

A interface web agora deve funcionar corretamente:

```
http://motorgo.co/enviar_newsletter_web.php
```

O usuário poderá:
1. Ver informações corretas dos veículos (com localização do proprietário)
2. Visualizar preview do email
3. Enviar newsletter manualmente
4. Acompanhar progresso em tempo real

---

**Data da Correção**: 2026-01-30  
**Arquivo Modificado**: `enviar_newsletter_web.php`  
**Total de Alterações**: 5 mudanças (2 JOINs + 3 exibições HTML)
