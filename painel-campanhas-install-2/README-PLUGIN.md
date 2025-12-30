# 📦 Plugin Painel de Campanhas - Instruções de Build e Instalação

## 🎯 Sobre

Plugin WordPress completo para gerenciamento de campanhas multicanal (WhatsApp, RCS, SMS) com interface React moderna e integração com microserviço NestJS.

## 🔨 Como Fazer Build do Plugin

### Pré-requisitos

- Node.js 18+ e npm instalados
- Acesso ao terminal/bash

### Build Automático (Recomendado)

Execute o script de build que faz tudo automaticamente:

```bash
cd painel-campanhas-install-2
./build-plugin.sh
```

Este script irá:
1. ✅ Instalar dependências do React (se necessário)
2. ✅ Fazer build da aplicação React
3. ✅ Criar arquivo ZIP do plugin pronto para instalação
4. ✅ Excluir arquivos desnecessários (node_modules, src, etc)

O arquivo `painel-campanhas-install-2.zip` será criado na pasta pai.

### Build Manual

Se preferir fazer manualmente:

```bash
# 1. Build do React
cd painel-campanhas-install-2/react
npm install --legacy-peer-deps
npm run build

# 2. Criar ZIP
cd ..
zip -r ../painel-campanhas-install-2.zip . \
    -x "*/node_modules/*" \
    -x "*/react/src/*" \
    -x "*/.git/*"
```

## 📥 Instalação no WordPress

### Método 1: Via Admin WordPress (Recomendado)

1. Faça login no WordPress como administrador
2. Vá em **Plugins > Adicionar novo**
3. Clique em **Enviar plugin**
4. Selecione o arquivo `painel-campanhas-install-2.zip`
5. Clique em **Instalar agora**
6. Após instalação, clique em **Ativar**

### Método 2: Via FTP/SFTP

1. Extraia o arquivo ZIP
2. Faça upload da pasta `painel-campanhas-install-2` para `/wp-content/plugins/`
3. Vá em **Plugins** no WordPress e ative o plugin

## 🚀 Primeiro Uso

Após ativar o plugin, acesse:

```
https://seu-site.com/painel/login
```

As credenciais são gerenciadas pelo sistema (configuradas no arquivo principal do plugin).

## 📁 Estrutura do Plugin

```
painel-campanhas-install-2/
├── painel-campanhas.php       # Arquivo principal do plugin
├── react-wrapper.php           # Wrapper que carrega a aplicação React
├── react/
│   ├── dist/                   # Build do React (gerado automaticamente)
│   │   ├── index.html
│   │   └── assets/
│   │       ├── index.[hash].js
│   │       └── index.[hash].css
│   ├── src/                    # Código fonte React (não incluído no ZIP)
│   └── package.json
├── *.php                       # Templates PHP (fallback se React não estiver disponível)
└── build-plugin.sh             # Script de build
```

## 🔧 Como Funciona

### Carregamento da Interface

O plugin usa **detecção automática**:

1. ✅ Se `react/dist/index.html` existe → Carrega interface React moderna
2. ❌ Se não existe → Usa templates PHP legados (fallback)

### Rotas Disponíveis

- `/painel/login` - Tela de login
- `/painel/home` - Dashboard
- `/painel/campanhas` - Listagem de campanhas
- `/painel/nova-campanha` - Criar nova campanha
- `/painel/campanhas-recorrentes` - Campanhas recorrentes
- `/painel/aprovar-campanhas` - Aprovação de campanhas
- `/painel/mensagens` - Gerenciamento de mensagens
- `/painel/relatorios` - Relatórios
- `/painel/api-manager` - Gerenciador de APIs
- `/painel/configuracoes` - Configurações

## ⚙️ Integração com Microserviço

O plugin se comunica via AJAX com:

1. **WordPress (backend PHP)** - Gerenciamento de dados locais
2. **Microserviço NestJS** (opcional) - Envio de campanhas

Configure a URL do microserviço em:
- `/painel/api-manager` (via interface)
- Ou editando `painel-campanhas.php` diretamente

## 🐛 Troubleshooting

### Interface antiga aparecendo ao invés do React

**Problema**: Após instalar, aparece interface PHP antiga.

**Solução**:
1. Verifique se executou o build: `./build-plugin.sh`
2. Confirme que `react/dist/` existe no plugin instalado
3. Verifique permissões da pasta `react/dist/`

### Erro 404 nas rotas

**Problema**: URLs do painel retornam 404.

**Solução**:
1. Vá em **Configurações > Links permanentes**
2. Clique em **Salvar alterações** (isso regenera as regras)

### Assets não carregam

**Problema**: CSS/JS não aparecem.

**Solução**:
1. Verifique se o build foi feito corretamente
2. Confirme que arquivos existem em `react/dist/assets/`
3. Verifique console do navegador (F12) para erros

## 📝 Desenvolvimento

### Modo Desenvolvimento

Para desenvolver com hot reload:

```bash
cd react
npm run dev
```

A aplicação estará disponível em `http://localhost:8080`

### Rebuild Após Mudanças

Sempre que alterar o código React:

```bash
cd react
npm run build
```

## 🔐 Segurança

- ✅ Autenticação obrigatória para todas as rotas (exceto login)
- ✅ Nonces WordPress para validação AJAX
- ✅ Sanitização de inputs
- ✅ Validação de permissões

## 📞 Suporte

Para problemas ou dúvidas:
1. Verifique a seção Troubleshooting acima
2. Consulte logs do WordPress em `/wp-content/debug.log`
3. Ative modo debug: `define('WP_DEBUG', true);` no `wp-config.php`

## 📜 Licença

GPLv2 or later
