# 🚀 MidPainel - Sistema de Gerenciamento de Campanhas

Sistema backend desenvolvido em NestJS para gerenciamento e disparo de campanhas de mensageria através de múltiplos provedores.

## 📋 Sobre

O MidPainel é uma API robusta que recebe solicitações de campanhas do WordPress, processa os dados e dispara mensagens através de diferentes provedores de mensageria (CDA, GOSAC, NOAH, RCS, Salesforce). Utiliza filas BullMQ para processamento assíncrono e garante alta disponibilidade com retry automático e tratamento de erros.

## ✨ Funcionalidades

- ✅ **Múltiplos Provedores**: Suporte para CDA, GOSAC, NOAH, RCS e Salesforce
- ✅ **Processamento Assíncrono**: Fila de jobs com BullMQ e Redis
- ✅ **Retry Automático**: Tentativas automáticas com backoff exponencial
- ✅ **Webhooks**: Notificação em tempo real para WordPress sobre status das campanhas
- ✅ **Normalização de Telefones**: Formatação automática de números (código do país)
- ✅ **Salesforce Integration**: Envio em duas etapas (Salesforce + Marketing Cloud com agendamento de 20 minutos)

## 🏗️ Arquitetura

```
┌─────────────┐
│ WordPress   │  ──POST──>  ┌─────────────┐
│  (Frontend) │             │   NestJS    │
└─────────────┘             │   (API)     │
      ▲                     └─────────────┘
      │                            │
      │                            ▼
      │                     ┌─────────────┐
      │                     │   BullMQ    │
      │                     │   (Queue)   │
      │                     └─────────────┘
      │                            │
      │                            ▼
      │                     ┌─────────────┐
      │                     │  Providers  │
      │                     │  (CDA, etc) │
      │                     └─────────────┘
      │
      └─────────── Webhook ───────────┘
```

## 🛠️ Tecnologias

- **NestJS** - Framework Node.js
- **TypeScript** - Linguagem
- **Prisma** - ORM para PostgreSQL
- **BullMQ** - Sistema de filas
- **Redis** - Cache e filas
- **PostgreSQL** - Banco de dados
- **Axios** - Cliente HTTP

## 📦 Instalação

### Pré-requisitos

- Node.js 18+
- pnpm
- Docker e Docker Compose
- PostgreSQL
- Redis

### Passos

1. Clone o repositório:
```bash
git clone <repository-url>
cd midpainel
```

2. Instale as dependências:
```bash
pnpm install
```

3. Configure as variáveis de ambiente:
```bash
cp .env.example .env
# Edite o .env com suas configurações
```

4. Configure o banco de dados:
```bash
# Gere o Prisma Client
npx prisma generate

# Execute as migrations
npx prisma migrate dev
```

5. Inicie os serviços (PostgreSQL e Redis):
```bash
docker-compose up -d
```

6. Execute a aplicação:
```bash
# Desenvolvimento
pnpm run start:dev

# Produção
pnpm run build
pnpm run start:prod
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Aplicação
PORT=3000
NODE_ENV=production

# Banco de Dados
DATABASE_URL=postgresql://user:password@localhost:5432/midpainel

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# WordPress
WORDPRESS_URL=https://seu-site.com
WORDPRESS_API_KEY=sua-api-key

# CORS
CORS_ORIGIN=https://seu-site.com
```

## 📡 API Endpoints

### POST `/campaigns/dispatch`

Dispara uma nova campanha.

**Headers:**
```
X-API-Key: sua-api-key
Content-Type: application/json
```

**Body:**
```json
{
  "agendamento_id": "AG123456",
  "provider": "CDA",
  "data": [
    {
      "telefone": "14988117592",
      "nome": "João Silva",
      "mensagem": "Olá, esta é uma mensagem de teste"
    }
  ]
}
```

### GET `/campaigns/:id/status`

Retorna o status de uma campanha.

**Headers:**
```
X-API-Key: sua-api-key
```

## 🔌 Provedores Suportados

### CDA
- Envio de mensagens SMS
- Suporte a templates e mensagens personalizadas

### GOSAC
- Envio de mensagens SMS
- Suporte a múltiplos formatos

### NOAH
- Envio de mensagens SMS
- Integração via API REST

### RCS
- Envio de mensagens RCS (Rich Communication Services)
- Suporte a templates, documentos e textos

### Salesforce
- Envio em duas etapas:
  1. Criação/atualização de contatos na Salesforce
  2. Disparo automático no Marketing Cloud após 20 minutos

## 🔄 Fluxo de Processamento

1. **Recebimento**: WordPress envia requisição para `/campaigns/dispatch`
2. **Validação**: API valida dados e autenticação
3. **Enfileiramento**: Job é adicionado à fila do provedor correspondente
4. **Processamento**: Worker processa o job assincronamente
5. **Envio**: Provider envia mensagens para a API externa
6. **Atualização**: Status da campanha é atualizado no banco
7. **Webhook**: WordPress é notificado sobre o status final

## 📊 Estrutura do Projeto

```
src/
├── campaigns/          # Módulo de campanhas (controller, service, DTOs)
├── jobs/              # Processadores de jobs (BullMQ)
│   └── providers/     # Processadores específicos por provedor
├── providers/         # Implementações dos provedores
│   ├── base/         # Classe base e interfaces
│   ├── cda/          # Provider CDA
│   ├── gosac/        # Provider GOSAC
│   ├── noah/         # Provider NOAH
│   ├── rcs/          # Provider RCS
│   └── salesforce/   # Provider Salesforce
├── webhook/          # Serviço de webhooks para WordPress
├── config/           # Configurações (BullMQ, WordPress)
├── common/           # Utilitários comuns (guards, etc)
└── prisma/           # Serviço Prisma
```

## 🧪 Testes

```bash
# Testes unitários
pnpm run test

# Testes e2e
pnpm run test:e2e

# Cobertura de testes
pnpm run test:cov
```

## 🐳 Docker

```bash
# Build
docker-compose build

# Iniciar serviços
docker-compose up -d

# Logs
docker-compose logs -f

# Parar serviços
docker-compose down
```

## 📝 Scripts Disponíveis

```bash
pnpm run build          # Compila o projeto
pnpm run start          # Inicia em modo produção
pnpm run start:dev      # Inicia em modo desenvolvimento (watch)
pnpm run start:debug    # Inicia em modo debug
pnpm run lint           # Executa o linter
pnpm run format         # Formata o código
```

## 🔒 Segurança

- Autenticação via API Key
- Validação de dados com class-validator
- Sanitização de inputs
- CORS configurável
- Retry com backoff para evitar rate limiting

## 📈 Monitoramento

- Logs estruturados com NestJS Logger
- Status de jobs no BullMQ Dashboard (se configurado)
- Webhooks para notificação de status

## 🤝 Contribuindo

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto é privado e proprietário.

## 👥 Autores

- **Equipe de Desenvolvimento**

---

**Desenvolvido com ❤️ usando NestJS**
