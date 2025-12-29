# Integração React com WordPress

Este diretório contém a aplicação React que substitui as páginas PHP do plugin.

## Estrutura

- `src/` - Código fonte React/TypeScript
- `dist/` - Build de produção (gerado após `npm run build`)
- `package.json` - Dependências do projeto

## Setup

1. **Instalar dependências:**
```bash
cd react
npm install
```

2. **Desenvolvimento:**
```bash
npm run dev
```
Isso iniciará o servidor de desenvolvimento na porta 8080.

3. **Build para produção:**
```bash
npm run build
```
Isso gerará os arquivos estáticos na pasta `dist/` que serão carregados pelo WordPress.

## Como funciona a integração

1. O WordPress detecta se o build do React existe (`react/dist/index.html`)
2. Se existir e a opção `pc_use_react` estiver habilitada, carrega o `react-wrapper.php`
3. O wrapper PHP enfileira os assets do React (CSS e JS)
4. O React é montado no elemento `#root`
5. O React usa HashRouter para navegação (compatível com WordPress)
6. Todas as chamadas de API usam `wp_ajax` através do arquivo `src/lib/api.ts`

## Ativando o React no WordPress

Por padrão, o plugin usa os templates PHP. Para ativar o React:

```php
// Adicionar no código ou via função no WordPress
update_option('pc_use_react', true);
```

Ou criar um endpoint/admin page para alternar entre PHP e React.

## Estrutura de API

Todas as chamadas de API estão centralizadas em `src/lib/api.ts` e usam a função `wpAjax()` que faz requisições para os endpoints AJAX do WordPress.

Os endpoints disponíveis são:
- `pc_login` - Login
- `pc_get_campanhas` - Listar campanhas
- `cm_schedule_campaign` - Criar campanha
- `pc_get_messages` - Listar templates de mensagem
- E muitos outros... (ver `api.ts` para lista completa)

## Variáveis Globais

O WordPress injeta `window.pcAjax` com:
- `ajaxurl` - URL do admin-ajax.php
- `nonce` - Nonce para segurança
- `homeUrl` - URL base do WordPress
- `currentUser` - Dados do usuário atual
- `currentPage` - Página atual

## Notas

- O React usa **HashRouter** ao invés de BrowserRouter para compatibilidade com WordPress
- Os assets são carregados via `react-wrapper.php` que detecta automaticamente os arquivos gerados pelo Vite
- O build gera um `manifest.json` (se configurado) que é usado para carregar os assets corretos

