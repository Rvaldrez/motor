# Diagnóstico: Fotos Não Aparecem no Email

## Problema Relatado

> "as fotos dos carros não aparecem no corpo do email"

## Verificação do Código ✅

**O código ESTÁ CORRETO!** As imagens JÁ usam URLs absolutas:

### Linhas 225 e 279

```php
$fotoUrl = !empty($veiculo['foto_principal']) 
    ? $baseUrl . '/' . $veiculo['foto_principal'] 
    : $baseUrl . '/imagens/sem-foto.jpg';
```

**Gera**: `https://motorgo.co/fotos_veiculos/veiculo_123.jpg` ✅

---

## Possíveis Causas

Se o código está correto mas as fotos não aparecem, pode ser:

### 1. Dados Incorretos no Banco de Dados

**Verificar**:
```sql
SELECT id, marca, modelo, foto_principal 
FROM veiculos 
WHERE foto_principal IS NOT NULL 
LIMIT 5;
```

**Resultado esperado**:
- ✅ CORRETO: `fotos_veiculos/veiculo_123.jpg`
- ❌ ERRADO: `/home/u123/fotos_veiculos/veiculo_123.jpg` (caminho do servidor)
- ❌ ERRADO: `NULL` ou vazio

### 2. Arquivos Não Existem no Servidor

**Verificar**:
```bash
# SSH no servidor
cd /home/u218663118/domains/motorgo.co/public_html
ls -la fotos_veiculos/
```

**Resultado esperado**:
- Deve haver arquivos `.jpg` ou `.png`
- Permissões devem ser `644` ou `755`

### 3. URL Não Acessível

**Testar no navegador**:
```
https://motorgo.co/fotos_veiculos/veiculo_123.jpg
```

Se não abrir:
- ❌ Arquivo não existe
- ❌ Permissões incorretas
- ❌ Caminho errado no BD

### 4. Cliente de Email Bloqueando Imagens

Alguns clientes de email bloqueiam imagens externas por padrão:

- **Gmail**: Clique em "Exibir imagens" no topo do email
- **Outlook**: Verificar configurações de segurança
- **Mobile**: Pode ter opção "Carregar imagens"

---

## Passo a Passo de Diagnóstico

### 1. Verificar BD

```sql
-- Ver fotos registradas
SELECT 
    id,
    marca,
    modelo,
    foto_principal,
    LENGTH(foto_principal) as tamanho_path
FROM veiculos 
WHERE foto_principal IS NOT NULL 
ORDER BY id DESC 
LIMIT 10;
```

**O que observar**:
- `foto_principal` deve começar com `fotos_veiculos/`
- NÃO deve ter `/home/u...` ou `http://`
- NÃO deve estar NULL

### 2. Verificar Arquivos

```bash
# Listar últimas fotos
ls -lht /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/ | head -20

# Verificar permissões
stat /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/veiculo_123.jpg
```

### 3. Testar URLs

Copie o valor de `foto_principal` do BD e teste:

```
https://motorgo.co/[valor_do_BD]
```

Exemplo:
```
https://motorgo.co/fotos_veiculos/veiculo_123.jpg
```

### 4. Inspecionar Email

Abra o email recebido e veja o código fonte HTML:

**Procure por**:
```html
<img src="https://motorgo.co/fotos_veiculos/...">
```

**Verifique**:
- ✅ URL começa com `https://motorgo.co/`
- ✅ URL está completa
- ❌ URL não é relativa (`fotos_veiculos/...`)

---

## Soluções Possíveis

### Se o problema for no BD

**Corrigir caminhos**:
```sql
-- Ver caminhos problemáticos
SELECT id, foto_principal 
FROM veiculos 
WHERE foto_principal LIKE '/home/%' 
   OR foto_principal LIKE 'http%'
   OR foto_principal NOT LIKE 'fotos_veiculos/%';

-- Exemplo de correção (ajustar conforme necessário)
UPDATE veiculos 
SET foto_principal = CONCAT('fotos_veiculos/', SUBSTRING_INDEX(foto_principal, '/', -1))
WHERE foto_principal LIKE '/home/%';
```

### Se o problema for arquivos faltando

**Verificar e restaurar**:
```bash
# Verificar se pasta existe
ls -ld /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/

# Criar se não existir
mkdir -p /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/

# Ajustar permissões
chmod 755 /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/
chmod 644 /home/u218663118/domains/motorgo.co/public_html/fotos_veiculos/*.jpg
```

### Se o problema for cliente de email

**Instruir investidores**:
- Gmail: "Sempre exibir imagens de remetentes seguros"
- Outlook: Adicionar remetente à lista de confiáveis
- Mobile: Permitir carregamento de imagens

---

## Teste Rápido

Envie um email de teste com este HTML:

```html
<img src="https://motorgo.co/imagens/logo_motorgo_blk.png" alt="Logo">
<img src="https://motorgo.co/fotos_veiculos/veiculo_123.jpg" alt="Teste">
```

**Resultado esperado**:
- Logo deve aparecer (se existir)
- Foto do veículo deve aparecer (se existir)

Se NENHUMA imagem aparecer:
- ❌ Cliente de email está bloqueando
- ❌ Problema de rede/firewall

Se Logo aparecer mas foto não:
- ❌ Arquivo da foto não existe
- ❌ Caminho errado no BD

---

## Resumo

✅ **Código está correto** - URLs absolutas implementadas  
🔍 **Problema está em**: Dados do BD ou arquivos faltando  
📝 **Próximo passo**: Execute os diagnósticos acima  

### Checklist

- [ ] Verifique BD com SQL acima
- [ ] Liste arquivos da pasta fotos_veiculos
- [ ] Teste URL no navegador
- [ ] Veja código fonte do email recebido
- [ ] Verifique configuração do cliente de email

**Com esses passos, encontraremos o problema!** 🔍
