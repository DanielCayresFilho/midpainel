<?php
/**
 * Plugin Name: get_agendamentos
 * Description: Rotas get de agendamentos, disparados ou não
 * Version: 1.4.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// PROTEÇÃO POR SENHA SEGURA
// =========================================================================

class GA_Password_Protection {
    private $password_hash;
    private $cookie_key = 'ga_dashboard_auth';
    
    public function __construct() {
        // Busca a senha das configurações ou usa padrão temporário
        $password = get_option('ga_dashboard_password', 'admin123');
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        add_action('wp_ajax_ga_verify_password', [$this, 'verify_password']);
        add_action('wp_ajax_ga_logout_dashboard', [$this, 'logout_dashboard']);
    }
    
    public function is_authenticated() {
        // Verifica se tem o cookie e se é válido
        if (isset($_COOKIE[$this->cookie_key])) {
            $auth_hash = $_COOKIE[$this->cookie_key];
            // Verifica se o hash é válido (simples verificação)
            return !empty($auth_hash) && strlen($auth_hash) === 64;
        }
        return false;
    }
    
    public function show_password_form() {
        ?>
        <div class="ga-password-protection-wrapper">
            <div class="ga-login-box">
                <div class="ga-login-icon">🔒</div>
                <h2>Área Protegida</h2>
                <p>Digite a senha para acessar o Status dos Envios</p>
                
                <form id="ga-password-form">
                    <div class="ga-input-group">
                        <input 
                            type="password" 
                            id="ga-password-input" 
                            placeholder="Digite a senha"
                            autocomplete="off"
                            required
                            autofocus
                        >
                    </div>
                    <button type="submit" id="ga-submit-btn" class="button button-primary button-large">
                        Entrar
                    </button>
                    <div class="ga-error-message" id="ga-error-message"></div>
                </form>
            </div>
        </div>

        <style>
            .ga-password-protection-wrapper {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 500px;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                border-radius: 8px;
                margin: 20px 0;
            }
            .ga-login-box {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 100%;
                animation: ga-slideIn 0.4s ease-out;
            }
            @keyframes ga-slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .ga-login-icon {
                font-size: 60px;
                text-align: center;
                margin-bottom: 20px;
            }
            .ga-login-box h2 {
                margin: 0 0 10px 0;
                text-align: center;
                color: #1e293b;
                font-size: 24px;
                font-weight: 600;
            }
            .ga-login-box p {
                text-align: center;
                color: #64748b;
                margin-bottom: 30px;
                font-size: 14px;
            }
            .ga-input-group {
                margin-bottom: 20px;
            }
            #ga-password-input {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 15px;
                transition: all 0.3s ease;
            }
            #ga-password-input:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            #ga-submit-btn {
                width: 100%;
                padding: 12px;
                font-size: 15px;
                font-weight: 600;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            #ga-submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            }
            #ga-submit-btn:active {
                transform: translateY(0);
            }
            #ga-submit-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none !important;
            }
            .ga-error-message {
                color: #dc2626;
                text-align: center;
                margin-top: 15px;
                font-size: 13px;
                font-weight: 500;
                display: none;
                padding: 10px;
                background: #fee2e2;
                border-radius: 6px;
                border: 1px solid #fca5a5;
            }
            .ga-error-message.show {
                display: block;
                animation: ga-shake 0.4s ease;
            }
            @keyframes ga-shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-8px); }
                75% { transform: translateX(8px); }
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('ga-password-form');
            const input = document.getElementById('ga-password-input');
            const button = document.getElementById('ga-submit-btn');
            const errorDiv = document.getElementById('ga-error-message');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = input.value.trim();
                
                if (!password) {
                    showError('Por favor, digite a senha');
                    return;
                }

                button.disabled = true;
                button.textContent = 'Verificando...';
                errorDiv.classList.remove('show');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ga_verify_password',
                        password: password,
                        nonce: '<?php echo wp_create_nonce('ga_password_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.textContent = '✓ Acesso liberado!';
                        button.style.background = '#10b981';
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        showError(data.data || 'Senha incorreta');
                        button.disabled = false;
                        button.textContent = 'Entrar';
                        input.value = '';
                        input.focus();
                    }
                })
                .catch(error => {
                    showError('Erro ao verificar senha');
                    button.disabled = false;
                    button.textContent = 'Entrar';
                });
            });

            function showError(message) {
                errorDiv.textContent = message;
                errorDiv.classList.add('show');
            }
        });
        </script>
        <?php
    }
    
    public function verify_password() {
        check_ajax_referer('ga_password_nonce', 'nonce');
        
        $password = sanitize_text_field($_POST['password'] ?? '');
        
        if (password_verify($password, $this->password_hash)) {
            // Cria um hash único para o cookie
            $auth_hash = hash('sha256', $password . time() . wp_salt());
            
            // Define o cookie por 8 horas
            setcookie(
                $this->cookie_key,
                $auth_hash,
                time() + (8 * 3600), // 8 horas
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // httponly
            );
            
            wp_send_json_success('Autenticado com sucesso');
        } else {
            wp_send_json_error('Senha incorreta');
        }
    }
    
    public function logout_dashboard() {
        // Remove o cookie
        setcookie(
            $this->cookie_key,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
        wp_send_json_success('Logout realizado');
    }
}

$ga_password_protection = new GA_Password_Protection();

// =========================================================================
// API REST SEGURA
// =========================================================================

class WP_Agendamentos_REST_Min {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';
        add_action('rest_api_init', [$this, 'routes']);
    }

    public static function create_table_on_activation() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'envios_pendentes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(11) NOT NULL AUTO_INCREMENT,
            telefone varchar(20) NOT NULL,
            nome varchar(255) DEFAULT '' NOT NULL,
            idgis_ambiente int(11) NOT NULL,
            idcob_contrato int(11) NOT NULL,
            cpf_cnpj varchar(18) DEFAULT '' NOT NULL,
            data_cadastro datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            mensagem text NOT NULL,
            fornecedor varchar(50) NOT NULL,
            agendamento_id varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'pendente' NOT NULL,
            resposta_api text,
            current_user_id bigint(20) NULL DEFAULT NULL,
            data_disparo datetime NULL,
            valido int DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function check_api_key(\WP_REST_Request $request) {
        $master_key = get_option('acm_master_api_key');
        if (empty($master_key)) {
            return new WP_Error('no_master_key', 'Master API Key não configurada.', ['status' => 503]);
        }

        $provided_key = $request->get_header('X-API-KEY');
        if ($provided_key !== $master_key) {
            return new WP_Error('invalid_key', 'API Key inválida.', ['status' => 401]);
        }
        
        return true;
    }

    public function routes() {
        // Lista agendamentos com pendentes (id + fornecedor + total)
        register_rest_route('agendamentos/v1', '/pendentes', [
            'methods'  => 'GET',
            'callback' => [$this, 'list_pendentes'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        
        // Retorna os registros (linhas) pendentes de um agendamento específico
        register_rest_route('agendamentos/v1', '/pendentes/(?P<agendamento_id>[^/]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_pendentes_by_id'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'agendamento_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'fornecedor' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ]
        ]);

        register_rest_route('agendamentos/v1', '/disparados', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_disparados'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        register_rest_route('agendamentos/v1', '/disparados/(?P<idgis_ambiente>[^/]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_disparos_by_idgis'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'idgis_ambiente' => [
                    'require' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'fornecedor' => [
                    'require' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ],
        ]);
    }

    public function list_pendentes(\WP_REST_Request $req) {
        global $wpdb;
        $sql = "
            SELECT agendamento_id, fornecedor, COUNT(*) AS pendentes
            FROM {$this->table}
            WHERE status = 'pendente'
            GROUP BY agendamento_id, fornecedor
            ORDER BY agendamento_id DESC
        ";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public function get_pendentes_by_id(\WP_REST_Request $req) {
        global $wpdb;
        $agendamento_id = $req->get_param('agendamento_id');
        $validados = $req->get_param('validos');

        if ($validados) {
            $sql = $wpdb->prepare("
                SELECT concat(55, telefone) as telefone, nome, idgis_ambiente, idcob_contrato, cpf_cnpj, data_cadastro, mensagem
                FROM {$this->table}
                WHERE agendamento_id = %s AND VALIDO = %s AND status = 'pendente' 
                ORDER BY id ASC
            ", $agendamento_id, $validados);
        } else {
            $sql = $wpdb->prepare("
                SELECT concat(55, telefone) as telefone, nome, idgis_ambiente, idcob_contrato, cpf_cnpj, data_cadastro, mensagem
                FROM {$this->table}
                WHERE agendamento_id = %s AND status = 'pendente' 
                ORDER BY id ASC
            ", $agendamento_id);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);

        $payload = array_map(function($r) {
            return [
                'telefone'       => (string) $r['telefone'],
                'nome'           => (string) $r['nome'],
                'idgis_ambiente' => (string) $r['idgis_ambiente'],
                'idcob_contrato' => (string) $r['idcob_contrato'],
                'CPF_CNPJ'       => (string) $r['cpf_cnpj'],
                'data'           => (string) ($r['data_cadastro'] ?: date('Y-m-d H:i:s')),
                'mensagem'       => (string) $r['mensagem'],
            ];
        }, $rows ?: []);
        return rest_ensure_response($payload);
    }

    public function get_disparados(\WP_REST_Request $req) {
        global $wpdb;
        $query = "
            SELECT * FROM {$this->table}
            WHERE status = 'enviado'
            ORDER BY data_disparo DESC
        ";
        $rows = $wpdb->get_results($query, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public function get_disparos_by_idgis(\WP_REST_Request $req) {
        global $wpdb;
        $idgis_ambiente = $req->get_param('idgis_ambiente');
        $fornecedor     = $req->get_param('fornecedor');

        if ($fornecedor) {
            $sql = $wpdb->prepare("
                SELECT * FROM {$this->table}
                WHERE idgis_ambiente = %s AND status = 'enviado' AND fornecedor = %s
                ORDER BY data_disparo DESC
            ", $idgis_ambiente, $fornecedor);
        } else {
            $sql = $wpdb->prepare("
                SELECT * FROM {$this->table}
                WHERE idgis_ambiente = %s AND status = 'enviado'
                ORDER BY data_disparo DESC
            ", $idgis_ambiente);
        }
       $rows = $wpdb->get_results($sql, ARRAY_A);
       return rest_ensure_response($rows ?: []);
    }
}
register_activation_hook(__FILE__, ['WP_Agendamentos_REST_Min', 'create_table_on_activation']);
new WP_Agendamentos_REST_Min();

// =========================================================================
// DASHBOARD ADMINISTRATIVO
// =========================================================================

add_action('admin_menu', 'ga_register_dashboard_page');
function ga_register_dashboard_page() {
    add_menu_page(
        'Status dos Envios',
        'Status dos Envios',
        'manage_options',
        'job-status-dashboard',
        'ga_render_dashboard_page',
        'dashicons-backup',
        27
    );
}

function ga_render_dashboard_page() {
    global $ga_password_protection;
    
    // Verifica se está autenticado
    if (!$ga_password_protection->is_authenticated()) {
        // Mostra apenas o formulário de senha
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-backup"></span> Status dos Envios</h1>
            <?php $ga_password_protection->show_password_form(); ?>
        </div>
        <?php
        return;
    }
    
    // Se autenticado, mostra o dashboard completo
    ?>
    <div class="wrap ga-dashboard-wrap">
        <div class="ga-dashboard-header">
            <h1><span class="dashicons dashicons-backup"></span> Status dos Envios</h1>
            <button id="ga-logout-btn" class="button">
                <span class="dashicons dashicons-exit"></span> Sair
            </button>
        </div>
        
        <div id="dashboard-controls">
            <button id="refresh-jobs-button" class="button button-primary">
                <span class="dashicons dashicons-update"></span> Recarregar
            </button>
        </div>

        <div id="campaign-message" class="notice" style="display: none; margin-top: 10px;"></div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="manage-column">ID da Campanha</th>
                    <th class="manage-column">ID GIS Ambiente</th>
                    <th class="manage-column">Provedor</th>
                    <th class="manage-column">Status</th>
                    <th class="manage-column">Total de Clientes</th>
                    <th class="manage-column">Data de Criação</th>
                    <th class="manage-column">Agendado por</th>
                    <th class="manage-column">Ações</th>
                </tr>
            </thead>
            <tbody id="jobs-table-body">
                <tr>
                    <td colspan="8" class="loading-cell">Carregando dados...</td> 
                </tr>
            </tbody>
        </table>
    </div>
    
    <style>
        .ga-dashboard-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;margin-top:20px}
        .ga-dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .ga-dashboard-header h1{font-size:24px;font-weight:600;display:flex;align-items:center;gap:10px;margin:0}
        #ga-logout-btn{display:inline-flex;align-items:center;gap:5px}
        #dashboard-controls{margin:20px 0}
        #refresh-jobs-button{display:inline-flex;align-items:center;gap:5px;font-size:14px;padding:8px 16px;border-radius:6px;transition:background-color .2s ease,box-shadow .2s ease}
        #refresh-jobs-button:hover{background-color:#0069a0}
        #refresh-jobs-button .dashicons-update{transition:transform .5s ease}
        #refresh-jobs-button:active .dashicons-update{transform:rotate(180deg)}
        .wp-list-table{border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,.05);overflow:hidden;border:none}
        .wp-list-table thead tr{background-color:#f6f7f7;border-bottom:1px solid #e5e5e5}
        .wp-list-table th{font-weight:600;color:#444;padding:16px 12px}
        .wp-list-table tbody tr{transition:background-color .15s ease-in-out}
        .wp-list-table tbody tr:hover{background-color:#f0f6fc}
        .wp-list-table td{padding:14px 12px;vertical-align:middle}
        .wp-list-table .loading-cell,.wp-list-table .empty-cell{text-align:center;color:#888;font-style:italic;padding:40px}
        .wp-list-table .actions-cell{text-align:center}
        .status-badge{padding:4px 10px;border-radius:12px;font-weight:500;font-size:12px;color:#fff;text-transform:capitalize;white-space:nowrap}
        .status-badge.status-pendente_aprovacao{background-color:#ffb900;color:#333}
        .status-badge.status-pendente{background-color:#0073aa}
        .status-badge.status-negado{background-color:#d63638}
        .status-badge.status-enviado{background-color:#46b450}
        .campaign-actions .button{margin:0 4px}
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutBtn = document.getElementById('ga-logout-btn');
        
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Deseja realmente sair?')) {
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'ga_logout_dashboard'
                        })
                    })
                    .then(() => {
                        window.location.reload();
                    });
                }
            });
        }
    });
    </script>
    <?php
}

add_action('admin_enqueue_scripts', 'ga_enqueue_dashboard_scripts');
function ga_enqueue_dashboard_scripts($hook) {
    if ('toplevel_page_job-status-dashboard' !== $hook) return;
    wp_enqueue_script('job-status-dashboard-js', plugin_dir_url(__FILE__) . 'js/dashboard.js', [], '1.4.0', true);
    wp_localize_script('job-status-dashboard-js', 'ga_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ga_nonce')
    ]);
}

class WP_Agendamentos_API_For_Dashboard {
    public function __construct() {
        add_action('wp_ajax_ga_get_campaigns', [$this, 'get_campaign_summaries']);
        add_action('wp_ajax_ga_handle_action', [$this, 'handle_campaign_action']);
    }

    public function get_campaign_summaries() {
        check_ajax_referer('ga_nonce', 'nonce');
        global $wpdb;
        $target_table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->users;

        $query = "
            SELECT
                t1.agendamento_id,
                t1.idgis_ambiente,
                t1.fornecedor AS provider,
                t1.status,
                MIN(t1.data_cadastro) AS created_at,
                COUNT(t1.id) AS total_clients,
                COALESCE(u.display_name, 'Usuário Desconhecido') AS scheduled_by
            FROM `{$target_table}` AS t1
            LEFT JOIN `{$users_table}` AS u ON t1.current_user_id = u.ID
            GROUP BY t1.agendamento_id, t1.idgis_ambiente, t1.fornecedor, t1.status, scheduled_by
            ORDER BY MIN(t1.data_cadastro) DESC
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        wp_send_json_success($results ?: []);
    }

    public function handle_campaign_action() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }

        check_ajax_referer('ga_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . "envios_pendentes";
        $agendamento_id = sanitize_text_field($_POST['agendamento_id'] ?? '');
        $action = sanitize_text_field($_POST['campaign_action'] ?? '');

        if (empty($agendamento_id) || !in_array($action, ['approve', 'deny'])) {
            wp_send_json_error('Parâmetros inválidos.');
        }

        $new_status = ($action === 'approve') ? 'pendente' : 'negado';

        $updated = $wpdb->update($table, ['status' => $new_status], ['agendamento_id' => $agendamento_id], ['%s'], ['%s']);

        if ($updated === false) {
            wp_send_json_error('Erro ao atualizar status no banco.');
        }

        if ($action === 'approve') {
            $unique_url = home_url("/wp-json/unique/v1/" . urlencode($agendamento_id));
            $response = wp_remote_post($unique_url, [
                'headers' => ['Content-Type' => 'application/json', 'X-API-KEY' => get_option('acm_master_api_key')],
                'body' => json_encode(['action' => 'approve']),
                'timeout' => 90
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error('Erro ao chamar Unique Endpoint: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200 && $response_code !== 202) {
                wp_send_json_error("Unique Endpoint retornou erro: {$response_code} - {$response_body}");
            }
        }

        wp_send_json_success("Campanha {$action} realizada com sucesso.");
    }
}
new WP_Agendamentos_API_For_Dashboard();

// =========================================================================
//  CPT e colunas customizadas (Sem alterações)
// =========================================================================

function ga_register_campaign_log_cpt() {
    $labels = [
        'name' => _x('Logs de Campanhas', 'post type general name'), 'singular_name' => _x('Log de Campanha', 'post type singular name'),
        'menu_name' => _x('Logs de Campanhas', 'admin menu'), 'name_admin_bar' => _x('Log de Campanha', 'add new on admin bar'),
        'add_new' => _x('Adicionar Novo', 'book'), 'add_new_item' => __('Adicionar Novo Log'), 'new_item' => __('Novo Log'),
        'edit_item' => __('Editar Log'), 'view_item' => __('Ver Log'), 'all_items' => __('Todos os Logs'),
        'search_items' => __('Pesquisar Logs'), 'not_found' => __('Nenhum log encontrado.'), 'not_found_in_trash' => __('Nenhum log encontrado na lixeira.')
    ];
    $args = [
        'labels' => $labels, 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'query_var' => true,
        'rewrite' => ['slug' => 'campaign-log'], 'capability_type' => 'post', 'has_archive' => false,
        'hierarchical' => false, 'menu_position' => 28, 'menu_icon' => 'dashicons-clipboard',
        'supports' => ['title', 'editor', 'author'], 'show_in_rest' => true
    ];
    register_post_type('campaign_log', $args);
}
add_action('init', 'ga_register_campaign_log_cpt');

function ga_set_campaign_log_columns($columns) {
    unset($columns['title'], $columns['author'], $columns['date']);
    $columns['campaign_id'] = __('ID da Campanha'); $columns['decision'] = __('Decisão');
    $columns['responsible'] = __('Responsável'); $columns['date'] = __('Data da Ação');
    return $columns;
}
add_filter('manage_campaign_log_posts_columns', 'ga_set_campaign_log_columns');

function ga_custom_campaign_log_column($column, $post_id) {
    switch ($column) {
        case 'campaign_id': echo get_the_title($post_id); break;
        case 'decision':
            $decision = get_post_meta($post_id, '_campaign_decision', true);
            if ($decision === 'approve') { echo '<span style="color:green; font-weight:bold;">Aprovada</span>'; }
            elseif ($decision === 'deny') { echo '<span style="color:red; font-weight:bold;">Negada</span>'; }
            break;
        case 'responsible': echo get_the_author_meta('display_name', get_post_field('post_author', $post_id)); break;
    }
}
add_action('manage_campaign_log_posts_custom_column', 'ga_custom_campaign_log_column', 10, 2);