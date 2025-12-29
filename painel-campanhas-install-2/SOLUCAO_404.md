# 🔧 Solução para Erro 404 - Página não encontrada

## Problema
Ao acessar as rotas do plugin (ex: `/painel/home`), você recebe um erro 404 "Página não encontrada".

## Soluções (tente nesta ordem)

### 1. Flush de Rewrite Rules (Mais Comum)
1. Acesse o **WordPress Admin** (wp-admin)
2. Vá em **Configurações > Links Permanentes**
3. Clique em **"Salvar alterações"** (sem mudar nada)
4. Isso força o WordPress a recarregar todas as rotas

### 2. Desativar e Reativar o Plugin
1. Vá em **Plugins > Plugins Instalados**
2. **Desative** o plugin "Painel de Campanhas"
3. **Ative** novamente o plugin
4. Isso executa o hook de ativação que registra as rotas

### 3. Verificar Permalinks
Certifique-se de que os **Permalinks** estão configurados:
- Vá em **Configurações > Links Permanentes**
- Selecione qualquer opção que **NÃO seja "Padrão"**
- Recomendado: **"Nome do post"** ou **"Estruturado"**
- Clique em **"Salvar alterações"**

### 4. Limpar Cache
Se você usa plugins de cache:
- Limpe o cache do WordPress
- Limpe o cache do navegador (Ctrl+F5)
- Se usar cache de servidor (Varnish, Redis), limpe também

### 5. Verificar .htaccess
Certifique-se de que o arquivo `.htaccess` na raiz do WordPress tem permissão de escrita:
- O WordPress precisa poder modificar o `.htaccess` para as rewrite rules funcionarem
- Permissão recomendada: **644** ou **666**

### 6. Verificar Módulo mod_rewrite
Se estiver em servidor Apache, verifique se o módulo `mod_rewrite` está ativo:
```bash
# No terminal do servidor
apache2ctl -M | grep rewrite
# ou
httpd -M | grep rewrite
```

### 7. Debug Manual (Avançado)
Adicione este código temporariamente no `wp-config.php` para ver as rotas registradas:
```php
// Adicione ANTES de "That's all, stop editing!"
add_action('init', function() {
    if (isset($_GET['debug_routes'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->wp_rewrite_rules());
        echo '</pre>';
        die();
    }
}, 999);
```
Depois acesse: `http://seusite.com/?debug_routes=1`

## Verificação Rápida

Teste se as rotas estão funcionando:
1. Acesse: `http://seusite.com/painel/login`
2. Se aparecer a página de login → **Funcionando! ✅**
3. Se aparecer 404 → Siga as soluções acima

## URLs Corretas

Lembre-se de usar as URLs corretas:
- ✅ `/painel/login` (correto)
- ✅ `/painel/home` (correto)
- ❌ `/painel/dashboard` (não existe)
- ❌ `/wp-admin/painel` (não é assim)

## Se Nada Funcionar

1. Verifique os logs de erro do WordPress
2. Verifique se há conflito com outros plugins de rotas
3. Desative outros plugins temporariamente para testar
4. Verifique se o servidor suporta rewrite rules (alguns servidores compartilhados não suportam)

## Contato

Se o problema persistir após tentar todas as soluções, forneça:
- Versão do WordPress
- Versão do PHP
- Tipo de servidor (Apache/Nginx)
- Lista de plugins ativos
- Mensagem de erro completa
