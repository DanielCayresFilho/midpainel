# 🔴 Fix: Redis Health Check Failing no Coolify

## Problema

O Redis está falhando no health check porque o comando `redis-cli ping` não funciona quando o Redis tem senha configurada.

**Erro:**
```
Container redis-tk8044owosgwsw84osssgkks-190120070850  Error
dependency failed to start: container redis-tk8044owosgwsw84osssgkks-190120070850 is unhealthy
```

---

## ✅ Solução

O problema é que o Coolify está gerando um health check que não considera a senha do Redis. Você tem **2 opções**:

### Opção 1: Deixar Redis sem senha (Recomendado para desenvolvimento)

No Coolify, **não configure** a variável `REDIS_PASSWORD` (ou deixe vazia).

O Redis vai rodar sem senha e o health check vai funcionar.

---

### Opção 2: Ajustar o docker-compose.yaml manualmente

Se você **precisar** de senha no Redis, você precisa ajustar o health check no `docker-compose.yaml` que o Coolify gerou.

**Health check atual (que está falhando):**
```yaml
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
```

**Health check corrigido:**
```yaml
healthcheck:
  test: >
    sh -c "if [ -n \"${REDIS_PASSWORD}\" ]; then
      redis-cli -a ${REDIS_PASSWORD} ping;
    else
      redis-cli ping;
    fi"
```

**⚠️ Problema:** O Coolify **sobrescreve** o `docker-compose.yaml` a cada deploy, então você precisaria ajustar manualmente toda vez.

---

## 🎯 Recomendação

**Para o Coolify, use Redis SEM senha:**

1. No Coolify, **remova** a variável `REDIS_PASSWORD` (se você configurou)
2. Ou deixe ela **vazia**
3. Faça o deploy novamente

O Redis vai funcionar normalmente sem senha dentro da rede Docker do Coolify, que já é isolada.

---

## 🔒 Segurança

**Por que é seguro deixar Redis sem senha no Coolify?**

1. **Rede isolada:** O Coolify cria uma rede Docker isolada (`tk8044owosgwsw84osssgkks`)
2. **Acesso interno:** Apenas os containers na mesma rede podem acessar o Redis
3. **Não exposto:** O Redis não está exposto publicamente (só via porta interna)
4. **Firewall:** O servidor já tem firewall configurado

Se você **realmente precisar** de senha, você teria que:
- Ajustar o `docker-compose.yaml` manualmente toda vez
- Ou usar um Redis externo (não recomendado)

---

## 📝 Passos para Resolver

1. **No Coolify, vá em Environment Variables**
2. **Remova ou deixe vazia** a variável `REDIS_PASSWORD`
3. **Faça o deploy novamente**

O Redis vai iniciar sem senha e o health check vai passar.

---

## 🧪 Verificar se Funcionou

Após o deploy, verifique os logs do Redis no Coolify. Você deve ver:

```
Container redis-...  Healthy
```

E não mais:

```
Container redis-...  Error
```

---

## 📚 Referência

O `docker-compose.yaml` local já está corrigido com o health check que funciona com ou sem senha. Mas como o Coolify gera o seu próprio, a solução mais simples é **não usar senha no Redis**.

