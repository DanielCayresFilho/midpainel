# Como Corrigir o Erro "O plugin não possui um cabeçalho válido"

## Problema Identificado

Há uma estrutura duplicada no plugin. O WordPress precisa que o arquivo principal esteja em:

```
wp-content/plugins/painel-campanhas/painel-campanhas.php
```

## Solução

### Opção 1: Usar a pasta interna (Recomendado)

Se você baixou o plugin e há uma estrutura assim:
```
painel-campanhas/
  painel-campanhas/
    painel-campanhas.php  ← Este é o arquivo correto
    templates/
    assets/
```

**Faça isso:**
1. Entre na pasta `painel-campanhas/painel-campanhas/`
2. Copie TODO o conteúdo dessa pasta
3. Cole em `wp-content/plugins/painel-campanhas/`
4. A estrutura final deve ser:
   ```
   wp-content/plugins/painel-campanhas/
     painel-campanhas.php  ← Arquivo principal
     templates/
     assets/
     README.md
   ```

### Opção 2: Usar o arquivo da raiz

Se você tem:
```
painel-campanhas/
  painel-campanhas.php  ← Este arquivo
  templates/
  assets/
```

**Faça isso:**
1. Certifique-se de que a pasta se chama exatamente `painel-campanhas`
2. Copie a pasta inteira para `wp-content/plugins/`
3. A estrutura final deve ser:
   ```
   wp-content/plugins/painel-campanhas/
     painel-campanhas.php  ← Arquivo principal
     templates/
     assets/
   ```

## Verificação Final

Após copiar, verifique:

1. **Localização do arquivo:**
   ```
   wp-content/plugins/painel-campanhas/painel-campanhas.php
   ```

2. **Nome da pasta:**
   - Deve ser exatamente `painel-campanhas` (sem espaços, sem caracteres especiais)

3. **Cabeçalho do arquivo:**
   O arquivo `painel-campanhas.php` deve começar com:
   ```php
   <?php
   /**
    * Plugin Name: Painel de Campanhas
    * Plugin URI: 
    * Description: Sistema completo de gerenciamento de campanhas...
    * Version: 1.0.0
    * Author: Daniel Cayres
    * ...
    */
   ```

4. **Sem BOM:**
   - O arquivo deve ser salvo como UTF-8 sem BOM
   - Use um editor como Notepad++ e salve como "UTF-8 without BOM"

## Teste Rápido

1. Acesse WordPress Admin > Plugins
2. Procure por "Painel de Campanhas"
3. Se aparecer, o cabeçalho está correto
4. Se não aparecer, verifique os logs de erro do WordPress

## Se Ainda Não Funcionar

1. **Verifique os logs:**
   - `/wp-content/debug.log`
   - Logs do servidor

2. **Teste com arquivo mínimo:**
   Crie um arquivo `test-plugin.php` na pasta do plugin:
   ```php
   <?php
   /**
    * Plugin Name: Teste
    * Version: 1.0.0
    */
   ```
   Se este funcionar, o problema está no arquivo principal.

3. **Verifique permissões:**
   - Pasta: 755
   - Arquivo: 644

4. **Limpe cache:**
   - Limpe cache do WordPress
   - Limpe cache do navegador
   - Desative outros plugins temporariamente

