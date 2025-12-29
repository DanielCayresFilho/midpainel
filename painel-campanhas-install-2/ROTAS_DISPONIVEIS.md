# 🚀 Guia de Rotas - Painel de Campanhas

## Como Acessar as Páginas

Todas as páginas do plugin são acessadas através da URL base do seu WordPress + `/painel/` + nome da página.

**Formato:** `http://seusite.com/painel/nome-da-pagina`

---

## 📋 Lista Completa de Rotas

### 🔐 Autenticação
- **`/painel/login`** - Página de login
  - Acesse primeiro para fazer login no sistema
  - Exemplo: `http://seusite.com/painel/login`

### 🏠 Dashboard e Navegação Principal
- **`/painel/home`** - Dashboard principal (não é `/painel/dashboard`)
  - Estatísticas gerais, últimas campanhas, ações rápidas
  - Exemplo: `http://seusite.com/painel/home`

### 📢 Campanhas
- **`/painel/campanhas`** - Listagem de todas as campanhas
  - Visualiza todas as suas campanhas com filtros
  - Exemplo: `http://seusite.com/painel/campanhas`

- **`/painel/nova-campanha`** - Criar nova campanha (normal)
  - Criação de campanha usando bases VW_BASE*
  - Exemplo: `http://seusite.com/painel/nova-campanha`

- **`/painel/campanha-arquivo`** - Criar campanha via arquivo CSV
  - Upload de arquivo CSV com dados da campanha
  - Exemplo: `http://seusite.com/painel/campanha-arquivo`

- **`/painel/campanhas-recorrentes`** - Gerenciar campanhas recorrentes
  - Templates de campanhas salvos para execução automática
  - Exemplo: `http://seusite.com/painel/campanhas-recorrentes`

- **`/painel/aprovar-campanhas`** - Aprovar campanhas pendentes ⚠️ (Apenas Admin)
  - Aprova ou nega campanhas aguardando aprovação
  - Exemplo: `http://seusite.com/painel/aprovar-campanhas`

### 💬 Mensagens
- **`/painel/mensagens`** - Gerenciar templates de mensagem
  - CRUD de templates de mensagem para usar nas campanhas
  - Exemplo: `http://seusite.com/painel/mensagens`

### 📊 Relatórios
- **`/painel/relatorios`** - Relatórios e estatísticas gerais
  - Relatórios de campanhas, estatísticas 1x1
  - Exemplo: `http://seusite.com/painel/relatorios`

- **`/painel/controle-custo`** - Página principal de controle de custos
  - Menu com links para cadastro e relatório
  - Exemplo: `http://seusite.com/painel/controle-custo`

- **`/painel/controle-custo/cadastro`** - Cadastro de custos e orçamentos
  - Cadastra custos por provider e orçamentos por base
  - Exemplo: `http://seusite.com/painel/controle-custo/cadastro`

- **`/painel/controle-custo/relatorio`** - Relatório de custos
  - Visualiza gastos por provider e por base
  - Exemplo: `http://seusite.com/painel/controle-custo/relatorio`

### ⚙️ Configurações (Apenas Admin)
- **`/painel/configuracoes`** - Configurações do sistema
  - CRUD de carteiras, vincular bases às carteiras
  - Exemplo: `http://seusite.com/painel/configuracoes`

- **`/painel/api-manager`** - Gerenciamento de API
  - Configura credenciais de API, URLs de microserviço
  - Exemplo: `http://seusite.com/painel/api-manager`

---

## 🔄 Fluxo de Uso Recomendado

### Primeiro Acesso:
1. Acesse `/painel/login` para fazer login
2. Após login, você será redirecionado para `/painel/home`

### Configuração Inicial (Admin):
1. `/painel/configuracoes` - Criar carteiras e vincular bases
2. `/painel/controle-custo/cadastro` - Cadastrar custos e orçamentos
3. `/painel/api-manager` - Configurar API e credenciais

### Uso Diário:
1. `/painel/home` - Ver dashboard e estatísticas
2. `/painel/nova-campanha` ou `/painel/campanha-arquivo` - Criar campanhas
3. `/painel/campanhas` - Ver e gerenciar campanhas
4. `/painel/aprovar-campanhas` - Aprovar campanhas (admin)
5. `/painel/relatorios` - Ver relatórios
6. `/painel/controle-custo/relatorio` - Acompanhar gastos

---

## ⚠️ Importante

### Permissões:
- **Administradores**: Acesso a todas as páginas
- **Assinantes**: Podem criar campanhas, ver relatórios, mas NÃO podem:
  - Aprovar campanhas (`/painel/aprovar-campanhas`)
  - Acessar API Manager (`/painel/api-manager`)
  - Acessar Configurações (`/painel/configuracoes`)

### Redirecionamentos:
- Se você tentar acessar qualquer página sem estar logado, será redirecionado para `/painel/login`
- Se você tentar acessar `/painel/login` já estando logado, será redirecionado para `/painel/home`

### Flush de Rewrite Rules:
Se as rotas não funcionarem após instalar o plugin:
1. Vá em WordPress Admin > Configurações > Links Permanentes
2. Clique em "Salvar alterações" (sem mudar nada)
3. Isso força o WordPress a recarregar as rotas

---

## 🆘 Problemas Comuns

### Erro 404 nas rotas:
- Vá em Configurações > Links Permanentes e salve novamente
- Desative e reative o plugin

### Redirecionamento infinito:
- Limpe o cache do WordPress
- Verifique se há conflito com outros plugins de rotas

### Página não encontrada:
- Certifique-se de que o plugin está ativo
- Verifique se a URL está correta (ex: `/painel/home` e não `/painel/dashboard`)

