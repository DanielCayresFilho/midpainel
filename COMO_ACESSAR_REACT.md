# ⚛️ COMO FUNCIONA O REACT NO PLUGIN

## 🎯 Sistema Híbrido Automático

O plugin tem um sistema **INTELIGENTE** que decide automaticamente qual interface usar:

### Se existir build do React → USA REACT ✅
### Se NÃO existir build → USA PHP (fallback)

---

## 📦 ARQUIVOS IMPORTANTES

### **painel-campanhas-COMPLETO.zip** (546 KB)
✅ Inclui build do React
✅ Interface moderna React com Tailwind CSS
✅ SPA (Single Page Application)
✅ Carregamento rápido

### **painel-campanhas.zip** (139 KB)  
❌ SEM build do React
⚠️ Usa apenas templates PHP (fallback)

---

## 🚀 COMO ACESSAR

### 1. Instale o Plugin

```bash
# Upload do arquivo painel-campanhas-COMPLETO.zip
# WordPress Admin → Plugins → Adicionar novo → Enviar plugin
```

### 2. Acesse a URL

```
https://paineldecampanhas.taticamarketing.com.br/painel/login
```

### 3. Faça Login

Use suas credenciais do WordPress

### 4. ✅ Interface React Carregará Automaticamente!

O plugin detecta automaticamente que existe o build React e carrega a interface moderna.

---

## 🔍 COMO VERIFICAR SE ESTÁ USANDO REACT

### Método 1: Inspecionar Elemento (F12)

```html
<!-- Se você ver isso, está usando React: -->
<div id="root">
  <div class="min-h-screen bg-gradient-to-br...">
    <!-- Conteúdo React -->
  </div>
</div>

<!-- Se você ver isso, está usando PHP: -->
<div class="pc-wrapper">
  <!-- Templates PHP tradicionais -->
</div>
```

### Método 2: Console do Navegador

```javascript
// Se estiver usando React, você verá:
console.log(window.pcAjax);
// { ajaxurl: "...", nonce: "...", currentPage: "login", ... }
```

### Método 3: Network Tab

```
React:
- Carrega: index.C0Hbfxkm.js (bundle React)
- Carrega: index.D8JAhPk8.css (Tailwind CSS)
- SPA - Navegação sem reload

PHP:
- Cada clique recarrega a página inteira
- Sem bundle JS do React
```

---

## 📁 ESTRUTURA DO BUILD REACT

```
painel-campanhas-install-2/
├── react/
│   ├── dist/                    ← BUILD DO REACT
│   │   ├── index.html          ← Template HTML
│   │   ├── assets/
│   │   │   ├── index.C0Hbfxkm.js  ← Bundle React (Vite)
│   │   │   └── index.D8JAhPk8.css ← Tailwind CSS
│   │   ├── logo.png
│   │   └── favicon.ico
│   └── (src/ node_modules/ excluídos do zip)
├── react-wrapper.php           ← Carrega React
└── painel-campanhas.php        ← Plugin principal
```

---

## 🎨 PÁGINAS DISPONÍVEIS (REACT)

Todas essas URLs carregam a mesma SPA React:

```
/painel/login                    → Login
/painel/home                     → Dashboard
/painel/campanhas                → Lista de Campanhas
/painel/nova-campanha            → Criar Campanha
/painel/campanhas-recorrentes    → Campanhas Recorrentes
/painel/aprovar-campanhas        → Aprovar Campanhas
/painel/mensagens                → Mensagens/Templates
/painel/relatorios               → Relatórios
/painel/api-manager              → Configurações API
/painel/controle-custo           → Controle de Custos
/painel/blocklist                → Blocklist
/painel/iscas                    → Iscas (testes)
/painel/ranking                  → Ranking
```

---

## 🔧 COMO FUNCIONA INTERNAMENTE

### 1. WordPress Detecta Rota

```php
// painel-campanhas.php
public function render_page($page) {
    $react_dist_path = $this->plugin_path . 'react/dist/index.html';
    $react_wrapper = $this->plugin_path . 'react-wrapper.php';
    
    // TENTA USAR REACT PRIMEIRO
    if (file_exists($react_dist_path) && file_exists($react_wrapper)) {
        include $react_wrapper;  // ← CARREGA REACT
        return;
    }
    
    // FALLBACK: USA PHP
    include $this->plugin_path . $page . '.php';
}
```

### 2. React Wrapper Carrega Bundle

```php
// react-wrapper.php
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href=".../react/dist/assets/index.D8JAhPk8.css">
</head>
<body>
    <div id="root"></div>
    
    <script>
        window.pcAjax = {
            ajaxurl: "<?php echo admin_url('admin-ajax.php'); ?>",
            nonce: "<?php echo wp_create_nonce('pc_nonce'); ?>",
            currentPage: "<?php echo $current_page; ?>"
        };
    </script>
    
    <script type="module" src=".../react/dist/assets/index.C0Hbfxkm.js"></script>
</body>
</html>
```

### 3. React SPA Inicializa

```typescript
// React App
import { BrowserRouter, Routes, Route } from 'react-router-dom'

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/painel/login" element={<Login />} />
        <Route path="/painel/home" element={<Dashboard />} />
        {/* ... */}
      </Routes>
    </BrowserRouter>
  )
}
```

---

## 🆘 TROUBLESHOOTING

### "Página não encontrada" ao acessar /painel/*

**Causa:** Rewrite rules não foram atualizadas  
**Solução:**
1. WordPress Admin → Configurações → Links Permanentes
2. Clique em "Salvar alterações" (sem mudar nada)
3. Isso recarrega as regras de rewrite

### Carrega PHP ao invés de React

**Causa:** Build React não existe  
**Solução:**
1. Verifique se usou `painel-campanhas-COMPLETO.zip`
2. Verifique se pasta `react/dist/` existe no servidor
3. Reative o plugin

### Erros de Console JavaScript

**Causa:** Conflito com outros plugins  
**Solução:**
1. Desative outros plugins temporariamente
2. Verifique se há erros 404 nos assets React

---

## ✅ CHECKLIST DE INSTALAÇÃO

- [ ] Upload do `painel-campanhas-COMPLETO.zip`
- [ ] Plugin ativado
- [ ] Acessou `/painel/login`
- [ ] Interface React carregou (veja logo Tática Branding)
- [ ] Login funcionou
- [ ] Dashboard apareceu

---

## 🎉 PRONTO!

Se você ver a interface moderna com gradiente, animações e Tailwind CSS,
**você está usando REACT**! 

**URL de acesso:**
```
https://paineldecampanhas.taticamarketing.com.br/painel/login
```

🚀 Interface React + Tailwind + Vite + SPA
