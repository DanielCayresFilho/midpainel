# 📄 Resumo das Páginas do Plugin - Painel de Campanhas

## 🔐 Login (`/painel/login`)
Página de autenticação. Permite fazer login no sistema usando credenciais do WordPress. Após o login, redireciona para o dashboard.

---

## 🏠 Dashboard (`/painel/home`)
Página inicial do sistema. Exibe:
- Total de campanhas criadas
- Campanhas pendentes de aprovação
- Campanhas enviadas
- Campanhas criadas hoje
- Lista das últimas campanhas executadas
- Visão geral rápida do sistema

---

## 📋 Minhas Campanhas (`/painel/campanhas`)
Lista todas as campanhas criadas. Permite:
- Visualizar campanhas por status (pendente, enviado, negado, etc)
- Filtrar por fornecedor, ambiente, usuário
- Ver detalhes de cada campanha
- Navegar entre páginas de resultados

---

## ➕ Nova Campanha (`/painel/nova-campanha`)
Criação de campanha usando bases de dados (VW_BASE*). Permite:
- Selecionar base de dados
- Aplicar filtros avançados (cidade, estado, faixa etária, etc)
- Escolher template de mensagem
- Distribuir entre múltiplos provedores (CDA, GOSAC, NOAH, etc)
- Agendar para envio imediato ou aguardar aprovação

---

## 📁 Campanha via Arquivo (`/painel/campanha-arquivo`)
Criação de campanha através de upload de arquivo CSV. Permite:
- Enviar arquivo CSV com dados dos clientes
- Validar formato do arquivo (telefone, CPF obrigatórios)
- Escolher template de mensagem
- Selecionar provedor
- Criar campanha diretamente dos dados do arquivo

---

## 🔄 Campanhas Recorrentes (`/painel/campanhas-recorrentes`)
Gerencia templates de campanhas que podem ser executadas automaticamente. Permite:
- Criar templates de campanha recorrente
- Definir frequência de execução (diária, semanal, etc)
- Ver histórico de execuções
- Executar manualmente ou editar templates
- Ativar/desativar campanhas recorrentes

---

## ✅ Aprovar Campanhas (`/painel/aprovar-campanhas`)
**Apenas para Administradores.** Página para aprovar ou negar campanhas pendentes. Permite:
- Visualizar campanhas aguardando aprovação
- Ver detalhes da campanha (mensagem, filtros, quantidade)
- Aprovar ou negar campanhas
- Adicionar observações ao negar

---

## 💬 Templates de Mensagem (`/painel/mensagens`)
Gerencia os templates de mensagens usados nas campanhas. Permite:
- Criar novos templates de mensagem
- Editar templates existentes
- Excluir templates
- Ver lista de todos os templates salvos
- Os templates são salvos como Custom Post Types do WordPress

---

## 📊 Relatórios (`/painel/relatorios`)
Visualiza relatórios e estatísticas de envios. Permite:
- Ver estatísticas por status (enviado, pendente, negado, etc)
- Filtrar por usuário, fornecedor, ambiente, data
- Visualizar envios 1x1 (individuais)
- Exportar dados em CSV
- Ver tabela detalhada de todos os envios

---

## 💰 Controle de Custo (`/painel/controle-custo`)
Menu principal do módulo de controle de custos. Oferece acesso a:
- Cadastro de custos e orçamentos
- Relatório de custos

---

## 💵 Cadastro de Custos (`/painel/controle-custo/cadastro`)
Gerencia custos e orçamentos do sistema. Permite:
- Cadastrar custo por mensagem por provedor (CDA, GOSAC, etc)
- Definir orçamento por carteira
- Ver orçamentos cadastrados
- Editar ou excluir orçamentos
- Distribuir orçamento entre bases vinculadas à carteira

---

## 📈 Relatório de Custos (`/painel/controle-custo/relatorio`)
Visualiza relatórios financeiros. Permite:
- Ver gastos por provedor
- Ver gastos por carteira/base
- Filtrar por período (data inicial e final)
- Visualizar comparativo de custos
- Acompanhar consumo de orçamento

---

## ⚙️ Configurações (`/painel/configuracoes`)
**Apenas para Administradores.** Gerencia configurações principais do sistema. Permite:
- Criar e editar carteiras
- Vincular bases de dados às carteiras
- Ativar/desativar carteiras
- Gerenciar relacionamento entre carteiras e bases

---

## 🔑 API Manager (`/painel/api-manager`)
**Apenas para Administradores.** Gerencia configurações de API e integrações. Permite:
- Configurar Master API Key
- Definir URLs de microserviços
- Gerenciar credenciais de API
- Configurar endpoints de integração

---

## 📝 Observações Importantes

- **Acesso**: Algumas páginas são restritas apenas para administradores
- **Navegação**: Todas as páginas (exceto login) incluem menu lateral para navegação rápida
- **Autenticação**: Páginas protegidas redirecionam para login se o usuário não estiver autenticado
- **URLs**: Todas as páginas seguem o padrão `/painel/nome-da-pagina`

