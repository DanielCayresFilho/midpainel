# 📦 INSTALAÇÃO DO PLUGIN - Painel de Campanhas

## ✅ Arquivo Gerado

**Nome:** `painel-campanhas.zip`  
**Tamanho:** 139 KB  
**Localização:** `/home/unix/git/midpainel/painel-campanhas.zip`

---

## 🚀 COMO INSTALAR NO WORDPRESS

### Método 1: Via Painel do WordPress (Recomendado)

1. **Baixe o arquivo**
   ```bash
   # Se estiver em servidor remoto, faça download via SCP/FTP
   scp user@servidor:/home/unix/git/midpainel/painel-campanhas.zip .
   ```

2. **Acesse o WordPress Admin**
   - Vá para: `https://seusite.com/wp-admin`

3. **Instale o Plugin**
   - Menu: **Plugins** → **Adicionar novo**
   - Clique em **Enviar plugin**
   - Escolha o arquivo `painel-campanhas.zip`
   - Clique em **Instalar agora**

4. **Ative o Plugin**
   - Após instalação, clique em **Ativar**
   - ✅ As tabelas serão criadas automaticamente!

---

### Método 2: Via FTP/SSH

1. **Descompacte o arquivo**
   ```bash
   unzip painel-campanhas.zip -d /var/www/html/wp-content/plugins/
   ```

2. **Ative no WordPress Admin**
   - Vá para **Plugins** → **Plugins instalados**
   - Encontre "Painel de Campanhas"
   - Clique em **Ativar**

---

## ⚙️ CONFIGURAÇÃO INICIAL

### 1. Acesse o Menu

Após ativar, você verá um novo menu **"Painel"** na lateral esquerda.

### 2. Configure o API Manager

📍 **Painel** → **API Manager**

#### Microserviço
```
URL: https://seu-microservico.com
API Key: sua-chave-secreta
```

#### Credenciais dos Providers

**RCS Ótima**
- Token: (fornecido pela Ótima Digital)

**WhatsApp Ótima**
- Token: (fornecido pela Ótima Digital)
- Broker Code: (seu código)
- Customer Code: (seu código)

**RCS CDA**
- Chave API: (fornecida pelo CromosApp)

### 3. Cadastre Carteiras

📍 **Painel** → **Carteiras**

Crie suas carteiras de clientes e vincule às bases de dados.

### 4. Configure Custos

📍 **Painel** → **Controle de Custo**

Defina o custo por disparo de cada provider.

---

## 🎯 CRIANDO SUA PRIMEIRA CAMPANHA

1. **Nova Campanha**
   - Vá em **Painel** → **Nova Campanha**

2. **Selecione a Base**
   - Escolha a tabela do banco de dados
   - Aplique filtros se necessário

3. **Configure Providers**
   - Selecione os fornecedores (RCS Ótima, WhatsApp Ótima, etc.)
   - Defina a distribuição (Split ou All)

4. **Mensagem**
   - Crie ou selecione um template
   - Use placeholders: `[[TAG1]]`, `[[TAG2]]`, etc.

5. **Criar**
   - Clique em **Criar Campanha**
   - Status: **Pendente de Aprovação**

6. **Aprovar**
   - Vá em **Painel** → **Aprovar Campanhas**
   - Revise e clique em **Aprovar**
   - ✅ Campanha será enviada ao microserviço!

---

## 🗄️ TABELAS CRIADAS

Ao ativar, o plugin cria automaticamente:

✅ `wp_envios_pendentes` - Tabela principal  
✅ `wp_pc_custos_providers` - Custos  
✅ `wp_pc_orcamentos_bases` - Orçamentos  
✅ `wp_pc_carteiras` - Carteiras  
✅ `wp_pc_carteiras_bases` - Vínculos  
✅ `wp_cm_baits` - Iscas (números de teste)  
✅ `wp_cm_idgis_mappings` - Mapeamentos  
✅ `wp_pc_blocklist` - Bloqueios  
✅ `wp_cm_recurring_campaigns` - Campanhas recorrentes

**Não precisa criar nada manualmente!** Tudo é criado automaticamente.

---

## 🔍 VERIFICAR INSTALAÇÃO

Execute no MySQL para verificar:

```sql
-- Verificar se as tabelas foram criadas
SHOW TABLES LIKE 'wp_%';

-- Verificar estrutura da tabela principal
DESCRIBE wp_envios_pendentes;

-- Contar registros (deve estar vazio inicialmente)
SELECT COUNT(*) FROM wp_envios_pendentes;
```

---

## 🆘 TROUBLESHOOTING

### Erro: "Tabelas não foram criadas"

**Solução:**
1. Desative o plugin
2. Delete as tabelas (se existirem parcialmente)
3. Ative novamente

### Erro: "Permissões insuficientes"

**Solução:**
- Verifique permissões do usuário do banco de dados
- Precisa ter permissão `CREATE TABLE`

### Menu "Painel" não aparece

**Solução:**
- Verifique se o usuário tem permissão `manage_options`
- Faça logout e login novamente

---

## 📞 SUPORTE

**Autor:** Daniel Cayres  
**Versão:** 1.0.0  
**Licença:** GPLv2 or later

---

## 🎉 PRONTO!

Seu plugin está instalado e pronto para uso!

✅ **100% Independente** - Não precisa de outros plugins  
✅ **Tabelas criadas automaticamente**  
✅ **Suporte a 7 providers**  
✅ **Interface moderna e completa**

**Boas campanhas!** 🚀
