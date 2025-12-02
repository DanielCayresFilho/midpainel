# 🔴 Debug: Redis Health Check Failing no Coolify

## Problema Persistente

Mesmo sem senha, o Redis está falhando no health check. Isso pode ser causado por:

1. **Health check muito rápido** - Redis precisa de tempo para iniciar
2. **Comando de health check incorreto** - O Coolify pode estar usando um comando que não funciona
3. **Redis não está iniciando** - Pode haver erro no startup do Redis

---

## 🔍 Passos para Debug

### 1. Verificar Logs do Redis no Coolify

No Coolify, vá em **Logs** do container `redis-...` e verifique se há erros.

**O que procurar:**
- Erros de inicialização
- Mensagens de "Ready to accept connections"
- Qualquer erro relacionado a permissões ou volumes

---

### 2. Verificar Health Check no Coolify

O Coolify gera o health check automaticamente. Verifique no `docker-compose.yaml` gerado se o health check está assim:

```yaml
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
  interval: 10s
  timeout: 5s
  retries: 5
```

**Problema possível:** O `timeout: 5s` pode ser muito curto se o Redis estiver demorando para iniciar.

---

## ✅ Soluções

### Solução 1: Aumentar Timeout do Health Check (se possível)

Se o Coolify permitir, aumente o timeout do health check para `10s` ou `15s`.

---

### Solução 2: Usar Health Check Mais Robusto

O problema pode ser que `redis-cli ping` não está funcionando. Tente um health check alternativo:

```yaml
healthcheck:
  test: ["CMD-SHELL", "redis-cli ping || exit 1"]
  interval: 10s
  timeout: 10s
  retries: 5
  start_period: 10s
```

O `start_period: 10s` dá tempo para o Redis iniciar antes de começar a verificar.

---

### Solução 3: Verificar se Redis Está Iniciando

Execute manualmente no container do Redis:

```bash
docker exec -it redis-tk8044owosgwsw84osssgkks-... redis-cli ping
```

Se retornar `PONG`, o Redis está funcionando, mas o health check está com problema.

---

### Solução 4: Usar Redis Externo (Temporário)

Como workaround temporário, você pode usar um Redis externo (como Upstash ou Redis Cloud) enquanto resolve o problema do health check.

**Configuração:**
```env
REDIS_HOST=seu-redis-externo.com
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha
```

---

## 🎯 Solução Recomendada: Ajustar Health Check no Coolify

Como o Coolify gera o `docker-compose.yaml` automaticamente, você tem algumas opções:

### Opção A: Usar Dockerfile com Health Check

Adicione um health check no Dockerfile do Redis (mas isso não funciona porque o Redis é uma imagem externa).

### Opção B: Criar Script de Health Check

Crie um script customizado de health check, mas o Coolify não permite isso facilmente.

### Opção C: Usar Redis sem Health Check (Workaround)

Remova temporariamente o `depends_on` do Redis no `docker-compose.yaml` gerado pelo Coolify (mas ele vai regenerar).

---

## 🔧 Solução Definitiva: Ajustar no Coolify

Infelizmente, o Coolify gera o `docker-compose.yaml` automaticamente, então você tem duas opções:

### 1. **Usar Redis Externo** (Mais fácil)

Use um serviço de Redis externo (Upstash, Redis Cloud, etc.) e configure no `.env`:

```env
REDIS_HOST=seu-redis.upstash.io
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha
```

### 2. **Aguardar Fix do Coolify ou Usar Workaround**

Se o Coolify permitir editar o `docker-compose.yaml` manualmente, ajuste o health check:

```yaml
healthcheck:
  test: ["CMD-SHELL", "redis-cli ping || exit 1"]
  interval: 10s
  timeout: 10s
  retries: 5
  start_period: 10s
```

---

## 📝 Checklist de Debug

- [ ] Verificar logs do Redis no Coolify
- [ ] Testar `redis-cli ping` manualmente no container
- [ ] Verificar se o volume do Redis está sendo criado
- [ ] Verificar se há erros de permissão
- [ ] Considerar usar Redis externo temporariamente

---

## 🚀 Próximos Passos

1. **Verifique os logs do Redis** no Coolify
2. **Teste manualmente** se o Redis está funcionando
3. **Se necessário, use Redis externo** temporariamente
4. **Reporte o problema** ao suporte do Coolify se persistir

