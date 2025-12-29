# 🔧 Solução para WordPress em Subdiretório

## Problema
Se o WordPress está instalado em um subdiretório (ex: `http://localhost/wordpress/`), as rotas podem não funcionar corretamente.

## Solução Implementada

O plugin agora tem um **fallback automático** que detecta a URL mesmo quando as rewrite rules não funcionam. Isso significa que mesmo em subdiretórios, as rotas devem funcionar.

## Passos para Resolver

### 1. Desativar e Reativar o Plugin
1. Vá em **Plugins > Plugins Instalados**
2. **Desative** o plugin "Painel de Campanhas"
3. **Ative** novamente o plugin
4. Isso força o registro das rotas

### 2. Atualizar Links Permanentes
1. Acesse **Configurações > Links Permanentes**
2. Clique em **"Salvar alterações"** (sem mudar nada)
3. Isso força o WordPress a recarregar as rewrite rules

### 3. Usar o Script de Debug
1. Copie o arquivo `debug-routes.php` para a raiz do WordPress
2. Acesse: `http://localhost/wordpress/debug-routes.php`
3. O script mostrará:
   - Se o plugin está ativo
   - Quais rotas estão registradas
   - Se há problemas com query vars
   - Opção para forçar flush

### 4. Testar URLs
Tente acessar estas URLs (ajuste o caminho conforme seu WordPress):
- `http://localhost/wordpress/painel/login`
- `http://localhost/wordpress/painel/home`
- `http://localhost/wordpress/painel/campanhas`

**Importante:** 
- ✅ Use `/painel/home` (sem barra final)
- ✅ Use `/painel/home/` (com barra final) - ambos devem funcionar agora

## Como Funciona o Fallback

O plugin agora detecta automaticamente a URL mesmo quando `get_query_var()` não funciona. Ele:
1. Pega a URL atual (`$_SERVER['REQUEST_URI']`)
2. Remove o caminho base do WordPress (ex: `/wordpress`)
3. Compara com os padrões conhecidos
4. Mapeia para a página correta

## Verificação Rápida

Execute este código no `functions.php` do tema (temporariamente) para ver o que está acontecendo:

```php
add_action('template_redirect', function() {
    if (strpos($_SERVER['REQUEST_URI'], '/painel/') !== false) {
        error_log('URL: ' . $_SERVER['REQUEST_URI']);
        error_log('Query Var pc_page: ' . get_query_var('pc_page'));
    }
}, 1);
```

Depois verifique o arquivo `wp-content/debug.log` (se WP_DEBUG estiver ativo).

## Se Ainda Não Funcionar

1. Verifique se o `.htaccess` na raiz do WordPress tem permissão de escrita
2. Verifique se o módulo `mod_rewrite` está ativo no Apache
3. Verifique se há conflito com outros plugins de rotas
4. Tente desativar outros plugins temporariamente

## Contato

Se o problema persistir, forneça:
- URL completa que está tentando acessar
- URL do WordPress (home_url)
- Resultado do script `debug-routes.php`
- Mensagem de erro completa (se houver)

