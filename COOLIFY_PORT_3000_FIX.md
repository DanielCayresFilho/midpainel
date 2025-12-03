# 🔴 Fix: Porta 3000 Já Está em Uso

## ✅ Progresso

Ótimo! O Redis agora está funcionando:
```
Container redis-...  Healthy
Container postgres-...  Healthy
```

Mas agora temos um novo problema: a porta 3000 já está em uso.

---

## 🔍 Problema

```
Error: failed to bind port 0.0.0.0:3000/tcp: bind: address already in use
```

Isso significa que há outro processo ou container usando a porta 3000.

---

## ✅ Soluções

### Solução 1: Parar Container Antigo (Recomendado)

Provavelmente há um container antigo ainda rodando. No Coolify:

1. **Vá em Containers** ou **Deployments**
2. **Pare e remova containers antigos** que possam estar usando a porta 3000
3. **Faça o deploy novamente**

---

### Solução 2: Mudar a Porta no Coolify

Se você precisa manter outro serviço na porta 3000:

1. **No Coolify, vá em Settings** do serviço
2. **Procure por "Port" ou "APP_PORT"**
3. **Mude para outra porta** (ex: `3001`, `3002`, `8080`)
4. **Atualize a variável de ambiente:**

```env
APP_PORT=3001
PORT=3001
```

5. **Faça o deploy novamente**

**⚠️ IMPORTANTE:** Se mudar a porta, você também precisa atualizar:
- A URL do NestJS no WordPress (API Manager)
- Qualquer proxy reverso ou configuração de domínio

---

### Solução 3: Verificar Processos na Porta 3000

Se você tem acesso SSH ao servidor:

```bash
# Ver o que está usando a porta 3000
sudo lsof -i :3000
# ou
sudo netstat -tulpn | grep 3000

# Parar o processo (se necessário)
sudo kill -9 <PID>
```

---

## 🎯 Solução Recomendada

**No Coolify:**

1. **Vá em Containers/Deployments**
2. **Pare todos os containers antigos** relacionados ao middleware
3. **Remova containers parados** (se possível)
4. **Faça o deploy novamente**

O Coolify deve limpar containers antigos automaticamente, mas às vezes ficam "órfãos".

---

## 📝 Verificar se Funcionou

Após parar os containers antigos e fazer o deploy:

1. **Verifique os logs** do app no Coolify
2. **Você deve ver:**
   ```
   🚀 Application is running on: http://localhost:3000
   ```
3. **Teste o endpoint:**
   ```bash
   curl https://middleware.painel.taticamarketing.com.br/
   ```

---

## 🐛 Se Ainda Der Erro

Se mesmo após parar containers antigos ainda der erro:

1. **Verifique se há outro serviço no Coolify** usando a porta 3000
2. **Mude a porta** para 3001 ou outra disponível
3. **Atualize a URL no WordPress** após mudar a porta

---

## ✅ Próximos Passos

1. ✅ Redis funcionando (Healthy)
2. ✅ PostgreSQL funcionando (Healthy)
3. ⏳ Resolver conflito de porta 3000
4. ⏳ App deve iniciar corretamente

