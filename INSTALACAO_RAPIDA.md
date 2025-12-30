# 🚀 INSTALAÇÃO RÁPIDA - Fix AJAX 404

## ❌ Problema Resolvido

**Erro anterior:**
```
Erro ao carregar dados do dashboard
404: /painel/wp-admin/admin-ajax.php
```

**Causa:** React extraía `/painel` da URL e adicionava incorretamente ao caminho do AJAX.

---

## ✅ O que foi corrigido

1. **react-wrapper.php** - Gera URL absoluta correta
2. **React api.ts** - Remove manipulação de path, usa URL do WP diretamente
3. **Endpoint de teste** - Adicionado `pc_test` para validação
4. **React rebuild** - Build novo com todas as correções

---

## 📦 Arquivo para Instalar

**Nome:** `painel-campanhas-AJAX-FIXED.zip`
**Tamanho:** 481 KB
**Localização:** `/home/unix/git/midpainel/`

---

## 🔧 INSTALAÇÃO (3 passos)

### 1️⃣ Desative e delete o plugin atual
```
WordPress Admin → Plugins → Painel de Campanhas
→ Desativar → Deletar
```

### 2️⃣ Instale o novo ZIP
```
Plugins → Adicionar novo → Enviar plugin
→ Escolher arquivo: painel-campanhas-AJAX-FIXED.zip
→ Instalar agora → Ativar
```

### 3️⃣ Teste
```
https://paineldecampanhas.taticamarketing.com.br/painel/login
→ Fazer login
→ Dashboard deve carregar sem erros! ✅
```

---

## ✅ Como Verificar

**Console do navegador (F12):**
```javascript
// Deve aparecer:
🔵 [API] Usando AJAX URL do WordPress:
https://paineldecampanhas.taticamarketing.com.br/wp-admin/admin-ajax.php

// NÃO deve aparecer /painel/ no caminho ❌
```

**Network tab:**
```
POST /wp-admin/admin-ajax.php → 200 OK ✅
```

---

## 🆘 Se ainda der erro

1. Ctrl+Shift+R (limpar cache do navegador)
2. Limpar cache do WordPress
3. Fazer logout e login novamente

---

## 📄 Documentação Completa

Ver arquivo: `AJAX_FIX_SUMMARY.md`

---

✅ **Pronto para instalar!**
