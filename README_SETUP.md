# 🚀 Setup - Midpainel NestJS

## 📋 Pré-requisitos

- Node.js 20+
- pnpm 10.24.0+
- Docker e Docker Compose
- PostgreSQL 16+
- Redis 7+

## 🔧 Instalação

### 1. Instalar dependências

```bash
pnpm install
```

### 2. Configurar variáveis de ambiente

Copie o arquivo `.env.example` para `.env`:

```bash
cp .env.example .env
```

Edite o `.env` com suas configurações:

```env
# Database
DATABASE_URL="postgresql://midpainel:password@postgres:5432/midpainel?schema=public"
DATABASE_HOST=postgres
DATABASE_PORT=5432
DATABASE_USER=midpainel
DATABASE_PASSWORD=your_password_here
DATABASE_NAME=midpainel

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# WordPress Integration
WORDPRESS_URL=http://wordpress
WORDPRESS_API_KEY=your-master-api-key-here

# NestJS
PORT=3000
NODE_ENV=development
```

### 3. Configurar Prisma

```bash
# Gerar cliente Prisma
npx prisma generate

# Executar migrations
npx prisma migrate dev --name init
```

### 4. Iniciar serviços (Docker)

```bash
docker-compose up -d
```

Isso irá iniciar:
- PostgreSQL (porta 5432)
- Redis (porta 6379)
- Aplicação NestJS (porta 3000)

### 5. Executar aplicação em desenvolvimento

```bash
pnpm run start:dev
```

A aplicação estará disponível em: `http://localhost:3000`

## 🧪 Testar

### Testar endpoint de dispatch

```bash
curl -X POST http://localhost:3000/campaigns/dispatch \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: your-master-api-key-here" \
  -d '{"agendamento_id": "C20241201120000"}'
```

### Testar status de campanha

```bash
curl -X GET http://localhost:3000/campaigns/{campaign_id}/status \
  -H "X-API-KEY: your-master-api-key-here"
```

## 📁 Estrutura do Projeto

```
src/
├── campaigns/          # Módulo de campanhas
│   ├── dto/           # Data Transfer Objects
│   ├── campaigns.controller.ts
│   ├── campaigns.service.ts
│   └── campaigns.module.ts
├── jobs/              # Processadores de jobs (BullMQ)
│   ├── dispatch-campaign.processor.ts
│   └── jobs.module.ts
├── providers/         # Providers de envio
│   ├── base/         # Classe base e interfaces
│   ├── cda/          # Provider CDA
│   ├── gosac/        # Provider GOSAC
│   ├── noah/         # Provider NOAH
│   ├── rcs/          # Provider RCS
│   └── salesforce/   # Provider Salesforce
├── webhook/          # Webhooks para WordPress
├── config/           # Configurações
├── common/           # Utilitários comuns
└── prisma/           # Serviço Prisma
```

## 🔄 Próximos Passos

1. ✅ Infraestrutura base criada
2. ⏳ Implementar Provider CDA
3. ⏳ Implementar outros providers
4. ⏳ Implementar webhook de status
5. ⏳ Testes e validação

## 📚 Documentação

Consulte os documentos em `archs/docs/`:
- `DOCUMENTO_COMPLETO_MIGRACAO.md` - Documentação completa
- `PLANO_MIGRACAO.md` - Plano de execução
- `ARQUITETURA_TECNICA.md` - Detalhes técnicos

## 🐛 Troubleshooting

### Erro de conexão com banco

Verifique se o PostgreSQL está rodando:
```bash
docker-compose ps
```

### Erro de conexão com Redis

Verifique se o Redis está rodando:
```bash
docker-compose ps
redis-cli ping
```

### Erro de Prisma

Regenere o cliente:
```bash
npx prisma generate
```

