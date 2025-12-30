# Painel de Campanhas - Plugin WordPress

## 📋 Descrição

Sistema **COMPLETO e INDEPENDENTE** de gerenciamento de campanhas multicanal (WhatsApp, RCS, SMS) para WordPress.

Este plugin **NÃO requer nenhum outro plugin** para funcionar. Todas as tabelas e funcionalidades são criadas e gerenciadas internamente.

## ✨ Características

- ✅ **100% Independente** - Não precisa de outros plugins
- 🎯 **Multi-canal** - WhatsApp, RCS, SMS
- 💰 **Controle de Custos** - Gerenciamento de custos por provider
- 👥 **Carteiras** - Organização por carteiras de clientes
- ✅ **Aprovação de Campanhas** - Workflow de aprovação
- 📊 **Relatórios Completos** - Dashboard com métricas em tempo real
- 🔄 **Campanhas Recorrentes** - Agendamento automático
- 🚫 **Blocklist** - Bloqueio de telefones e CPFs
- 🎣 **Iscas** - Números de teste para campanhas
- 🔗 **Integração Microserviço** - API REST para NestJS

## 🎯 Providers Suportados

1. **RCS Ótima** - Templates RCS via API Ótima Digital
2. **WhatsApp Ótima** - Mensagens HSM via API Ótima Digital
3. **RCS CDA** - RCS via CromosApp
4. **CDA** - Campanha direta via API CDA
5. **GOSAC** - Plataforma WhatsApp GOSAC
6. **NOAH** - Sistema de contatos NOAH
7. **Salesforce** - Integração com Salesforce + Marketing Cloud

## 📦 Instalação

1. Faça upload da pasta para /wp-content/plugins/
2. Ative o plugin no painel do WordPress
3. Acesse **Painel > Dashboard** no menu lateral

## 🗄️ Tabelas Criadas Automaticamente

- wp_envios_pendentes - Tabela principal de campanhas
- wp_pc_custos_providers - Custos por provider
- wp_pc_orcamentos_bases - Orçamentos por base
- wp_pc_carteiras - Cadastro de carteiras
- wp_cm_baits - Números de teste
- wp_pc_blocklist - Lista de bloqueio
- wp_cm_recurring_campaigns - Campanhas recorrentes

## 🚀 Como Usar

1. Configure o API Manager com credenciais dos providers
2. Cadastre carteiras e vincule às bases
3. Crie campanhas e aprove para envio
4. Acompanhe relatórios em tempo real

## 🔒 Totalmente Independente

Este plugin cria TODAS as tabelas necessárias automaticamente.
Não depende de nenhum outro plugin para funcionar!

---
**Versão:** 1.0.0 | **Autor:** Daniel Cayres
