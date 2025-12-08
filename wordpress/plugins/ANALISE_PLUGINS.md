# Análise de Plugins - O que pode ser removido?

## ✅ **PLUGINS QUE DEVEM SER MANTIDOS** (Essenciais)

### 1. **api_consumer_manager** ⚠️ **MANTER** (mas pode ser integrado)
- **Função**: Gerencia credenciais de API (Master API Key, Provider Credentials)
- **Status**: Essencial - o Painel de Campanhas usa suas funções
- **Ação**: Manter, mas pode integrar a interface no Painel de Campanhas futuramente

### 2. **get_agendamentos** ⚠️ **MANTER** (mas pode ser integrado)
- **Função**: Endpoints REST API para agendamentos, aprovação de campanhas
- **Status**: Essencial - fornece dados para o NestJS
- **Ação**: Manter, mas a interface de aprovação pode ser migrada para o Painel

### 3. **data-receiver** ✅ **MANTER**
- **Função**: Recebe dados do NestJS (webhooks)
- **Status**: Essencial - comunicação backend

### 4. **Consumers (cda_consumer, gosac_consumer, noah_consumer, salesforce, wp-rcs-otima-consumer)** ✅ **MANTER**
- **Função**: Consomem APIs dos providers e enviam para NestJS
- **Status**: Essenciais - comunicação com providers

### 5. **message_template_manager** ⚠️ **MANTER** (mas pode ser integrado)
- **Função**: Gerencia templates de mensagem
- **Status**: Útil - pode ser integrado no Painel futuramente

### 6. **cpf-campaign-manager** ⚠️ **MANTER** (mas pode ser integrado)
- **Função**: Higienização de base CPF
- **Status**: Útil - pode ser integrado no Painel futuramente

### 7. **endpoint_unique** ✅ **MANTER**
- **Função**: Endpoints únicos específicos
- **Status**: Pode ser essencial dependendo do uso

### 8. **hookv2** ✅ **MANTER**
- **Função**: Sistema de hooks
- **Status**: Pode ser essencial

---

## ❌ **PLUGINS QUE PODEM SER REMOVIDOS** (Substituídos pelo Painel de Campanhas)

### 1. **public-dashboard** ❌ **REMOVER**
- **Função**: Dashboard público antigo
- **Motivo**: Substituído completamente pelo **Painel de Campanhas**
- **Ação**: Desativar e remover após confirmar que tudo funciona no novo painel

### 2. **painel-login-shortcode** ❌ **REMOVER** (se existir)
- **Função**: Shortcode de login
- **Motivo**: Login agora está integrado no Painel de Campanhas
- **Ação**: Remover se existir

### 3. **api-credentials-migrator** ❌ **REMOVER** (se ainda existir)
- **Função**: Migração de credenciais
- **Motivo**: Funcionalidade já integrada no API Manager
- **Ação**: Remover se ainda estiver ativo

---

## ⚠️ **PLUGINS PARA AVALIAR** (Dependem do uso)

### 1. **campaign-manager** ⚠️ **AVALIAR**
- **Função**: Sistema antigo de gerenciamento de campanhas
- **Status**: Pode ter funcionalidades que ainda não foram migradas
- **Ação**: 
  - Verificar se há funcionalidades únicas
  - Migrar funcionalidades necessárias para o Painel
  - Remover após migração completa

### 2. **users** ⚠️ **AVALIAR**
- **Função**: Relatório de envios pendentes
- **Status**: Pode ser útil, mas pode ser integrado no Painel
- **Ação**: Avaliar se os relatórios do Painel são suficientes

### 3. **csv** ⚠️ **AVALIAR**
- **Função**: Export CSV de envios pendentes
- **Status**: Pode ser útil, mas pode ser integrado no Painel
- **Ação**: Avaliar necessidade

### 4. **dataview** ⚠️ **AVALIAR**
- **Função**: Visualização de dados
- **Status**: Não está claro o uso
- **Ação**: Verificar se é usado

---

## 🔧 **PLUGINS DE TERCEIROS** (Manter se em uso)

### 1. **elementor** ✅ **MANTER** (se usado)
- **Função**: Page builder
- **Status**: Se usado para páginas públicas, manter

### 2. **wp-crontrol** ✅ **MANTER** (se usado)
- **Função**: Gerenciamento de cron jobs
- **Status**: Útil para debug/manutenção

### 3. **wp-file-manager** ⚠️ **AVALIAR**
- **Função**: Gerenciamento de arquivos
- **Status**: Se não usado, pode remover

### 4. **white-label-cms** ⚠️ **AVALIAR**
- **Função**: Customização do admin
- **Status**: Se não usado, pode remover

### 5. **really-simple-ssl** ✅ **MANTER** (se usado)
- **Função**: SSL/HTTPS
- **Status**: Se usado para SSL, manter

### 6. **akismet** ✅ **MANTER** (se usado)
- **Função**: Anti-spam
- **Status**: Se usado, manter

---

## 📋 **RESUMO - AÇÃO IMEDIATA**

### ✅ **Pode remover AGORA:**
1. ❌ **public-dashboard** - Substituído pelo Painel de Campanhas
2. ❌ **painel-login-shortcode** (se existir) - Login integrado
3. ❌ **api-credentials-migrator** (se ainda existir) - Funcionalidade migrada

### ⚠️ **Avaliar e migrar depois:**
1. **campaign-manager** - Verificar funcionalidades únicas
2. **users** - Verificar se relatórios são suficientes
3. **csv** - Verificar necessidade de export

### ✅ **Manter sempre:**
- **api_consumer_manager** - Essencial
- **get_agendamentos** - Essencial (endpoints REST)
- **data-receiver** - Essencial (webhooks)
- **Consumers** (cda, gosac, noah, salesforce, rcs) - Essenciais
- **message_template_manager** - Útil
- **cpf-campaign-manager** - Útil

---

## 🎯 **PLANO DE MIGRAÇÃO RECOMENDADO**

### Fase 1: Remoção Imediata (Seguro)
1. Desativar `public-dashboard`
2. Testar todas as funcionalidades no novo Painel
3. Se tudo OK, remover `public-dashboard`

### Fase 2: Integração (Futuro)
1. Integrar interface do API Manager no Painel de Campanhas
2. Integrar funcionalidades do `campaign-manager` se necessário
3. Integrar relatórios do `users` no Painel
4. Integrar `cpf-campaign-manager` no Painel (já tem página de higienização)

### Fase 3: Limpeza Final
1. Remover plugins não utilizados
2. Consolidar funcionalidades no Painel de Campanhas

---

## ⚠️ **ATENÇÃO**

**NÃO REMOVA** os seguintes plugins sem verificar:
- Qualquer consumer (cda, gosac, noah, salesforce, rcs)
- `get_agendamentos` (endpoints REST essenciais)
- `data-receiver` (webhooks essenciais)
- `api_consumer_manager` (gerenciamento de credenciais)

**SEMPRE TESTE** antes de remover qualquer plugin em produção!

