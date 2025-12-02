# 🚀 Configuração do NestJS no Coolify

## ✅ Sim, o Coolify vai subir PostgreSQL e Redis automaticamente!

O Coolify já está configurando tudo no `docker-compose.yaml` que ele gerou. Você só precisa configurar o `.env` com as variáveis necessárias.

---

## 📝 Variáveis de Ambiente (.env)

Configure estas variáveis no Coolify (seção Environment Variables):

### 🔐 **Obrigatórias - Banco de Dados**

O Coolify já injeta algumas, mas você precisa configurar:

```env
# Database - O Coolify já injeta DATABASE_HOST=postgres e DATABASE_PORT=5432
# Mas você precisa definir:
DATABASE_USER=midpainel
DATABASE_PASSWORD=sua_senha_segura_aqui
DATABASE_NAME=midpainel

# DATABASE_URL completa (Prisma precisa disso)
# Formato: postgresql://USER:PASSWORD@HOST:PORT/DATABASE?schema=public
# O Coolify já injeta DATABASE_HOST=postgres, então use:
DATABASE_URL=postgresql://${DATABASE_USER}:${DATABASE_PASSWORD}@postgres:5432/${DATABASE_NAME}?schema=public
```

**⚠️ IMPORTANTE:** No Coolify, você pode usar as variáveis que ele já injeta. Configure assim:

```env
DATABASE_USER=midpainel
DATABASE_PASSWORD=sua_senha_segura
DATABASE_NAME=midpainel
DATABASE_URL=postgresql://midpainel:sua_senha_segura@postgres:5432/midpainel?schema=public
```

---

### 🔴 **Obrigatórias - Redis**

O Coolify já injeta `REDIS_HOST=redis` e `REDIS_PORT=6379`. Se você configurou senha:

```env
# Se você configurou senha no Redis (opcional)
REDIS_PASSWORD=sua_senha_redis_ou_deixe_vazio
```

---

### 🌐 **Obrigatórias - WordPress**

```env
# URL do WordPress (onde está rodando)
WORDPRESS_URL=https://seu-wordpress.com.br
# OU se estiver na mesma rede Docker:
# WORDPRESS_URL=http://wordpress

# Master API Key (a mesma que você configurou no API Manager do WordPress)
WORDPRESS_API_KEY=sua_master_api_key_aqui
# OU use o nome alternativo:
ACM_MASTER_API_KEY=sua_master_api_key_aqui
```

---

### ⚙️ **Opcionais - NestJS**

```env
# Porta (o Coolify já configura via APP_PORT)
PORT=3000

# Ambiente
NODE_ENV=production

# Log Level
LOG_LEVEL=info
```

---

## 📋 **Resumo Completo do .env para Coolify**

Cole isso no Coolify (Environment Variables):

```env
# ============================================
# DATABASE (PostgreSQL)
# ============================================
DATABASE_USER=midpainel
DATABASE_PASSWORD=SUA_SENHA_SEGURA_AQUI
DATABASE_NAME=midpainel
DATABASE_URL=postgresql://midpainel:SUA_SENHA_SEGURA_AQUI@postgres:5432/midpainel?schema=public

# ============================================
# REDIS
# ============================================
# O Coolify já injeta REDIS_HOST=redis e REDIS_PORT=6379
# Se você configurou senha no Redis, descomente:
# REDIS_PASSWORD=sua_senha_redis

# ============================================
# WORDPRESS INTEGRATION
# ============================================
WORDPRESS_URL=https://seu-wordpress.com.br
WORDPRESS_API_KEY=SUA_MASTER_API_KEY_AQUI

# ============================================
# NESTJS
# ============================================
PORT=3000
NODE_ENV=production
LOG_LEVEL=info
```

---

## 🔍 **Como o Coolify Funciona**

1. **PostgreSQL e Redis:** O Coolify já está configurando no `docker-compose.yaml` que ele gerou
2. **Networks:** O Coolify cria a rede `tk8044owosgwsw84osssgkks` onde todos os serviços se comunicam
3. **Service Names:** O Coolify usa os nomes `postgres` e `redis` como hostnames
4. **Health Checks:** O Coolify já configurou health checks para garantir que os serviços estão prontos

---

## ✅ **Checklist de Configuração**

- [ ] Configure `DATABASE_USER`, `DATABASE_PASSWORD`, `DATABASE_NAME` no Coolify
- [ ] Configure `DATABASE_URL` completa (Prisma precisa disso)
- [ ] Configure `WORDPRESS_URL` (URL pública do WordPress)
- [ ] Configure `WORDPRESS_API_KEY` (Master API Key do WordPress)
- [ ] Se configurou senha no Redis, adicione `REDIS_PASSWORD`
- [ ] Deploy e verifique os logs

---

## 🧪 **Testar Após Deploy**

### 1. Verificar se o NestJS está rodando:

```bash
curl https://middleware.painel.taticamarketing.com.br/health
```

### 2. Testar endpoint de dispatch:

```bash
curl -X POST https://middleware.painel.taticamarketing.com.br/campaigns/dispatch \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: sua_master_api_key" \
  -d '{"agendamento_id": "C20241201120000"}'
```

### 3. Verificar logs no Coolify:

- Vá em **Logs** do serviço `app`
- Procure por erros de conexão com PostgreSQL ou Redis
- Procure por erros de conexão com WordPress

---

## 🐛 **Troubleshooting**

### Erro: "Cannot connect to PostgreSQL"

**Causa:** `DATABASE_URL` incorreta ou PostgreSQL não está pronto.

**Solução:**
1. Verifique se o PostgreSQL está rodando (Coolify mostra status)
2. Verifique se `DATABASE_URL` está correta
3. Verifique se `DATABASE_USER`, `DATABASE_PASSWORD`, `DATABASE_NAME` estão corretos

### Erro: "Cannot connect to Redis"

**Causa:** Redis não está pronto ou senha incorreta.

**Solução:**
1. Verifique se o Redis está rodando (Coolify mostra status)
2. Se configurou senha, verifique `REDIS_PASSWORD`
3. O Coolify já injeta `REDIS_HOST=redis` e `REDIS_PORT=6379`

### Erro: "Cannot connect to WordPress"

**Causa:** `WORDPRESS_URL` incorreta ou WordPress não está acessível.

**Solução:**
1. Verifique se `WORDPRESS_URL` está acessível publicamente
2. Teste: `curl https://seu-wordpress.com.br/wp-json/`
3. Verifique se a Master API Key está correta

### Erro: "Prisma Client not generated"

**Causa:** Prisma não gerou o client durante o build.

**Solução:**
1. O Dockerfile já gera o Prisma Client
2. Se ainda der erro, verifique os logs do build no Coolify
3. Certifique-se que o `prisma/schema.prisma` está no repositório

---

## 📝 **Notas Importantes**

1. **Service Names:** No Coolify, os serviços se comunicam pelos nomes:
   - `postgres` (não `localhost`)
   - `redis` (não `localhost`)
   - `app` (seu NestJS)

2. **DATABASE_URL:** O Prisma precisa da URL completa. Use o formato:
   ```
   postgresql://USER:PASSWORD@HOST:PORT/DATABASE?schema=public
   ```

3. **WORDPRESS_URL:** Use a URL pública (HTTPS) do WordPress, não `http://wordpress` (isso só funciona na mesma rede Docker).

4. **Porta:** O Coolify já configura a porta via `APP_PORT`. Não precisa mudar.

---

## 🎯 **Próximos Passos**

Após configurar o `.env` e fazer o deploy:

1. ✅ Configure a URL do NestJS no WordPress (API Manager)
2. ✅ Teste aprovar uma campanha
3. ✅ Verifique os logs do NestJS
4. ✅ Verifique se as mensagens estão sendo enviadas

---

## 📚 **Referências**

- Dockerfile: `/Dockerfile`
- docker-compose.yaml: `/docker-compose.yaml`
- Configuração WordPress: `src/config/wordpress.config.ts`
- Configuração Database: `src/config/database.config.ts`
- Configuração Redis: `src/config/bullmq.config.ts`

