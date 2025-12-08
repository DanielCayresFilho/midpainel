# Painel de Campanhas

Plugin WordPress completo para gerenciamento de campanhas de mensageria com interface moderna e integração com API NestJS.

## Características

- ✅ Interface moderna e responsiva com Tailwind CSS
- ✅ Sistema de rotas customizadas (`/painel/home`, `/painel/campanhas`, etc)
- ✅ Autenticação integrada com WordPress
- ✅ Controle de acesso (Admin vs Assinante)
- ✅ Design dark mode
- ✅ Animações e transições suaves
- ✅ Integração com API Manager e outros plugins

## Rotas Disponíveis

- `/painel/login` - Página de login
- `/painel/home` - Dashboard principal
- `/painel/campanhas` - Listagem de campanhas
- `/painel/nova-campanha` - Criar nova campanha
- `/painel/aprovar-campanhas` - Aprovar campanhas (apenas admin)
- `/painel/mensagens` - Templates de mensagem
- `/painel/relatorios` - Relatórios e estatísticas
- `/painel/api-manager` - Gerenciamento de API (apenas admin)
- `/painel/configuracoes` - Configurações do sistema (apenas admin)

## Instalação

1. Copie a pasta `painel-campanhas` para `/wp-content/plugins/`
2. Ative o plugin no WordPress
3. Acesse `/painel/login` para fazer login
4. Após login, você será redirecionado para `/painel/home`

## Permissões

- **Administradores**: Acesso completo a todas as páginas
- **Assinantes**: Podem criar campanhas, ver relatórios e gerenciar mensagens
- **Aprovação de Campanhas**: Apenas administradores

## Integração com Outros Plugins

O plugin integra-se com:
- **API Consumer Manager**: Para gerenciar credenciais de API
- **Get Agendamentos**: Para listar e gerenciar campanhas
- **Message Template Manager**: Para templates de mensagem (em desenvolvimento)

## Estrutura de Arquivos

```
painel-campanhas/
├── painel-campanhas.php      # Arquivo principal do plugin
├── templates/                 # Templates das páginas
│   ├── base.php              # Template base
│   ├── login.php             # Página de login
│   ├── home.php              # Dashboard
│   ├── campanhas.php         # Listagem de campanhas
│   ├── nova-campanha.php     # Criar campanha
│   ├── aprovar-campanhas.php # Aprovar campanhas
│   ├── mensagens.php         # Templates de mensagem
│   ├── relatorios.php        # Relatórios
│   ├── api-manager.php       # API Manager
│   └── configuracoes.php     # Configurações
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos customizados
│   └── js/
│       └── main.js           # JavaScript principal
└── README.md                 # Este arquivo
```

## Desenvolvimento

### Adicionar Nova Página

1. Crie o template em `templates/nova-pagina.php`
2. Adicione a rota em `add_rewrite_rules()`:
   ```php
   add_rewrite_rule('^painel/nova-pagina/?$', 'index.php?pc_page=nova-pagina', 'top');
   ```
3. Adicione o link na sidebar em `templates/base.php`

### Customizar Estilos

Edite `assets/css/style.css` ou use classes Tailwind CSS diretamente nos templates.

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- Plugins integrados (API Consumer Manager, Get Agendamentos)

## Licença

Este plugin é desenvolvido para uso interno.

