# 🔍 COMO DEBUGAR O PROBLEMA DO BOTÃO "PRÓXIMO"

**Arquivo:** `painel-campanhas-DEBUG.zip` (462 KB)
**Status:** 🔧 Versão com logs de debug

---

## 📋 O QUE PRECISO QUE VOCÊ FAÇA

Instalei **logs detalhados** no código para descobrir por que o botão "Próximo" não está habilitando.

---

## 🚀 PASSO A PASSO

### 1. Instale a versão DEBUG

```bash
1. WordPress Admin > Plugins > Plugins Instalados
2. Desative "Painel de Campanhas"
3. Delete o plugin
4. Plugins > Adicionar Novo > Enviar Plugin
5. Escolha: painel-campanhas-DEBUG.zip
6. Instale e Ative
```

### 2. Abra o Console do Navegador

```bash
1. Abra o site do WordPress
2. Pressione F12 (ou Ctrl+Shift+I)
3. Vá na aba "Console"
4. Deixe o console aberto
```

### 3. Teste a Criação de Campanha

```bash
1. Vá em "Nova Campanha"
2. Preencha:
   - Nome: "Teste Debug"
   - Carteira: Selecione qualquer uma
   - Base: Selecione a base VW_BASE_SMS_ATIVO_BV_VEICULOS_ADM
```

### 4. Veja os Logs que Aparecem no Console

Você vai ver logs como estes:

```
🔍 [useEffect baseUpdateData] Dados recebidos: { ... }
✅ [useEffect baseUpdateData] Setando baseUpdateStatus: { ... }
🔍 [canGoNext] Verificando condições: { ... }
```

### 5. **ME MANDE UM PRINT DESSES LOGS**

**IMPORTANTE:** Preciso ver exatamente o que está nos logs!

Especialmente esta linha:
```
🔍 [canGoNext] Verificando condições: {
  step: 1,
  formDataName: "...",
  formDataCarteira: "...",
  formDataBase: "...",
  hasRequiredFields: true/false,
  baseUpdateStatus: { ... },
  isBaseUpdated: true/false,
  canProceed: true/false
}
```

---

## 📸 O QUE EU PRECISO VER

### Print 1: Console do Navegador
- Tire print de TODOS os logs que começam com 🔍 ou ✅
- Expanda os objetos clicando nas setinhas

### Print 2: Tela da Nova Campanha
- Mostre o formulário preenchido
- Mostre se o botão "Próximo" está habilitado ou desabilitado

### Print 3 (OPCIONAL): Logs do WordPress
```bash
1. Vá no servidor
2. Abra o arquivo: /wp-content/debug.log
3. Procure por linhas com 🔍 [check_base_update]
4. Me mande essas linhas
```

---

## 🎯 O QUE ESTOU PROCURANDO

Vou verificar:

1. **formData está preenchido?**
   - `formDataName` tem valor?
   - `formDataCarteira` tem valor?
   - `formDataBase` tem valor?

2. **baseUpdateStatus está correto?**
   - `baseUpdateData` chegou do servidor?
   - `baseUpdateStatus.isUpdated` é `true` ou `false`?

3. **Por que canProceed é false?**
   - É por falta de dados?
   - É porque a base está "desatualizada"?

---

## 🔧 POSSÍVEIS PROBLEMAS E SOLUÇÕES

### Problema A: `baseUpdateStatus.isUpdated = false`
**Causa:** A base não foi atualizada hoje
**Solução:** Vou **remover essa validação** porque não faz sentido bloquear a criação de campanha por isso

### Problema B: `formData.name` está vazio
**Causa:** O campo nome não está sendo atualizado corretamente
**Solução:** Vou corrigir o binding do formulário

### Problema C: `baseUpdateData` é `null` ou `undefined`
**Causa:** A requisição AJAX está falhando
**Solução:** Vou verificar o nonce e o handler PHP

---

## ⚡ RESPOSTA RÁPIDA

**Se quiser resolver agora mesmo, me mande:**

1. Print do console com os logs 🔍
2. Me diga se a base tem a coluna `ult_atualizacao` no banco de dados

---

## 💡 DICA

Se você quiser **remover temporariamente a validação da base** para testar, posso gerar uma versão que:
- Remove a verificação de `is_updated`
- Permite criar campanha mesmo com base "desatualizada"

**Quer que eu faça isso?**

Ou prefere esperar os logs para entender o problema real?

---

**Instale, teste e me mande os prints!** 📸
