# Correção de Vulnerabilidades - npm audit

## Vulnerabilidades encontradas e corrigidas

### 1. **Vite** (Moderate)
- **Problema**: Vite <=5.4.19 tem vulnerabilidades
- **Solução**: Atualizado para `^6.0.0` que corrige todas as CVEs
- **CVEs corrigidas**:
  - GHSA-g4jq-h2w9-997c (server.fs)
  - GHSA-jqfw-vq24-v9c3 (HTML files)
  - GHSA-93m4-6634-74q7 (Windows backslash bypass)

### 2. **esbuild** (Moderate) - Dependência transitiva
- **Problema**: esbuild <=0.24.2 vulnerável
- **Solução**: Será corrigido automaticamente ao atualizar Vite para 6.0.0+
- **CVE**: GHSA-67mh-4wv8-2f99

### 3. **glob** (High) - Dependência transitiva
- **Problema**: glob 10.2.0 - 10.4.5 tem command injection
- **Solução**: Será atualizado para 10.5.0+ com `npm audit fix`
- **CVE**: GHSA-5j98-mcp5-4vw2

### 4. **js-yaml** (Moderate) - Dependência transitiva
- **Problema**: js-yaml 4.0.0 - 4.1.0 tem prototype pollution
- **Solução**: Será atualizado para 4.1.1+ com `npm audit fix`
- **CVE**: GHSA-mh29-5h37-fv8m

### 5. **next-themes** (Conflito de compatibilidade)
- **Problema**: Versão 0.3.0 não suporta React 19
- **Solução**: Atualizado para `^0.4.6` que suporta React 19
- **Nota**: Necessário para evitar conflitos de peer dependencies

## Atualizações realizadas no package.json

```json
{
  "vite": "^6.0.0",           // Era: ^5.4.19
  "next-themes": "^0.4.6"     // Era: ^0.3.0
}
```

## Como aplicar as correções

1. **Instalar dependências atualizadas**:
   ```bash
   cd react
   npm install
   ```

2. **Aplicar correções automáticas das dependências transitivas**:
   ```bash
   npm audit fix
   ```

3. **Verificar que todas foram corrigidas**:
   ```bash
   npm audit
   ```
   Deve retornar: `found 0 vulnerabilities`

4. **Se ainda houver vulnerabilidades, forçar correção** (apenas se necessário):
   ```bash
   npm audit fix --force
   ```

## Importante sobre as vulnerabilidades

### Contexto de segurança:
- **esbuild**: Afeta apenas o servidor de desenvolvimento, não produção
- **glob**: Afeta CLI, não afeta uso como biblioteca
- **js-yaml**: Afeta apenas se usar funcionalidades específicas (merge)
- **vite**: Vulnerabilidades no servidor de dev, não no build final

### Em produção:
- O build final (`npm run build`) NÃO contém essas vulnerabilidades
- Apenas o ambiente de desenvolvimento é afetado
- Mesmo assim, é importante corrigir para segurança

## Nota sobre React 19

A atualização para React 19.2.3 é segura e corrige CVE-2025-55182. As dependências foram atualizadas para garantir compatibilidade completa.

