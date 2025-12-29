# Guia de Instalação - Painel de Campanhas

## Estrutura Correta de Pastas

O plugin deve estar estruturado assim:

```
wp-content/
  plugins/
    painel-campanhas/          ← Nome da pasta deve ser igual ao nome do plugin
      painel-campanhas.php     ← Arquivo principal (deve ter o cabeçalho correto)
      templates/
      assets/
      readme.txt
      README.md
```

## Passos para Instalação

### Método 1: Upload Manual

1. Certifique-se de que a pasta se chama exatamente `painel-campanhas`
2. Copie toda a pasta para `/wp-content/plugins/`
3. A pasta final deve ser: `/wp-content/plugins/painel-campanhas/`
4. Acesse o WordPress Admin > Plugins
5. Procure por "Painel de Campanhas"
6. Clique em "Ativar"

### Método 2: Via ZIP

1. Crie um arquivo ZIP da pasta `painel-campanhas` (não da pasta pai)
2. Acesse WordPress Admin > Plugins > Adicionar Novo
3. Clique em "Enviar plugin"
4. Selecione o arquivo ZIP
5. Clique em "Instalar agora"
6. Após instalar, clique em "Ativar plugin"

## Verificação do Cabeçalho

O arquivo `painel-campanhas.php` deve começar com:

```php
<?php
/**
 * Plugin Name: Painel de Campanhas
 * Plugin URI: https://github.com/seu-usuario/painel-campanhas
 * Description: Sistema completo de gerenciamento de campanhas...
 * Version: 1.0.0
 * Author: Daniel Cayres
 * ...
 */
```

## Problemas Comuns

### Erro: "Falta de cabeçalho correto"

**Causas possíveis:**
1. O arquivo principal não está na raiz da pasta do plugin
2. O cabeçalho está mal formatado
3. Há caracteres especiais ou BOM no início do arquivo
4. A pasta do plugin tem nome incorreto

**Soluções:**
1. Verifique se `painel-campanhas.php` está diretamente em `/wp-content/plugins/painel-campanhas/`
2. Verifique se o cabeçalho começa com `<?php` na primeira linha
3. Certifique-se de que não há espaços ou caracteres antes de `<?php`
4. Renomeie a pasta para `painel-campanhas` (sem espaços ou caracteres especiais)

### Erro: Plugin não aparece na lista

1. Verifique as permissões da pasta (deve ser 755)
2. Verifique se o arquivo principal tem permissão de leitura (644)
3. Limpe o cache do WordPress
4. Verifique os logs de erro do PHP

## Pós-Instalação

Após ativar o plugin:

1. Acesse `/painel/login` para fazer login
2. Configure as carteiras em Configurações
3. Cadastre custos e orçamentos em Controle de Custo
4. Certifique-se de que o Campaign Manager está ativo (obrigatório)

## Dependências

- **Campaign Manager**: Obrigatório para campanhas normais
- **WordPress**: 5.0 ou superior
- **PHP**: 7.4 ou superior

