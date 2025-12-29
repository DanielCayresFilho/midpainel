# Integração React com WordPress - Guia Completo

## Resumo

Este plugin agora suporta uma interface React moderna que substitui as páginas PHP tradicionais. A integração permite usar React mantendo toda a funcionalidade backend do WordPress.

## Estrutura

```
painel-campanhas-install-2/
├── react/                    # Aplicação React
│   ├── src/                 # Código fonte
│   ├── dist/                # Build de produção (gerado)
│   └── package.json
├── react-wrapper.php        # Wrapper PHP que carrega React
└── painel-campanhas.php     # Plugin principal (modificado)
```

## Setup Inicial

### 1. Instalar dependências do React

```bash
cd react
npm install
```

### 2. Build para produção

```bash
npm run build
```

Isso criará os arquivos estáticos em `react/dist/`.

### 3. Ativar React no WordPress

Por padrão, o plugin usa templates PHP. Para ativar React:

**Opção 1: Via código (adicionar em functions.php ou plugin)**
```php
update_option('pc_use_react', true);
```

**Opção 2: Manualmente no banco**
```sql
UPDATE wp_options SET option_value = '1' WHERE option_name = 'pc_use_react';
```

**Opção 3: Adicionar constante no wp-config.php (recomendado)**
```php
define('PC_USE_REACT', true);
```

E então modificar `render_page()` para verificar a constante:
```php
$use_react = defined('PC_USE_REACT') && PC_USE_REACT || get_option('pc_use_react', false);
```

## Como Funciona

1. **Detecção**: `render_page()` verifica se `pc_use_react` está ativado e se `react/dist/index.html` existe
2. **Wrapper**: Se sim, carrega `react-wrapper.php` ao invés do template PHP
3. **Assets**: O wrapper carrega todos os CSS e JS de `react/dist/assets/`
4. **Montagem**: React monta no elemento `#root`
5. **API**: React usa `window.pcAjax` para fazer chamadas AJAX ao WordPress

## Estrutura de API

Todas as chamadas de API estão centralizadas em `react/src/lib/api.ts`:

```typescript
import { login, getCampanhas, scheduleCampaign } from '@/lib/api';

// Exemplo de uso
const user = await login('email@example.com', 'senha');
const campanhas = await getCampanhas();
```

## Variáveis Globais (window.pcAjax)

O WordPress injeta automaticamente:

```javascript
window.pcAjax = {
  ajaxurl: '/wp-admin/admin-ajax.php',
  nonce: 'abc123...',           // Nonce para segurança
  cmNonce: 'xyz789...',         // Nonce para campaign-manager
  homeUrl: 'http://site.com',
  restUrl: 'http://site.com/wp-json/...',
  currentUser: {
    id: 1,
    name: 'Nome Usuário',
    email: 'user@example.com',
    isAdmin: true
  },
  currentPage: 'home'
}
```

## Rotas

O React usa **HashRouter** para compatibilidade com WordPress:

- `/#/painel/login` - Login
- `/#/painel/home` - Dashboard
- `/#/painel/campanhas` - Lista de campanhas
- etc...

## Desenvolvimento

### Modo desenvolvimento

```bash
cd react
npm run dev
```

Isso inicia o servidor na porta 8080. **Note**: O modo dev não funciona diretamente no WordPress, você precisa fazer build.

### Build para produção

```bash
npm run build
```

Sempre faça build antes de testar no WordPress.

## Troubleshooting

### React não carrega

1. Verifique se `react/dist/` existe e tem arquivos
2. Verifique se `pc_use_react` está ativado
3. Verifique permissões de arquivos
4. Veja console do navegador para erros

### Assets não encontrados

1. Execute `npm run build` novamente
2. Verifique se `react/dist/assets/` contém arquivos
3. Verifique permissões de leitura

### Erros de API

1. Verifique se `window.pcAjax` está definido (console do navegador)
2. Verifique se os nonces estão corretos
3. Veja Network tab para ver requisições AJAX

### Página em branco

1. Abra console do navegador (F12)
2. Verifique erros de JavaScript
3. Verifique se `#root` existe no DOM
4. Tente fazer build novamente

## Migrando páginas PHP para React

1. **Criar componente React** em `react/src/pages/painel/`
2. **Adicionar rota** em `react/src/App.tsx`
3. **Criar funções API** em `react/src/lib/api.ts` se necessário
4. **Testar** localmente com `npm run dev`
5. **Build** com `npm run build`
6. **Testar** no WordPress

## Vantagens do React

- Interface moderna e responsiva
- Estado reativo
- Componentização
- Melhor UX com loading states
- TypeScript para type safety
- Hot reload em desenvolvimento

## Mantendo Compatibilidade

O plugin mantém os templates PHP como fallback. Se React não estiver disponível ou desativado, usa PHP normalmente. Isso garante:

- Não quebrar instalações existentes
- Permitir rollback fácil
- Desenvolvimento gradual

