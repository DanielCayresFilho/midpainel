# Passo a Passo para Ativar o Plugin

## ✅ Passos Necessários

### 1. Fazer Build do React (IMPORTANTE!)
Como atualizamos o React 19.2.3 e outras dependências, é necessário fazer um novo build:

```bash
cd react
npm run build
```

Isso vai gerar os arquivos atualizados na pasta `dist/`.

### 2. Desativar e Reativar o Plugin no WordPress

No admin do WordPress:
1. Vá em **Plugins**
2. **Desative** o plugin "Painel Campanhas"
3. **Ative** novamente o plugin

Isso vai:
- Registrar as rotas (`flush_rewrite_rules()`)
- Garantir que todas as configurações estejam aplicadas

### 3. Limpar Cache (se usar)
Se estiver usando algum plugin de cache, limpe o cache após ativar.

## ⚠️ Importante

- **O build do React é necessário** porque atualizamos dependências (React 19.2.3, Vite 6.0.0, etc.)
- **Desativar/ativar é necessário** para registrar as rewrite rules do WordPress
- **Após isso, tudo deve funcionar normalmente!**

## Verificação

Após ativar, acesse:
- `localhost/wordpress/painel/home`
- Deve carregar a interface React

