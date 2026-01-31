# GUIA DE INSTALAÇÃO DAS DEPENDÊNCIAS

## Problema Identificado

O script `enviar_newsletter_diario.php` não funciona porque as dependências do Composer não foram instaladas.

**Erro apresentado:**
```
PHP Fatal error: Failed opening required '/home/runner/work/motor/motor/vendor/autoload.php'
```

## Solução

### Passo 1: Instalar as Dependências

Execute o seguinte comando no diretório raiz do projeto:

```bash
cd /caminho/para/motor
php composer install
```

**OU**, se o comando `composer` estiver instalado globalmente:

```bash
cd /caminho/para/motor
composer install
```

### Passo 2: Verificar a Instalação

Após a instalação, verifique se o arquivo `vendor/autoload.php` foi criado:

```bash
ls -la vendor/autoload.php
```

Você deve ver algo como:
```
-rw-r--r-- 1 user user 1234 Jan 28 15:00 vendor/autoload.php
```

### Passo 3: Testar o Script

Agora você pode executar o script de newsletter:

```bash
php enviar_newsletter_diario.php
```

## O que será instalado?

As seguintes dependências serão instaladas automaticamente:

1. **PHPMailer 6.8+** - Biblioteca para envio de emails via SMTP
2. **vlucas/phpdotenv 5.5+** - Biblioteca para carregar variáveis de ambiente do arquivo .env

## Estrutura Criada

Após a instalação, sua estrutura de diretórios ficará assim:

```
motor/
├── vendor/
│   ├── autoload.php          ← Este arquivo será criado
│   ├── composer/
│   ├── phpmailer/
│   ├── vlucas/
│   └── ...
├── composer.json              ← Criado agora
├── composer.lock              ← Será criado após install
├── enviar_newsletter_diario.php
├── teste_envio_unico.php
└── ...
```

## Troubleshooting

### Erro: "composer: command not found"

Use o arquivo `composer` (composer.phar) que já existe no diretório:

```bash
php composer install
```

### Erro de Permissão

Se tiver problemas de permissão, tente:

```bash
sudo php composer install
```

### Erro de Memória

Se o Composer reclamar de falta de memória:

```bash
php -d memory_limit=-1 composer install
```

## Próximos Passos

Após instalar as dependências:

1. ✅ Execute `php teste_envio_unico.php` para testar o SMTP
2. ✅ Execute `php enviar_newsletter_diario.php` para enviar a newsletter
3. ✅ Configure o CronJob para execução automática diária

## Observação Importante

O diretório `vendor/` contém bibliotecas de terceiros e **NÃO deve ser versionado no Git**. 

Certifique-se de que o arquivo `.gitignore` contém:

```
/vendor/
composer.lock
```

Isso já deve estar configurado, mas verifique se necessário.
