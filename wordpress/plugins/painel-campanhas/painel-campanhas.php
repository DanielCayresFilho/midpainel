<?php
/**
 * Plugin Name: Painel de Campanhas
 * Description: Sistema completo de gerenciamento de campanhas com interface moderna e integração com API
 * Version: 1.0.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class Painel_Campanhas {
    private static $instance = null;
    private $plugin_path;
    private $plugin_url;
    private $version = '1.0.0';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        $this->init_hooks();
    }

    private function init_hooks() {
        // Ativação/Desativação
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Inicialização
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Rotas customizadas
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_custom_routes']);
        
        // AJAX
        add_action('wp_ajax_pc_login', [$this, 'handle_login']);
        add_action('wp_ajax_nopriv_pc_login', [$this, 'handle_login']);
        add_action('wp_ajax_pc_logout', [$this, 'handle_logout']);
        
        // AJAX para campanhas CPF
        add_action('wp_ajax_cpf_cm_upload_csv', [$this, 'handle_cpf_upload_csv']);
        add_action('wp_ajax_cpf_cm_get_custom_filters', [$this, 'handle_cpf_get_custom_filters']);
        add_action('wp_ajax_cpf_cm_preview_count', [$this, 'handle_cpf_preview_count']);
        add_action('wp_ajax_cpf_cm_generate_clean_file', [$this, 'handle_cpf_generate_clean_file']);
        add_action('wp_ajax_cpf_cm_create_campaign', [$this, 'handle_create_cpf_campaign']);
        
        // AJAX para campanhas recorrentes
        add_action('wp_ajax_cm_save_recurring', [$this, 'handle_save_recurring']);
        add_action('wp_ajax_cm_get_recurring', [$this, 'handle_get_recurring']);
        add_action('wp_ajax_cm_delete_recurring', [$this, 'handle_delete_recurring']);
        add_action('wp_ajax_cm_toggle_recurring', [$this, 'handle_toggle_recurring']);
        add_action('wp_ajax_cm_execute_recurring_now', [$this, 'handle_execute_recurring_now']);
        add_action('wp_ajax_cm_preview_recurring_count', [$this, 'handle_preview_recurring_count']);
        
        // AJAX para criar campanhas (delegar para campaign-manager se disponível, senão usar handler próprio)
        add_action('wp_ajax_cm_schedule_campaign', [$this, 'handle_schedule_campaign']);
        add_action('wp_ajax_cm_get_filters', [$this, 'handle_get_filters']);
        add_action('wp_ajax_cm_get_count', [$this, 'handle_get_count']);
        add_action('wp_ajax_cm_get_template_content', [$this, 'handle_get_template_content']);
        
        // AJAX para mensagens
        add_action('wp_ajax_pc_get_messages', [$this, 'handle_get_messages']);
        add_action('wp_ajax_pc_get_message', [$this, 'handle_get_message']);
        add_action('wp_ajax_pc_create_message', [$this, 'handle_create_message']);
        add_action('wp_ajax_pc_update_message', [$this, 'handle_update_message']);
        add_action('wp_ajax_pc_delete_message', [$this, 'handle_delete_message']);
        
        // AJAX para relatórios
        add_action('wp_ajax_pc_get_report_data', [$this, 'handle_get_report_data']);
        add_action('wp_ajax_pc_get_report_1x1_stats', [$this, 'handle_get_report_1x1_stats']);
        
        // Download CSV
        add_action('admin_post_pc_download_csv_geral', [$this, 'handle_download_csv_geral']);
        add_action('admin_post_pc_download_csv_agendamento', [$this, 'handle_download_csv_agendamento']);
        
        // AJAX para API Manager
        add_action('wp_ajax_pc_save_master_api_key', [$this, 'handle_save_master_api_key']);
        add_action('wp_ajax_pc_save_microservice_config', [$this, 'handle_save_microservice_config']);
        add_action('wp_ajax_pc_save_static_credentials', [$this, 'handle_save_static_credentials']);
        add_action('wp_ajax_pc_create_credential', [$this, 'handle_create_credential']);
        add_action('wp_ajax_pc_get_credential', [$this, 'handle_get_credential']);
        add_action('wp_ajax_pc_update_credential', [$this, 'handle_update_credential']);
        add_action('wp_ajax_pc_delete_credential', [$this, 'handle_delete_credential']);
        
        // AJAX para Aprovar Campanhas
        add_action('wp_ajax_pc_get_pending_campaigns', [$this, 'handle_get_pending_campaigns']);
        add_action('wp_ajax_pc_get_microservice_config', [$this, 'handle_get_microservice_config']);
        add_action('wp_ajax_pc_update_campaign_status', [$this, 'handle_update_campaign_status']);
        add_action('wp_ajax_pc_approve_campaign', [$this, 'handle_approve_campaign']);
        add_action('wp_ajax_pc_deny_campaign', [$this, 'handle_deny_campaign']);
        
        // Admin Post handlers
        add_action('admin_post_save_master_api_key', [$this, 'handle_save_master_api_key']);
        
        // Proteção de rotas
        add_action('template_redirect', [$this, 'check_authentication']);
        
        // REST API para microserviço buscar dados
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function register_rest_routes() {
        register_rest_route('campaigns/v1', '/data/(?P<agendamento_id>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_campaign_data_rest'],
            'permission_callback' => [$this, 'check_api_key_rest'],
        ]);
        
        register_rest_route('api-manager/v1', '/credentials/(?P<provider>[^/]+)/(?P<env_id>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_credentials_rest'],
            'permission_callback' => [$this, 'check_api_key_rest'],
        ]);
    }
    
    public function check_api_key_rest($request) {
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
    
    public function get_campaign_data_rest($request) {
        $agendamento_id = $request->get_param('agendamento_id');
        
        if (empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'Agendamento ID é obrigatório.', ['status' => 400]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'envios_pendentes';
        
        $query = $wpdb->prepare("
            SELECT 
                CONCAT('55', telefone) as telefone,
                nome,
                idgis_ambiente,
                idcob_contrato,
                COALESCE(cpf_cnpj, '') as cpf_cnpj,
                data_cadastro as data_cadastro,
                mensagem
            FROM {$table}
            WHERE agendamento_id = %s
            AND status IN ('pendente_aprovacao', 'pendente')
            ORDER BY id ASC
        ", $agendamento_id);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            return new WP_Error('no_data', 'Nenhum dado encontrado para este agendamento.', ['status' => 404]);
        }
        
        // Formata os dados conforme esperado pelo microserviço (CampaignData interface)
        $formatted_data = [];
        foreach ($results as $row) {
            $formatted_data[] = [
                'telefone' => (string) $row['telefone'],
                'nome' => (string) $row['nome'],
                'idgis_ambiente' => (string) $row['idgis_ambiente'],
                'idcob_contrato' => (string) $row['idcob_contrato'],
                'cpf_cnpj' => (string) $row['cpf_cnpj'],
                'mensagem' => (string) $row['mensagem'],
                'data_cadastro' => (string) ($row['data_cadastro'] ?: date('Y-m-d H:i:s')),
            ];
        }
        
        return rest_ensure_response($formatted_data);
    }

    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        // Inicializa componentes
    }

    public function add_rewrite_rules() {
        // Rotas principais
        add_rewrite_rule('^painel/login/?$', 'index.php?pc_page=login', 'top');
        add_rewrite_rule('^painel/home/?$', 'index.php?pc_page=home', 'top');
        add_rewrite_rule('^painel/campanhas/?$', 'index.php?pc_page=campanhas', 'top');
        add_rewrite_rule('^painel/nova-campanha/?$', 'index.php?pc_page=nova-campanha', 'top');
        add_rewrite_rule('^painel/campanhas-recorrentes/?$', 'index.php?pc_page=campanhas-recorrentes', 'top');
        add_rewrite_rule('^painel/aprovar-campanhas/?$', 'index.php?pc_page=aprovar-campanhas', 'top');
        add_rewrite_rule('^painel/mensagens/?$', 'index.php?pc_page=mensagens', 'top');
        add_rewrite_rule('^painel/relatorios/?$', 'index.php?pc_page=relatorios', 'top');
        add_rewrite_rule('^painel/api-manager/?$', 'index.php?pc_page=api-manager', 'top');
        add_rewrite_rule('^painel/configuracoes/?$', 'index.php?pc_page=configuracoes', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'pc_page';
        return $vars;
    }

    public function handle_custom_routes() {
        $page = get_query_var('pc_page');
        
        if (empty($page)) {
            return;
        }

        // Redireciona para login se não autenticado (exceto página de login)
        if ($page !== 'login' && !$this->is_authenticated()) {
            wp_redirect(home_url('/painel/login'));
            exit;
        }

        // Redireciona para home se já autenticado e tentando acessar login
        if ($page === 'login' && $this->is_authenticated()) {
            wp_redirect(home_url('/painel/home'));
            exit;
        }

        // Verifica permissão para páginas de administrador
        $admin_pages = ['aprovar-campanhas', 'api-manager', 'relatorios'];
        if (in_array($page, $admin_pages) && !current_user_can('manage_options')) {
            wp_redirect(home_url('/painel/home'));
            exit;
        }

        // Carrega a página correspondente
        $this->render_page($page);
        exit;
    }

    public function check_authentication() {
        $page = get_query_var('pc_page');
        
        if (empty($page) || $page === 'login') {
            return;
        }

        if (!$this->is_authenticated()) {
            wp_redirect(home_url('/painel/login'));
            exit;
        }
    }

    public function is_authenticated() {
        return is_user_logged_in();
    }

    public function can_access_admin_pages() {
        return current_user_can('manage_options');
    }

    public function render_page($page) {
        $template_file = $this->plugin_path . 'templates/' . $page . '.php';
        
        if (file_exists($template_file)) {
            // Define variáveis globais para os templates
            global $pc_current_page, $pc_plugin_path;
            $pc_current_page = $page;
            $pc_plugin_path = $this->plugin_path;
            
            include $template_file;
        } else {
            wp_die('Página não encontrada', 'Erro 404', ['response' => 404]);
        }
    }
    
    public function get_plugin_path() {
        return $this->plugin_path;
    }
    
    public function get_plugin_url() {
        return $this->plugin_url;
    }

    public function enqueue_assets() {
        $page = get_query_var('pc_page');
        
        if (empty($page)) {
            return;
        }

        // Tailwind CSS via CDN
        wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
        
        // Font Awesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');
        
        // CSS customizado
        wp_enqueue_style('painel-campanhas', $this->plugin_url . 'assets/css/style.css', [], $this->version);
        
        // CSS para filtros dinâmicos
        if ($page === 'nova-campanha') {
            wp_enqueue_style('filters-dynamic', $this->plugin_url . 'assets/css/filters.css', [], $this->version);
        }
        
        // JavaScript customizado (jQuery já está no WordPress)
        wp_enqueue_script('painel-campanhas', $this->plugin_url . 'assets/js/main.js', ['jquery'], $this->version, true);
        
        // Localize script
        wp_localize_script('painel-campanhas', 'pcData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc_nonce'),
            'homeUrl' => home_url(),
            'apiUrl' => rest_url('painel-campanhas/v1/'),
        ]);

        // JavaScript específico para nova campanha
        if ($page === 'nova-campanha') {
            wp_enqueue_script('nova-campanha', $this->plugin_url . 'assets/js/nova-campanha.js', ['jquery', 'painel-campanhas'], $this->version, true);
            
            // Localize script para nova campanha
            wp_localize_script('nova-campanha', 'pcAjax', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('campaign-manager-nonce'),
                'cpfNonce' => wp_create_nonce('cpf-campaign-nonce'),
                'homeUrl' => home_url(),
            ]);
        }
    }

    public function handle_login() {
        check_ajax_referer('pc_nonce', 'nonce');

        $username = sanitize_user($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

        if (empty($username) || empty($password)) {
            wp_send_json_error(['message' => 'Usuário e senha são obrigatórios']);
        }

        $creds = [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => $user->get_error_message() ?: 'Credenciais inválidas']);
        }

        wp_send_json_success([
            'message' => 'Login realizado com sucesso',
            'redirect' => home_url('/painel/home'),
        ]);
    }

    public function handle_logout() {
        check_ajax_referer('pc_nonce', 'nonce');
        wp_logout();
        wp_send_json_success(['redirect' => home_url('/painel/login')]);
    }

    public function handle_save_master_api_key() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $master_api_key = sanitize_text_field($_POST['master_api_key'] ?? '');
        update_option('acm_master_api_key', $master_api_key);

        wp_send_json_success(['message' => 'Master API Key salva com sucesso!']);
    }

    // ========== HANDLERS PARA API MANAGER ==========
    
    public function handle_save_microservice_config() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $config = [
            'url' => esc_url_raw($_POST['microservice_url'] ?? ''),
            'api_key' => sanitize_text_field($_POST['microservice_api_key'] ?? '')
        ];

        update_option('acm_microservice_config', $config);

        wp_send_json_success(['message' => 'Configuração do microserviço salva com sucesso!']);
    }
    
    public function handle_save_static_credentials() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $static_data = $_POST['static_credentials'] ?? [];
        
        $static_credentials = [
            // CDA
            'cda_api_url' => esc_url_raw($static_data['cda_api_url'] ?? ''),
            'cda_api_key' => sanitize_text_field($static_data['cda_api_key'] ?? ''),
            
            // Salesforce
            'sf_client_id' => sanitize_text_field($static_data['sf_client_id'] ?? ''),
            'sf_client_secret' => sanitize_text_field($static_data['sf_client_secret'] ?? ''),
            'sf_username' => sanitize_text_field($static_data['sf_username'] ?? ''),
            'sf_password' => sanitize_text_field($static_data['sf_password'] ?? ''),
            'sf_token_url' => esc_url_raw($static_data['sf_token_url'] ?? ''),
            'sf_api_url' => esc_url_raw($static_data['sf_api_url'] ?? ''),
            
            // Marketing Cloud
            'mkc_client_id' => sanitize_text_field($static_data['mkc_client_id'] ?? ''),
            'mkc_client_secret' => sanitize_text_field($static_data['mkc_client_secret'] ?? ''),
            'mkc_token_url' => esc_url_raw($static_data['mkc_token_url'] ?? ''),
            'mkc_api_url' => esc_url_raw($static_data['mkc_api_url'] ?? ''),
            
            // RCS CDA (CromosApp) - funciona igual ao CDA
            // codigo_equipe = idgis_ambiente (vem dos dados)
            // codigo_usuario = sempre '1'
            'rcs_chave_api' => sanitize_text_field($static_data['rcs_chave_api'] ?? ''),
            'rcs_base_url' => esc_url_raw($static_data['rcs_base_url'] ?? ''),
            'rcs_token' => sanitize_text_field($static_data['rcs_token'] ?? ''), // Mantido para compatibilidade
            
            // Dashboard Password
            'dashboard_password' => sanitize_text_field($static_data['dashboard_password'] ?? '')
        ];
        
        update_option('acm_static_credentials', $static_credentials);
        
        // Salva também no option antigo para compatibilidade
        if (!empty($static_credentials['dashboard_password'])) {
            update_option('ga_dashboard_password', $static_credentials['dashboard_password']);
        }

        wp_send_json_success(['message' => 'Static credentials salvas com sucesso!']);
    }
    
    public function handle_create_credential() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $provider = sanitize_key($_POST['provider'] ?? '');
        $env_id = sanitize_text_field($_POST['env_id'] ?? '');
        $credential_data = $_POST['credential_data'] ?? [];

        if (empty($provider) || empty($env_id) || empty($credential_data)) {
            wp_send_json_error('Dados incompletos');
            return;
        }

        $credentials = get_option('acm_provider_credentials', []);
        if (!is_array($credentials)) {
            $credentials = [];
        }

        // Sanitiza os dados da credencial
        $sanitized_data = [];
        foreach ($credential_data as $key => $value) {
            if ($key === 'url') {
                $sanitized_data[$key] = esc_url_raw($value);
            } else {
                $sanitized_data[$key] = sanitize_text_field($value);
            }
        }

        if (!isset($credentials[$provider])) {
            $credentials[$provider] = [];
        }
        
        $credentials[$provider][$env_id] = $sanitized_data;
        update_option('acm_provider_credentials', $credentials);

        wp_send_json_success(['message' => 'Credencial criada com sucesso!']);
    }
    
    public function handle_get_credential() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $provider = sanitize_key($_POST['provider'] ?? '');
        $env_id = sanitize_text_field($_POST['env_id'] ?? '');

        if (empty($provider) || empty($env_id)) {
            wp_send_json_error('Provider e Environment ID são obrigatórios');
            return;
        }

        $credentials = get_option('acm_provider_credentials', []);
        
        if (!isset($credentials[$provider][$env_id])) {
            wp_send_json_error('Credencial não encontrada');
            return;
        }

        wp_send_json_success(['data' => $credentials[$provider][$env_id]]);
    }
    
    public function handle_update_credential() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $provider = sanitize_key($_POST['provider'] ?? '');
        $env_id = sanitize_text_field($_POST['env_id'] ?? '');
        $credential_data = $_POST['credential_data'] ?? [];

        if (empty($provider) || empty($env_id) || empty($credential_data)) {
            wp_send_json_error('Dados incompletos');
            return;
        }

        $credentials = get_option('acm_provider_credentials', []);
        
        if (!isset($credentials[$provider][$env_id])) {
            wp_send_json_error('Credencial não encontrada');
            return;
        }

        // Sanitiza os dados da credencial
        $sanitized_data = [];
        foreach ($credential_data as $key => $value) {
            if ($key === 'url') {
                $sanitized_data[$key] = esc_url_raw($value);
            } else {
                $sanitized_data[$key] = sanitize_text_field($value);
            }
        }

        $credentials[$provider][$env_id] = $sanitized_data;
        update_option('acm_provider_credentials', $credentials);

        wp_send_json_success(['message' => 'Credencial atualizada com sucesso!']);
    }
    
    public function handle_delete_credential() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $provider = sanitize_key($_POST['provider'] ?? '');
        $env_id = sanitize_text_field($_POST['env_id'] ?? '');

        if (empty($provider) || empty($env_id)) {
            wp_send_json_error('Provider e Environment ID são obrigatórios');
            return;
        }

        $credentials = get_option('acm_provider_credentials', []);
        
        if (isset($credentials[$provider][$env_id])) {
            unset($credentials[$provider][$env_id]);
            update_option('acm_provider_credentials', $credentials);
            wp_send_json_success(['message' => 'Credencial deletada com sucesso!']);
        } else {
            wp_send_json_error('Credencial não encontrada');
        }
    }

    public function handle_cpf_upload_csv() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error('Nenhum arquivo enviado');
        }
        
        $match_field = sanitize_text_field($_POST['match_field'] ?? '');
        if (!in_array($match_field, ['cpf', 'telefone'], true)) {
            wp_send_json_error('Tipo de cruzamento inválido');
        }

        $file = $_FILES['csv_file'];
        
        // Validações básicas
        $allowed_types = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Apenas arquivos CSV são permitidos');
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB
            wp_send_json_error('Arquivo muito grande (máx 10MB)');
        }
        
        // Lê o arquivo
        $content = file_get_contents($file['tmp_name']);
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        
        if (empty($lines)) {
            wp_send_json_error('Arquivo CSV vazio');
        }
        
        // Extrai valores
        $values = [];
        foreach ($lines as $line) {
            if ('cpf' === $match_field) {
                $value = preg_replace('/[^0-9]/', '', $line);
                if (strlen($value) === 11) {
                    $values[] = $value;
                }
            } else {
                $value = preg_replace('/[^0-9]/', '', $line);
                $length = strlen($value);
                if ($length >= 10 && $length <= 11) {
                    $values[] = $value;
                }
            }
        }
        
        $values = array_values(array_unique($values));
        
        if (empty($values)) {
            wp_send_json_error('Nenhum dado válido encontrado no arquivo');
        }
        
        // Salva temporariamente
        $uploads_dir = wp_upload_dir()['basedir'] . '/cpf-campaigns/';
        if (!file_exists($uploads_dir)) {
            wp_mkdir_p($uploads_dir);
            file_put_contents($uploads_dir . '.htaccess', 'deny from all');
        }
        
        $temp_id = uniqid('cpf_', true);
        $temp_file = $uploads_dir . $temp_id . '.json';
        $payload = [
            'match_field' => $match_field,
            'values' => $values
        ];
        file_put_contents($temp_file, wp_json_encode($payload));
        
        wp_send_json_success([
            'temp_id' => $temp_id,
            'count' => count($values),
            'preview' => array_slice($values, 0, 5),
            'match_field' => $match_field
        ]);
    }
    
    public function handle_cpf_get_custom_filters() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        
        if (empty($table_name)) {
            wp_send_json_error('Tabela não especificada');
        }
        
        // Filtros permitidos
        $allowed_filters = [
            'STATUS_TELEFONE' => 'categorical',
            'VISAO_CPF_V8' => 'categorical',
            'SCORE_V8' => 'categorical',
        ];
        
        global $wpdb;
        $filters = [];
        
        foreach ($allowed_filters as $column => $type) {
            // Verifica se a coluna existe
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $table_name,
                $column
            ));
            
            if ($column_exists) {
                // Busca valores únicos
                $values = $wpdb->get_col(
                    "SELECT DISTINCT `{$column}` 
                     FROM `{$table_name}` 
                     WHERE `{$column}` IS NOT NULL 
                     AND `{$column}` != '' 
                     ORDER BY `{$column}` ASC
                     LIMIT 100"
                );
                
                if (!empty($values)) {
                    $filters[$column] = [
                        'type' => $type,
                        'values' => $values
                    ];
                }
            }
        }
        
        wp_send_json_success($filters);
    }
    
    public function handle_cpf_preview_count() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        
        if (empty($table_name) || empty($temp_id)) {
            wp_send_json_error('Dados incompletos');
        }

        $temp_payload = $this->load_cpf_temp_payload($temp_id);
        if (empty($temp_payload)) {
            wp_send_json_error('Arquivo temporário não encontrado');
        }

        $values = $temp_payload['values'];
        $match_field = $temp_payload['match_field'] ?? 'cpf';

        // Constrói query
        global $wpdb;
        $where_sql = $this->build_cpf_where_sql($wpdb, $table_name, $values, $filters, $match_field);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` {$where_sql}");
        
        wp_send_json_success(['count' => intval($count)]);
    }
    
    public function handle_cpf_generate_clean_file() {
        check_ajax_referer('pc_nonce', 'nonce');
        global $wpdb;
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        
        if (empty($table_name) || empty($temp_id)) {
            wp_send_json_error('Dados incompletos');
        }
        
        $temp_payload = $this->load_cpf_temp_payload($temp_id);
        if (empty($temp_payload)) {
            wp_send_json_error('Arquivo temporário não encontrado');
        }

        $values = $temp_payload['values'];
        $match_field = $temp_payload['match_field'] ?? 'cpf';

        $records = $this->get_cpf_records($wpdb, $table_name, $values, $filters, $match_field);

        if (empty($records)) {
            wp_send_json_error('Nenhum registro encontrado');
        }

        $csv = $this->build_cpf_clean_csv($records);
        $filename = 'cpf-campaign-' . current_time('YmdHis') . '.csv';

        wp_send_json_success([
            'file' => base64_encode($csv),
            'filename' => $filename
        ]);
    }
    
    private function load_cpf_temp_payload($temp_id) {
        $uploads_dir = wp_upload_dir()['basedir'] . '/cpf-campaigns/';
        $temp_file = $uploads_dir . $temp_id . '.json';
        if (!file_exists($temp_file)) {
            return null;
        }

        $payload = json_decode(file_get_contents($temp_file), true);
        if (empty($payload['values'])) {
            return null;
        }

        return $payload;
    }
    
    private function get_cpf_records($wpdb, $table_name, $values, $filters, $match_field) {
        $where_sql = $this->build_cpf_where_sql($wpdb, $table_name, $values, $filters, $match_field);

        $sql = "SELECT 
                    `NOME` as nome,
                    `TELEFONE` as telefone,
                    `CPF` as cpf_cnpj,
                    `IDCOB_CONTRATO` as idcob_contrato
                FROM `{$table_name}` {$where_sql}";

        return $wpdb->get_results($sql, ARRAY_A);
    }
    
    private function build_cpf_clean_csv($records) {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['nome', 'telefone', 'cpf', 'idcob_contrato'], ';');

        foreach ($records as $record) {
            $phone = preg_replace('/[^0-9]/', '', $record['telefone'] ?? '');
            if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
                $phone = substr($phone, 2);
            }
            
            fputcsv($handle, [
                $record['nome'] ?? '',
                $phone,
                $record['cpf_cnpj'] ?? '',
                $record['idcob_contrato'] ?? ''
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function handle_create_cpf_campaign() {
        check_ajax_referer('pc_nonce', 'nonce');

        global $wpdb;
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $match_field = sanitize_text_field($_POST['match_field'] ?? 'cpf');
        $template_id = intval($_POST['template_id'] ?? 0);
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        $providers_config_json = stripslashes($_POST['providers_config'] ?? '{}');
        $providers_config = json_decode($providers_config_json, true);

        if (empty($table_name) || empty($temp_id) || !$template_id || empty($providers_config['providers'])) {
            wp_send_json_error('Dados incompletos');
        }

        // Carrega template
        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'message_template') {
            wp_send_json_error('Template inválido');
        }
        $message_content = $template->post_content;

        // Carrega arquivo temporário
        $uploads_dir = wp_upload_dir()['basedir'] . '/cpf-campaigns/';
        $temp_file = $uploads_dir . $temp_id . '.json';
        if (!file_exists($temp_file)) {
            wp_send_json_error('Arquivo temporário não encontrado');
        }
        $temp_payload = json_decode(file_get_contents($temp_file), true);
        $values = $temp_payload['values'] ?? [];

        // Busca registros
        $where_sql = $this->build_cpf_where_sql($wpdb, $table_name, $values, $filters, $match_field);
        $sql = "SELECT 
                    `NOME` as nome,
                    `TELEFONE` as telefone,
                    `CPF` as cpf_cnpj,
                    `IDCOB_CONTRATO` as idcob_contrato,
                    `IDGIS_AMBIENTE` as idgis_ambiente
                FROM `{$table_name}` {$where_sql}";
        
        $records = $wpdb->get_results($sql, ARRAY_A);

        if (empty($records)) {
            wp_send_json_error('Nenhum registro encontrado');
        }

        // Distribui entre provedores
        $distributed_records = $this->distribute_records($records, $providers_config);

        // Insere na tabela envios_pendentes
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $current_user_id = get_current_user_id();
        $agendamento_base_id = current_time('YmdHis');
        $total_inserted = 0;

        foreach ($distributed_records as $provider_data) {
            $provider = $provider_data['provider'];
            $provider_records = $provider_data['records'];
            $prefix = strtoupper(substr($provider, 0, 1));
            $agendamento_id = $prefix . $agendamento_base_id;

            foreach ($provider_records as $record) {
                $telefone = preg_replace('/[^0-9]/', '', $record['telefone'] ?? '');
                if (strlen($telefone) > 11 && substr($telefone, 0, 2) === '55') {
                    $telefone = substr($telefone, 2);
                }

                $mensagem_final = $this->replace_placeholders($message_content, $record);

                $insert_data = [
                    'telefone' => $telefone,
                    'nome' => $record['nome'] ?? '',
                    'idgis_ambiente' => intval($record['idgis_ambiente'] ?? 0),
                    'idcob_contrato' => intval($record['idcob_contrato'] ?? 0),
                    'cpf_cnpj' => $record['cpf_cnpj'] ?? '',
                    'mensagem' => $mensagem_final,
                    'fornecedor' => $provider,
                    'agendamento_id' => $agendamento_id,
                    'status' => 'pendente_aprovacao',
                    'current_user_id' => $current_user_id,
                    'valido' => 1,
                    'data_cadastro' => current_time('mysql')
                ];

                $wpdb->insert($envios_table, $insert_data, ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']);
                $total_inserted++;
            }
        }

        // Remove arquivo temporário
        @unlink($temp_file);

        wp_send_json_success([
            'message' => "Campanha criada com sucesso! {$total_inserted} registros inseridos.",
            'agendamento_id' => $agendamento_base_id,
            'records_inserted' => $total_inserted
        ]);
    }

    private function distribute_records($records, $providers_config) {
        $total_records = count($records);
        $distribution_mode = $providers_config['mode'] ?? 'split';
        $providers = $providers_config['providers'] ?? [];

        if ($distribution_mode === 'all') {
            $result = [];
            foreach ($providers as $provider) {
                $result[] = ['provider' => $provider, 'records' => $records];
            }
            return $result;
        }

        $percentages = $providers_config['percentages'] ?? [];
        $total_percent = array_sum($percentages);
        if ($total_percent != 100 && $total_percent > 0) {
            foreach ($percentages as $provider => $percent) {
                $percentages[$provider] = ($percent / $total_percent) * 100;
            }
        }

        shuffle($records);
        $result = [];
        $start_index = 0;

        foreach ($providers as $i => $provider) {
            $percent = $percentages[$provider] ?? (100 / count($providers));
            $count = round(($percent / 100) * $total_records);
            
            if ($i === count($providers) - 1) {
                $count = $total_records - $start_index;
            }

            $provider_records = array_slice($records, $start_index, $count);
            if (!empty($provider_records)) {
                $result[] = ['provider' => $provider, 'records' => $provider_records];
            }
            $start_index += $count;
        }

        return $result;
    }

    private function replace_placeholders($message, $record) {
        $replacements = [
            '[[NOME]]' => $record['nome'] ?? '',
            '[[TELEFONE]]' => $record['telefone'] ?? '',
            '[[CPF]]' => $record['cpf_cnpj'] ?? '',
            '[[CONTRATO]]' => $record['idcob_contrato'] ?? '',
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        return $message;
    }

    // Helpers para integração com outros plugins
    public function get_api_credentials($provider, $env_id) {
        $credentials = get_option('acm_provider_credentials', []);
        
        if (isset($credentials[$provider][$env_id])) {
            return $credentials[$provider][$env_id];
        }
        
        return null;
    }

    public function get_master_api_key() {
        return get_option('acm_master_api_key', '');
    }

    public function get_agendamentos($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'agendamentos';
        
        $query = "SELECT * FROM {$table}";
        
        if ($status) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query .= " ORDER BY data_cadastro DESC";
        
        return $wpdb->get_results($query);
    }

    public function handle_save_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $nome_campanha = sanitize_text_field($_POST['nome_campanha'] ?? '');
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $providers_config_json = stripslashes($_POST['providers_config'] ?? '{}');
        $template_id = intval($_POST['template_id'] ?? 0);
        $record_limit = intval($_POST['record_limit'] ?? 0);
        $exclude_recent_phones = isset($_POST['exclude_recent_phones']) ? intval($_POST['exclude_recent_phones']) : 1;
        
        if (empty($nome_campanha) || empty($table_name) || empty($template_id)) {
            wp_send_json_error('Dados incompletos para criar template.');
        }
        
        // Cria tabela se não existir
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            nome_campanha varchar(255) NOT NULL,
            tabela_origem varchar(150) NOT NULL,
            filtros_json text,
            providers_config text NOT NULL,
            template_id bigint(20) NOT NULL,
            record_limit int(11) DEFAULT 0,
            ativo tinyint(1) DEFAULT 1,
            ultima_execucao datetime DEFAULT NULL,
            criado_por bigint(20) NOT NULL,
            criado_em datetime DEFAULT CURRENT_TIMESTAMP,
            atualizado_em datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Adiciona exclusão ao config
        $config_array = json_decode($providers_config_json, true);
        if (!is_array($config_array)) {
            $config_array = [];
        }
        $config_array['exclude_recent_phones'] = $exclude_recent_phones;
        $providers_config_json = json_encode($config_array);
        
        $result = $wpdb->insert(
            $table,
            [
                'nome_campanha' => $nome_campanha,
                'tabela_origem' => $table_name,
                'filtros_json' => $filters_json,
                'providers_config' => $providers_config_json,
                'template_id' => $template_id,
                'record_limit' => $record_limit,
                'ativo' => 1,
                'criado_por' => get_current_user_id()
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d']
        );
        
        if ($result === false) {
            wp_send_json_error('Erro ao salvar template: ' . $wpdb->last_error);
        }
        
        wp_send_json_success('Template salvo com sucesso!');
    }

    public function handle_schedule_campaign() {
        error_log('🔵 Painel Campanhas - handle_schedule_campaign chamado');
        
        // Se o campaign-manager estiver disponível, delega para ele
        if (class_exists('Campaign_Manager_Ajax')) {
            error_log('🔵 Delegando para Campaign_Manager_Ajax');
            $cm_ajax = new Campaign_Manager_Ajax();
            $cm_ajax->schedule_campaign();
            return;
        }
        
        error_log('🔵 Usando handler próprio do Painel Campanhas');
        
        // Caso contrário, implementa handler próprio
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        $providers_config_json = stripslashes($_POST['providers_config'] ?? '{}');
        $providers_config = json_decode($providers_config_json, true);
        $template_id = intval($_POST['template_id'] ?? 0);
        $record_limit = intval($_POST['record_limit'] ?? 0);
        $exclude_recent_phones = isset($_POST['exclude_recent_phones']) ? intval($_POST['exclude_recent_phones']) : 1;
        
        error_log('🔵 Dados recebidos: ' . json_encode([
            'table_name' => $table_name,
            'template_id' => $template_id,
            'providers_config' => $providers_config,
            'filters_count' => count($filters ?? []),
            'exclude_recent_phones' => $exclude_recent_phones
        ]));
        
        if (empty($table_name) || empty($providers_config) || empty($template_id)) {
            error_log('❌ Dados inválidos: table_name=' . $table_name . ', template_id=' . $template_id . ', providers=' . json_encode($providers_config));
            wp_send_json_error('Dados da campanha inválidos.');
        }
        
        // Busca template
        $message_post = get_post($template_id);
        if (!$message_post || $message_post->post_type !== 'message_template') {
            wp_send_json_error('Template de mensagem inválido.');
        }
        $message_content = $message_post->post_content;
        
        // Busca registros filtrados
        if (class_exists('Campaign_Manager_Filters')) {
            $records = Campaign_Manager_Filters::get_filtered_records($table_name, $filters, $record_limit);
        } else {
            wp_send_json_error('Campaign Manager não está disponível. Por favor, ative o plugin Campaign Manager.');
        }
        
        if (empty($records)) {
            wp_send_json_error('Nenhum registro encontrado com os filtros aplicados.');
        }
        
        // Distribui entre provedores
        $distributed_records = $this->distribute_records($records, $providers_config);
        
        // Insere na tabela envios_pendentes
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $current_user_id = get_current_user_id();
        $agendamento_base_id = current_time('YmdHis');
        $total_inserted = 0;
        $total_skipped = 0;
        
        // 🚀 OTIMIZAÇÃO: Busca todos os telefones recentes de uma vez (se necessário)
        $recent_phones = [];
        if ($exclude_recent_phones) {
            $recent_phones = $this->get_recent_phones_batch($envios_table);
            error_log('🔵 Telefones recentes encontrados: ' . count($recent_phones));
        }
        
        // Prepara todos os dados para inserção em lote
        $all_insert_data = [];
        
        foreach ($distributed_records as $provider_data) {
            $provider = $provider_data['provider'];
            $provider_records = $provider_data['records'];
            $prefix = strtoupper(substr($provider, 0, 1));
            $agendamento_id = $prefix . $agendamento_base_id;
            
            foreach ($provider_records as $record) {
                $telefone = preg_replace('/[^0-9]/', '', $record['telefone'] ?? '');
                if (strlen($telefone) > 11 && substr($telefone, 0, 2) === '55') {
                    $telefone = substr($telefone, 2);
                }
                
                // Verifica se deve excluir telefones recentes (usando array em memória)
                if ($exclude_recent_phones && isset($recent_phones[$telefone])) {
                    $total_skipped++;
                    continue;
                }
                
                $mensagem_final = $this->replace_placeholders($message_content, $record);
                
                $all_insert_data[] = [
                    'telefone' => $telefone,
                    'nome' => $record['nome'] ?? '',
                    'idgis_ambiente' => intval($record['idgis_ambiente'] ?? 0),
                    'idcob_contrato' => intval($record['idcob_contrato'] ?? 0),
                    'cpf_cnpj' => $record['cpf_cnpj'] ?? '',
                    'mensagem' => $mensagem_final,
                    'fornecedor' => $provider,
                    'agendamento_id' => $agendamento_id,
                    'status' => 'pendente_aprovacao',
                    'current_user_id' => $current_user_id,
                    'valido' => 1,
                    'data_cadastro' => current_time('mysql')
                ];
            }
        }
        
        // 🚀 OTIMIZAÇÃO: Insere em lotes de 500 registros
        if (!empty($all_insert_data)) {
            $batch_size = 500;
            $batches = array_chunk($all_insert_data, $batch_size);
            
            foreach ($batches as $batch) {
                $this->bulk_insert($envios_table, $batch);
                $total_inserted += count($batch);
            }
        }
        
        if ($total_inserted === 0) {
            wp_send_json_error('Nenhum registro foi inserido. Verifique os filtros e tente novamente.');
        }
        
        $message = "Campanha agendada! {$total_inserted} clientes inseridos.";
        if ($total_skipped > 0) {
            $message .= " {$total_skipped} telefones excluídos (já receberam mensagem recentemente).";
        }
        
        wp_send_json_success([
            'message' => $message,
            'agendamento_id' => $agendamento_base_id,
            'records_inserted' => $total_inserted,
            'records_skipped' => $total_skipped,
            'exclusion_enabled' => $exclude_recent_phones
        ]);
    }

    public function handle_get_filters() {
        if (class_exists('Campaign_Manager_Ajax')) {
            $cm_ajax = new Campaign_Manager_Ajax();
            $cm_ajax->get_filters();
            return;
        }
        wp_send_json_error('Campaign Manager não está disponível.');
    }

    public function handle_get_count() {
        if (class_exists('Campaign_Manager_Ajax')) {
            $cm_ajax = new Campaign_Manager_Ajax();
            $cm_ajax->get_count();
            return;
        }
        wp_send_json_error('Campaign Manager não está disponível.');
    }

    public function handle_get_template_content() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if ($template_id <= 0) {
            wp_send_json_error('ID do template inválido.');
            return;
        }
        
        $template_post = get_post($template_id);
        
        if (!$template_post || $template_post->post_type !== 'message_template') {
            wp_send_json_error('Template não encontrado.');
            return;
        }
        
        // Retorna apenas o conteúdo como string
        wp_send_json_success($template_post->post_content);
    }

    // ========== HANDLERS PARA MENSAGENS ==========
    
    public function handle_get_messages() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $current_user_id = get_current_user_id();
        
        $messages = get_posts([
            'post_type' => 'message_template',
            'author' => $current_user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ]);
        
        $formatted_messages = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'date' => $post->post_date
            ];
        }, $messages);
        
        wp_send_json_success($formatted_messages);
    }
    
    public function handle_get_message() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $message_id = intval($_POST['message_id'] ?? 0);
        $current_user_id = get_current_user_id();
        
        if ($message_id <= 0) {
            wp_send_json_error('ID da mensagem inválido.');
            return;
        }
        
        $post = get_post($message_id);
        
        if (!$post || $post->post_type !== 'message_template') {
            wp_send_json_error('Mensagem não encontrada.');
            return;
        }
        
        // Verifica se a mensagem pertence ao usuário
        if ($post->post_author != $current_user_id) {
            wp_send_json_error('Você não tem permissão para acessar esta mensagem.');
            return;
        }
        
        wp_send_json_success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content
        ]);
    }
    
    public function handle_create_message() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $current_user_id = get_current_user_id();
        
        if (empty($title) || empty($content)) {
            wp_send_json_error('Título e conteúdo são obrigatórios.');
            return;
        }
        
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_type' => 'message_template',
            'post_status' => 'publish',
            'post_author' => $current_user_id
        ]);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Erro ao criar mensagem: ' . $post_id->get_error_message());
            return;
        }
        
        wp_send_json_success([
            'message' => 'Mensagem criada com sucesso!',
            'id' => $post_id
        ]);
    }
    
    public function handle_update_message() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $message_id = intval($_POST['message_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $current_user_id = get_current_user_id();
        
        if ($message_id <= 0) {
            wp_send_json_error('ID da mensagem inválido.');
            return;
        }
        
        if (empty($title) || empty($content)) {
            wp_send_json_error('Título e conteúdo são obrigatórios.');
            return;
        }
        
        $post = get_post($message_id);
        
        if (!$post || $post->post_type !== 'message_template') {
            wp_send_json_error('Mensagem não encontrada.');
            return;
        }
        
        // Verifica se a mensagem pertence ao usuário
        if ($post->post_author != $current_user_id) {
            wp_send_json_error('Você não tem permissão para editar esta mensagem.');
            return;
        }
        
        $updated = wp_update_post([
            'ID' => $message_id,
            'post_title' => $title,
            'post_content' => $content
        ]);
        
        if (is_wp_error($updated)) {
            wp_send_json_error('Erro ao atualizar mensagem: ' . $updated->get_error_message());
            return;
        }
        
        wp_send_json_success([
            'message' => 'Mensagem atualizada com sucesso!'
        ]);
    }
    
    public function handle_delete_message() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $message_id = intval($_POST['message_id'] ?? 0);
        $current_user_id = get_current_user_id();
        
        if ($message_id <= 0) {
            wp_send_json_error('ID da mensagem inválido.');
            return;
        }
        
        $post = get_post($message_id);
        
        if (!$post || $post->post_type !== 'message_template') {
            wp_send_json_error('Mensagem não encontrada.');
            return;
        }
        
        // Verifica se a mensagem pertence ao usuário
        if ($post->post_author != $current_user_id) {
            wp_send_json_error('Você não tem permissão para deletar esta mensagem.');
            return;
        }
        
        $deleted = wp_delete_post($message_id, true);
        
        if (!$deleted) {
            wp_send_json_error('Erro ao deletar mensagem.');
            return;
        }
        
        wp_send_json_success([
            'message' => 'Mensagem deletada com sucesso!'
        ]);
    }

    // ========== HANDLERS PARA RELATÓRIOS ==========
    
    /**
     * Coleta e sanitiza filtros do relatório
     */
    private function collect_report_filters($source) {
        $source = wp_unslash($source);
        
        $sanitize_date = function($value) {
            $value = sanitize_text_field($value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
        };
        
        return [
            'filter_user' => isset($source['filter_user']) ? sanitize_text_field($source['filter_user']) : '',
            'filter_fornecedor' => isset($source['filter_fornecedor']) ? sanitize_text_field($source['filter_fornecedor']) : '',
            'filter_ambiente' => isset($source['filter_ambiente']) ? sanitize_text_field($source['filter_ambiente']) : '',
            'filter_agendamento' => isset($source['filter_agendamento']) ? sanitize_text_field($source['filter_agendamento']) : '',
            'filter_idgis' => isset($source['filter_idgis']) ? absint($source['filter_idgis']) : 0,
            'filter_date_start' => !empty($source['filter_date_start']) ? $sanitize_date($source['filter_date_start']) : '',
            'filter_date_end' => !empty($source['filter_date_end']) ? $sanitize_date($source['filter_date_end']) : '',
        ];
    }
    
    /**
     * Constrói cláusula WHERE para relatórios
     */
    private function build_report_where_sql($filters) {
        global $wpdb;
        
        $where = ['1=1'];
        
        if (!empty($filters['filter_user'])) {
            $where[] = $wpdb->prepare('E.display_name LIKE %s', '%' . $wpdb->esc_like($filters['filter_user']) . '%');
        }
        if (!empty($filters['filter_fornecedor'])) {
            $where[] = $wpdb->prepare('P.fornecedor LIKE %s', '%' . $wpdb->esc_like($filters['filter_fornecedor']) . '%');
        }
        if (!empty($filters['filter_ambiente'])) {
            // Verifica se a tabela existe antes de usar
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                'NOME_AMBIENTE'
            ));
            if ($table_exists) {
                $where[] = $wpdb->prepare('T.NOME_AMBIENTE LIKE %s', '%' . $wpdb->esc_like($filters['filter_ambiente']) . '%');
            }
        }
        if (!empty($filters['filter_agendamento'])) {
            $where[] = $wpdb->prepare('P.agendamento_id LIKE %s', '%' . $wpdb->esc_like($filters['filter_agendamento']) . '%');
        }
        if (!empty($filters['filter_date_start'])) {
            $where[] = $wpdb->prepare('CAST(P.data_cadastro AS DATE) >= %s', $filters['filter_date_start']);
        }
        if (!empty($filters['filter_date_end'])) {
            $where[] = $wpdb->prepare('CAST(P.data_cadastro AS DATE) <= %s', $filters['filter_date_end']);
        }
        if (!empty($filters['filter_idgis'])) {
            $where[] = $wpdb->prepare('P.idgis_ambiente = %d', $filters['filter_idgis']);
        }
        
        return implode(' AND ', $where);
    }
    
    /**
     * Conta registros agrupados
     */
    private function count_report_grouped_records($where_sql) {
        global $wpdb;
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->prefix . 'users';
        $ambiente_table = 'NOME_AMBIENTE';
        
        // Verifica se a tabela de ambiente existe
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $ambiente_table
        ));
        
        $join_ambiente = $table_exists ? "LEFT JOIN {$ambiente_table} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente" : "";
        
        $query = "
            SELECT COUNT(DISTINCT CONCAT(
                CAST(P.data_cadastro AS DATE), '-', P.current_user_id, '-', P.fornecedor, '-', P.agendamento_id, '-', P.idgis_ambiente
            )) AS total
            FROM {$envios_table} P
            LEFT JOIN {$users_table} E ON E.ID = P.current_user_id
            {$join_ambiente}
            WHERE {$where_sql}
        ";
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Busca totais por status
     */
    private function fetch_report_status_totals($where_sql, $filters = []) {
        global $wpdb;
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->prefix . 'users';
        
        // Primeiro, vamos verificar se há dados na tabela e quais status existem
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$envios_table}");
        error_log('🔵 Total de registros na tabela: ' . $total_records);
        
        $status_check = $wpdb->get_col("SELECT DISTINCT status FROM {$envios_table} LIMIT 20");
        error_log('🔵 Status encontrados na tabela: ' . print_r($status_check, true));
        
        // Verifica se a tabela de ambiente existe
        $ambiente_table = 'NOME_AMBIENTE';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $ambiente_table
        ));
        
        $join_ambiente = $table_exists ? "LEFT JOIN {$ambiente_table} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente" : "";
        
        // Query simplificada - busca direto da tabela envios_pendentes
        $query = "
            SELECT
                SUM(CASE WHEN LOWER(TRIM(P.status)) = 'enviado' THEN 1 ELSE 0 END) AS total_enviado,
                SUM(CASE WHEN LOWER(TRIM(P.status)) = 'pendente_aprovacao' THEN 1 ELSE 0 END) AS total_pendente_aprovacao,
                SUM(CASE WHEN LOWER(TRIM(P.status)) = 'agendado_mkc' THEN 1 ELSE 0 END) AS total_agendado_mkc,
                SUM(CASE WHEN LOWER(TRIM(P.status)) = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN LOWER(TRIM(P.status)) = 'negado' THEN 1 ELSE 0 END) AS total_negado
            FROM {$envios_table} P
            LEFT JOIN {$users_table} E ON E.ID = P.current_user_id
            {$join_ambiente}
            WHERE {$where_sql}
        ";
        
        error_log('🔵 Query de totais: ' . $query);
        
        $result = $wpdb->get_row($query, OBJECT);
        
        error_log('🔵 Resultado totais (raw): ' . print_r($result, true));
        
        // Se não retornou resultado ou todos são NULL, tenta query mais simples
        if (!$result || (is_null($result->total_enviado) && is_null($result->total_pendente_aprovacao))) {
            error_log('🔵 Resultado vazio, tentando query sem JOINs...');
            
            // Query sem JOINs para garantir que funcione
            $simple_query = "
                SELECT
                    SUM(CASE WHEN LOWER(TRIM(status)) = 'enviado' THEN 1 ELSE 0 END) AS total_enviado,
                    SUM(CASE WHEN LOWER(TRIM(status)) = 'pendente_aprovacao' THEN 1 ELSE 0 END) AS total_pendente_aprovacao,
                    SUM(CASE WHEN LOWER(TRIM(status)) = 'agendado_mkc' THEN 1 ELSE 0 END) AS total_agendado_mkc,
                    SUM(CASE WHEN LOWER(TRIM(status)) = 'pendente' THEN 1 ELSE 0 END) AS total_pendente,
                    SUM(CASE WHEN LOWER(TRIM(status)) = 'negado' THEN 1 ELSE 0 END) AS total_negado
                FROM {$envios_table}
                WHERE 1=1
            ";
            
            // Aplica filtros básicos que não dependem de JOINs
            $simple_where = ['1=1'];
            if (!empty($filters['filter_fornecedor'] ?? '')) {
                $simple_where[] = $wpdb->prepare('fornecedor LIKE %s', '%' . $wpdb->esc_like($filters['filter_fornecedor']) . '%');
            }
            if (!empty($filters['filter_agendamento'] ?? '')) {
                $simple_where[] = $wpdb->prepare('agendamento_id LIKE %s', '%' . $wpdb->esc_like($filters['filter_agendamento']) . '%');
            }
            if (!empty($filters['filter_date_start'] ?? '')) {
                $simple_where[] = $wpdb->prepare('CAST(data_cadastro AS DATE) >= %s', $filters['filter_date_start']);
            }
            if (!empty($filters['filter_date_end'] ?? '')) {
                $simple_where[] = $wpdb->prepare('CAST(data_cadastro AS DATE) <= %s', $filters['filter_date_end']);
            }
            if (!empty($filters['filter_idgis'] ?? 0)) {
                $simple_where[] = $wpdb->prepare('idgis_ambiente = %d', $filters['filter_idgis']);
            }
            
            $simple_query = str_replace('WHERE 1=1', 'WHERE ' . implode(' AND ', $simple_where), $simple_query);
            
            error_log('🔵 Query simples: ' . $simple_query);
            $result = $wpdb->get_row($simple_query, OBJECT);
            error_log('🔵 Resultado query simples: ' . print_r($result, true));
        }
        
        if (!$result) {
            return (object) [
                'total_enviado' => 0,
                'total_pendente_aprovacao' => 0,
                'total_agendado_mkc' => 0,
                'total_pendente' => 0,
                'total_negado' => 0,
            ];
        }
        
        // Garante que os valores são números
        return (object) [
            'total_enviado' => (int) ($result->total_enviado ?? 0),
            'total_pendente_aprovacao' => (int) ($result->total_pendente_aprovacao ?? 0),
            'total_agendado_mkc' => (int) ($result->total_agendado_mkc ?? 0),
            'total_pendente' => (int) ($result->total_pendente ?? 0),
            'total_negado' => (int) ($result->total_negado ?? 0),
        ];
    }
    
    public function handle_get_report_data() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        $filters = $this->collect_report_filters($_POST);
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = max(10, intval($_POST['per_page'] ?? 25));
        $offset = ($page - 1) * $per_page;
        
        $where_sql = $this->build_report_where_sql($filters);
        
        global $wpdb;
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->prefix . 'users';
        $ambiente_table = 'NOME_AMBIENTE';
        
        $query = "
            SELECT
                CAST(P.data_cadastro AS DATE) AS DATA,
                E.display_name AS USUARIO,
                P.fornecedor AS FORNECEDOR,
                P.agendamento_id AS AGENDAMENTO_ID,
                T.NOME_AMBIENTE,
                P.idgis_ambiente,
                SUM(CASE WHEN LOWER(P.status) = 'enviado' THEN 1 ELSE 0 END) AS QTD_ENVIADO,
                SUM(CASE WHEN P.status = 'pendente_aprovacao' THEN 1 ELSE 0 END) AS QTD_PENDENTE_APROVACAO,
                SUM(CASE WHEN P.status = 'agendado_mkc' THEN 1 ELSE 0 END) AS QTD_AGENDADO_MKC,
                SUM(CASE WHEN LOWER(P.status) = 'pendente' THEN 1 ELSE 0 END) AS QTD_PENDENTE,
                SUM(CASE WHEN LOWER(P.status) = 'negado' THEN 1 ELSE 0 END) AS QTD_NEGADO
            FROM {$envios_table} P
            LEFT JOIN {$users_table} E ON E.ID = P.current_user_id
            LEFT JOIN {$ambiente_table} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
            WHERE {$where_sql}
            GROUP BY
                E.user_nicename,
                T.NOME_AMBIENTE,
                P.fornecedor,
                P.agendamento_id,
                P.idgis_ambiente,
                CAST(P.data_cadastro AS DATE)
            ORDER BY DATA DESC
            LIMIT {$per_page} OFFSET {$offset}
        ";
        
        $rows = $wpdb->get_results($query);
        $totals = $this->fetch_report_status_totals($where_sql);
        $total_records = $this->count_report_grouped_records($where_sql);
        
        wp_send_json_success([
            'data' => $rows,
            'totals' => $totals,
            'total_records' => $total_records,
        ]);
    }
    
    public function handle_get_report_1x1_stats() {
        check_ajax_referer('pc_nonce', 'nonce');
        
        global $wpdb;
        $table_eventos = $wpdb->prefix . 'eventos_envios';
        
        $results = $wpdb->get_results(
            "SELECT carteira, COUNT(*) as total 
             FROM {$table_eventos} 
             WHERE tipo = '1X1' 
             GROUP BY carteira 
             ORDER BY total DESC",
            ARRAY_A
        );
        
        $total_1x1 = 0;
        foreach ($results as $row) {
            $total_1x1 += $row['total'];
        }
        
        wp_send_json_success([
            'total' => $total_1x1,
            'carteiras' => $results
        ]);
    }
    
    public function handle_download_csv_geral() {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão.');
        }
        
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'pc_csv_download')) {
            wp_die('Requisição inválida.');
        }
        
        global $wpdb;
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->prefix . 'users';
        $ambiente_table = 'NOME_AMBIENTE';
        
        $filters = $this->collect_report_filters($_GET);
        $where_sql = $this->build_report_where_sql($filters);
        
        $query = "
            SELECT
                P.id,
                CAST(P.data_cadastro AS DATE) AS data,
                E.display_name AS usuario,
                P.agendamento_id,
                P.fornecedor,
                T.NOME_AMBIENTE AS ambiente,
                P.idgis_ambiente,
                P.telefone,
                P.nome AS nome_cliente,
                P.status,
                P.cpf_cnpj,
                P.idcob_contrato,
                P.data_disparo
            FROM {$envios_table} P
            LEFT JOIN {$users_table} E ON E.ID = P.current_user_id
            LEFT JOIN {$ambiente_table} T ON T.IDGIS_AMBIENTE = P.idgis_ambiente
            WHERE {$where_sql}
            ORDER BY P.data_cadastro DESC
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            wp_die('Nenhum registro encontrado com os filtros aplicados.');
        }
        
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_geral_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $headers = array_keys($results[0]);
        fputcsv($output, $headers, ';');
        
        foreach ($results as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    public function handle_download_csv_agendamento() {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão.');
        }
        
        if (!isset($_REQUEST['agendamento_id']) || empty($_REQUEST['agendamento_id'])) {
            wp_die('Agendamento ID não fornecido.');
        }
        
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'pc_csv_download')) {
            wp_die('Requisição inválida.');
        }
        
        $agendamento_id = sanitize_text_field($_REQUEST['agendamento_id']);
        
        global $wpdb;
        $table_envios = $wpdb->prefix . 'envios_pendentes';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_envios} WHERE agendamento_id = %s ORDER BY id ASC",
            $agendamento_id
        ), ARRAY_A);
        
        if (empty($results)) {
            wp_die('Nenhum registro encontrado para este agendamento.');
        }
        
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="agendamento_' . $agendamento_id . '_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $headers = array_keys($results[0]);
        fputcsv($output, $headers, ';');
        
        foreach ($results as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }

    /**
     * 🚀 OTIMIZAÇÃO: Busca todos os telefones recentes de uma vez
     */
    /**
     * 🚀 OTIMIZADO: Busca telefones recentes com query simples e normalização eficiente
     */
    private function get_recent_phones_batch($envios_table) {
        global $wpdb;
        
        // 🚀 Query simples e rápida - usa índices em data_cadastro e status
        // Limita busca aos últimos 2 dias para reduzir volume
        $sql = "SELECT DISTINCT telefone 
                FROM {$envios_table} 
                WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                  AND status IN ('enviado', 'pendente', 'pendente_aprovacao')
                  AND telefone IS NOT NULL 
                  AND telefone != ''
                LIMIT 100000";
        
        error_log('🔵 Executando query de telefones recentes...');
        $start_time = microtime(true);
        
        $recent_phones = $wpdb->get_col($sql);
        
        $query_time = microtime(true) - $start_time;
        error_log('🔵 Query executada em ' . round($query_time, 2) . 's. Telefones encontrados: ' . count($recent_phones));
        
        if (empty($recent_phones)) {
            return [];
        }
        
        // 🚀 Normalização otimizada em batch usando array_map
        error_log('🔵 Normalizando telefones...');
        $normalize_start = microtime(true);
        
        $phones_map = [];
        $batch_size = 1000;
        $total = count($recent_phones);
        
        // Processa em lotes para não sobrecarregar memória
        for ($i = 0; $i < $total; $i += $batch_size) {
            $batch = array_slice($recent_phones, $i, $batch_size);
            
            foreach ($batch as $phone) {
                // Normalização rápida: remove não numéricos
                $phone_normalized = preg_replace('/[^0-9]/', '', $phone);
                
                // Remove código do país (55) se presente
                if (strlen($phone_normalized) > 11 && substr($phone_normalized, 0, 2) === '55') {
                    $phone_normalized = substr($phone_normalized, 2);
                }
                
                // Só adiciona se tiver tamanho válido (10 ou 11 dígitos)
                if (strlen($phone_normalized) >= 10 && strlen($phone_normalized) <= 11) {
                    $phones_map[$phone_normalized] = true;
                }
            }
        }
        
        $normalize_time = microtime(true) - $normalize_start;
        error_log('🔵 Normalização concluída em ' . round($normalize_time, 2) . 's. Telefones únicos: ' . count($phones_map));
        
        return $phones_map;
    }

    /**
     * 🚀 OTIMIZAÇÃO: Insere múltiplos registros de uma vez
     */
    private function bulk_insert($table, $data_array) {
        global $wpdb;
        
        if (empty($data_array)) {
            return;
        }
        
        // Prepara valores para INSERT múltiplo
        $values = [];
        $placeholders = [];
        
        foreach ($data_array as $data) {
            $values[] = $wpdb->prepare(
                "(%s, %s, %d, %d, %s, %s, %s, %s, %s, %d, %d, %s)",
                $data['telefone'],
                $data['nome'],
                $data['idgis_ambiente'],
                $data['idcob_contrato'],
                $data['cpf_cnpj'],
                $data['mensagem'],
                $data['fornecedor'],
                $data['agendamento_id'],
                $data['status'],
                $data['current_user_id'],
                $data['valido'],
                $data['data_cadastro']
            );
        }
        
        $sql = "INSERT INTO {$table} 
                (telefone, nome, idgis_ambiente, idcob_contrato, cpf_cnpj, mensagem, fornecedor, agendamento_id, status, current_user_id, valido, data_cadastro) 
                VALUES " . implode(', ', $values);
        
        $wpdb->query($sql);
    }

    public function handle_get_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        $current_user_id = get_current_user_id();
        
        // Busca apenas campanhas do usuário logado
        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE criado_por = %d ORDER BY criado_em DESC",
            $current_user_id
        ), ARRAY_A);
        
        wp_send_json_success($campaigns);
    }

    public function handle_delete_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        $current_user_id = get_current_user_id();
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        
        // Verifica se a campanha pertence ao usuário
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND criado_por = %d",
            $id, $current_user_id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Campanha não encontrada ou você não tem permissão para deletá-la.');
            return;
        }
        
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        
        if ($result === false) {
            wp_send_json_error('Erro ao deletar campanha.');
        } else {
            wp_send_json_success('Campanha deletada com sucesso!');
        }
    }

    public function handle_toggle_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        $ativo = intval($_POST['ativo'] ?? 0);
        $current_user_id = get_current_user_id();
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        
        // Verifica se a campanha pertence ao usuário
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND criado_por = %d",
            $id, $current_user_id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Campanha não encontrada ou você não tem permissão para alterá-la.');
            return;
        }
        
        $result = $wpdb->update(
            $table,
            ['ativo' => $ativo],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error('Erro ao atualizar status.');
        } else {
            wp_send_json_success($ativo ? 'Campanha ativada!' : 'Campanha desativada!');
        }
    }

    public function handle_execute_recurring_now() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        $exclude_recent_execution = isset($_POST['exclude_recent_phones']) ? intval($_POST['exclude_recent_phones']) : null;
        $current_user_id = get_current_user_id();
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        
        // Verifica se a campanha pertence ao usuário
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND criado_por = %d",
            $id, $current_user_id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Campanha não encontrada ou você não tem permissão para executá-la.');
            return;
        }
        
        if ($campaign['ativo'] != 1) {
            wp_send_json_error('Esta campanha está desativada. Ative-a antes de executar.');
            return;
        }
        
        // Se foi passado uma opção de exclusão na execução, sobrescreve a config salva
        if ($exclude_recent_execution !== null) {
            $providers_config = json_decode($campaign['providers_config'], true);
            if (!is_array($providers_config)) {
                $providers_config = [];
            }
            $providers_config['exclude_recent_phones'] = $exclude_recent_execution;
            $campaign['providers_config'] = json_encode($providers_config);
        }
        
        // Usa versão otimizada própria para melhor performance
        if (class_exists('Campaign_Manager_Recurring') && class_exists('Campaign_Manager_Filters')) {
            $result = $this->execute_recurring_campaign_optimized($campaign, $exclude_recent_execution);
            
            // Atualiza última execução
            $wpdb->update(
                $table,
                ['ultima_execucao' => current_time('mysql')],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => $result['message'],
                    'records_inserted' => $result['records_inserted'] ?? 0,
                    'records_skipped' => $result['records_skipped'] ?? 0,
                    'exclusion_enabled' => $exclude_recent_execution ?? 1
                ]);
            } else {
                wp_send_json_error($result['message']);
            }
            return;
        }
        
        wp_send_json_error('Campaign Manager não está disponível.');
    }

    public function handle_preview_recurring_count() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        $current_user_id = get_current_user_id();
        $table = $wpdb->prefix . 'cm_recurring_campaigns';
        
        // Verifica se a campanha pertence ao usuário
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND criado_por = %d",
            $id, $current_user_id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Campanha não encontrada.');
            return;
        }
        
        if (!class_exists('Campaign_Manager_Filters')) {
            wp_send_json_error('Campaign Manager não está disponível.');
            return;
        }
        
        $filters = json_decode($campaign['filtros_json'], true);
        if (!is_array($filters)) {
            $filters = [];
        }
        
        $total_count = Campaign_Manager_Filters::count_records($campaign['tabela_origem'], $filters);
        $final_count = $campaign['record_limit'] > 0 ? min($total_count, $campaign['record_limit']) : $total_count;
        
        wp_send_json_success([
            'count' => $final_count,
            'total_available' => $total_count,
            'has_limit' => $campaign['record_limit'] > 0
        ]);
    }

    /**
     * 🚀 SELECT DIRETO: Busca registros filtrados sem overhead do Campaign Manager
     * @param bool $exclude_recent_phones Se true, faz LEFT JOIN para excluir telefones com envios recentes
     */
    private function get_filtered_records_optimized($table_name, $filters, $limit = 0, $exclude_recent_phones = false) {
        global $wpdb;
        
        if (empty($table_name)) {
            return [];
        }
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        
        // Constrói WHERE direto
        $where_clauses = [];
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $column => $filter_data) {
                if (!is_array($filter_data) || empty($filter_data['operator']) || !isset($filter_data['value']) || $filter_data['value'] === '') {
                    continue;
                }
                
                $sanitized_column = esc_sql(str_replace('`', '', $column));
                $operator = strtoupper($filter_data['operator']);
                $value = $filter_data['value'];
                
                if ($operator === 'IN' && is_array($value) && !empty($value)) {
                    $placeholders = implode(', ', array_fill(0, count($value), '%s'));
                    $where_clauses[] = $wpdb->prepare(
                        "t.`{$sanitized_column}` IN ({$placeholders})",
                        $value
                    );
                } elseif (in_array($operator, ['=', '!=', '>', '<', '>=', '<='])) {
                    $where_clauses[] = $wpdb->prepare(
                        "t.`{$sanitized_column}` {$operator} %s",
                        $value
                    );
                }
            }
        }
        
        $where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : ' WHERE 1=1';
        
        $limit_sql = $limit > 0 ? $wpdb->prepare(" LIMIT %d", $limit) : '';
        
        // 🚀 OTIMIZAÇÃO: LEFT JOIN para excluir telefones recentes diretamente na query
        if ($exclude_recent_phones) {
            // Usa LEFT JOIN com WHERE IS NULL - muito mais rápido que NOT EXISTS
            $sql = "SELECT 
                        t.`TELEFONE` as telefone,
                        t.`NOME` as nome,
                        t.`IDGIS_AMBIENTE` as idgis_ambiente,
                        t.`IDCOB_CONTRATO` as idcob_contrato,
                        COALESCE(t.`CPF`, t.`CPF_CNPJ`) as cpf_cnpj
                    FROM `{$table_name}` t
                    LEFT JOIN {$envios_table} c ON (
                        -- Compara telefones (normaliza removendo caracteres não numéricos)
                        REGEXP_REPLACE(c.telefone, '[^0-9]', '') = REGEXP_REPLACE(t.TELEFONE, '[^0-9]', '')
                        OR
                        -- Remove código 55 se presente em ambos
                        (LENGTH(REGEXP_REPLACE(c.telefone, '[^0-9]', '')) > 11 
                         AND SUBSTRING(REGEXP_REPLACE(c.telefone, '[^0-9]', ''), 1, 2) = '55'
                         AND SUBSTRING(REGEXP_REPLACE(c.telefone, '[^0-9]', ''), 3) = REGEXP_REPLACE(t.TELEFONE, '[^0-9]', ''))
                        OR
                        (LENGTH(REGEXP_REPLACE(t.TELEFONE, '[^0-9]', '')) > 11 
                         AND SUBSTRING(REGEXP_REPLACE(t.TELEFONE, '[^0-9]', ''), 1, 2) = '55'
                         AND SUBSTRING(REGEXP_REPLACE(t.TELEFONE, '[^0-9]', ''), 3) = REGEXP_REPLACE(c.telefone, '[^0-9]', ''))
                    )
                    AND CAST(c.data_disparo AS DATE) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) AND CURRENT_DATE
                    AND c.status IN ('enviado', 'pendente', 'pendente_aprovacao')
                    " . $where_sql . "
                    AND c.telefone IS NULL" . $limit_sql;
            
            // Se REGEXP_REPLACE não estiver disponível (MySQL < 8.0), usa versão compatível
            $mysql_version = $wpdb->get_var("SELECT VERSION()");
            if (version_compare($mysql_version, '8.0.0', '<')) {
                // Versão compatível: compara telefones diretamente (pode ter pequenas diferenças de formatação)
                $sql = "SELECT 
                            t.`TELEFONE` as telefone,
                            t.`NOME` as nome,
                            t.`IDGIS_AMBIENTE` as idgis_ambiente,
                            t.`IDCOB_CONTRATO` as idcob_contrato,
                            COALESCE(t.`CPF`, t.`CPF_CNPJ`) as cpf_cnpj
                        FROM `{$table_name}` t
                        LEFT JOIN {$envios_table} c ON (
                            c.telefone = t.TELEFONE
                            OR c.telefone LIKE CONCAT('%', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(t.TELEFONE, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '%')
                            OR t.TELEFONE LIKE CONCAT('%', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '%')
                        )
                        AND CAST(c.data_disparo AS DATE) BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY) AND CURRENT_DATE
                        AND c.status IN ('enviado', 'pendente', 'pendente_aprovacao')
                        " . $where_sql . "
                        AND c.telefone IS NULL" . $limit_sql;
            }
        } else {
            // SELECT direto - busca apenas campos necessários
            $sql = "SELECT 
                        t.`TELEFONE` as telefone,
                        t.`NOME` as nome,
                        t.`IDGIS_AMBIENTE` as idgis_ambiente,
                        t.`IDCOB_CONTRATO` as idcob_contrato,
                        COALESCE(t.`CPF`, t.`CPF_CNPJ`) as cpf_cnpj
                    FROM `{$table_name}` t" . $where_sql . $limit_sql;
        }
        
        $records = $wpdb->get_results($sql, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('🔴 Erro ao buscar registros: ' . $wpdb->last_error);
            error_log('SQL: ' . $sql);
            return [];
        }
        
        // Retorna direto sem normalização desnecessária
        return $records ?: [];
    }

    /**
     * 🚀 VERSÃO OTIMIZADA: Executa campanha recorrente com inserção em lote
     */
    private function execute_recurring_campaign_optimized($campaign, $exclude_recent_execution) {
        global $wpdb;
        
        error_log('🔵 Painel Campanhas - Iniciando execução otimizada de campanha recorrente');
        $start_time = microtime(true);
        
        try {
            // 1. Decodifica configurações
            $filters = json_decode($campaign['filtros_json'], true);
            if (!is_array($filters)) {
                $filters = [];
            }
            
            $providers_config = json_decode($campaign['providers_config'], true);
            
            if (!$providers_config || empty($providers_config['providers'])) {
                return [
                    'success' => false,
                    'message' => 'Configuração de provedores inválida'
                ];
            }
            
            // Usa a opção de exclusão passada ou a configurada
            $exclude_recent_phones = $exclude_recent_execution !== null ? $exclude_recent_execution : 
                                    (isset($providers_config['exclude_recent_phones']) ? intval($providers_config['exclude_recent_phones']) : 1);
            
            // 2. 🚀 OTIMIZADO: Busca registros com SELECT direto + LEFT JOIN para excluir telefones recentes
            error_log('🔵 Buscando registros filtrados (SELECT direto com exclusão de telefones recentes)...');
            $step_start = microtime(true);
            $records = $this->get_filtered_records_optimized(
                $campaign['tabela_origem'],
                $filters,
                $campaign['record_limit'],
                $exclude_recent_phones  // Passa flag para fazer LEFT JOIN
            );
            error_log('🔵 Registros encontrados: ' . count($records) . ' em ' . round(microtime(true) - $step_start, 2) . 's');
            
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum registro encontrado com os filtros aplicados'
                ];
            }
            
            // 3. 🎣 ADICIONA ISCAS ATIVAS (apenas com IDGIS compatível)
            $baits_count = 0;
            if (class_exists('Campaign_Manager_Baits')) {
                $all_baits = Campaign_Manager_Baits::get_active_baits();
                $idgis_found = [];
                
                foreach ($records as $record) {
                    if (!empty($record['idgis_ambiente'])) {
                        $idgis_found[$record['idgis_ambiente']] = true;
                    }
                }
                
                foreach ($all_baits as $bait) {
                    if (isset($idgis_found[$bait['idgis_ambiente']])) {
                        $records[] = [
                            'telefone' => $bait['telefone'],
                            'nome' => $bait['nome'] . ' [ISCA]',
                            'idgis_ambiente' => $bait['idgis_ambiente'],
                            'idcob_contrato' => 0,
                            'cpf_cnpj' => ''
                        ];
                        $baits_count++;
                    }
                }
            }
            
            // 4. 🚀 OTIMIZAÇÃO: Exclusão de telefones recentes já feita no LEFT JOIN da query
            // Não precisa mais buscar telefones separadamente - já vem filtrado!
            
            // 5. Busca template
            $template_post = get_post($campaign['template_id']);
            if (!$template_post) {
                return [
                    'success' => false,
                    'message' => 'Template de mensagem não encontrado'
                ];
            }
            $mensagem_template = $template_post->post_content;
            
            // 6. Distribui registros entre provedores
            $distribution = $this->distribute_records_for_recurring($records, $providers_config);
            
            if (empty($distribution)) {
                return [
                    'success' => false,
                    'message' => 'Erro ao distribuir registros entre provedores'
                ];
            }
            
            // 7. Prepara todos os dados para inserção em lote
            error_log('🔵 Preparando dados para inserção...');
            $prep_start = microtime(true);
            $all_insert_data = [];
            $total_skipped = 0;
            $envios_table = $wpdb->prefix . 'envios_pendentes';
            $current_user_id = get_current_user_id();
            $agendamento_base_id = current_time('YmdHis');
            
            foreach ($distribution as $provider => $provider_records) {
                error_log("🔵 Processando provedor {$provider}: " . count($provider_records) . " registros");
                $prefix = strtoupper(substr($provider, 0, 1));
                $campaign_name_clean = preg_replace('/[^a-zA-Z0-9]/', '', $campaign['nome_campanha']);
                $campaign_name_short = substr($campaign_name_clean, 0, 30);
                $agendamento_id = $prefix . $agendamento_base_id . '_' . $campaign_name_short;
                
                foreach ($provider_records as $record) {
                    // 🚀 Telefones recentes já foram excluídos no LEFT JOIN da query
                    // Não precisa mais verificar aqui!
                    
                    // Aplica mapeamento IDGIS
                    $idgis_original = intval($record['idgis_ambiente'] ?? 0);
                    $idgis_mapeado = $idgis_original;
                    
                    if ($idgis_original > 0 && class_exists('CM_IDGIS_Mapper')) {
                        $idgis_mapeado = CM_IDGIS_Mapper::get_mapped_idgis(
                            $campaign['tabela_origem'],
                            $provider,
                            $idgis_original
                        );
                    }
                    
                    // Prepara mensagem
                    $mensagem_final = $this->replace_placeholders($mensagem_template, $record);
                    
                    $all_insert_data[] = [
                        'telefone' => $telefone_normalizado,
                        'nome' => $record['nome'] ?? '',
                        'idgis_ambiente' => $idgis_mapeado,
                        'idcob_contrato' => intval($record['idcob_contrato'] ?? 0),
                        'cpf_cnpj' => $record['cpf_cnpj'] ?? '',
                        'mensagem' => $mensagem_final,
                        'fornecedor' => $provider,
                        'agendamento_id' => $agendamento_id,
                        'status' => 'pendente_aprovacao',
                        'current_user_id' => $current_user_id,
                        'valido' => 1,
                        'data_cadastro' => current_time('mysql')
                    ];
                }
            }
            
            error_log('🔵 Preparação concluída em ' . round(microtime(true) - $prep_start, 2) . 's. Total: ' . count($all_insert_data) . ' registros');
            
            // 8. 🚀 OTIMIZAÇÃO: Insere em lotes de 500 registros
            $total_inserted = 0;
            if (!empty($all_insert_data)) {
                error_log('🔵 Preparando inserção em lote de ' . count($all_insert_data) . ' registros...');
                $batch_size = 500;
                $batches = array_chunk($all_insert_data, $batch_size);
                error_log('🔵 Total de lotes: ' . count($batches));
                
                foreach ($batches as $batch_index => $batch) {
                    error_log("🔵 Inserindo lote " . ($batch_index + 1) . " de " . count($batches) . " (" . count($batch) . " registros)...");
                    $this->bulk_insert_recurring($envios_table, $batch);
                    $total_inserted += count($batch);
                }
                error_log('🔵 Inserção concluída! Total: ' . $total_inserted);
            }
            
            if ($total_inserted === 0) {
                return [
                    'success' => false,
                    'message' => 'Nenhum registro foi agendado. Verifique os filtros e tente novamente.'
                ];
            }
            
            $skipped_message = '';
            if ($exclude_recent_phones && $total_skipped > 0) {
                $skipped_message = " | ⏭️ {$total_skipped} telefones excluídos (já receberam mensagem recentemente)";
            }
            
            $baits_message = '';
            if ($baits_count > 0) {
                $baits_message = " | 🎣 {$baits_count} iscas";
            }
            
            $duration = microtime(true) - $start_time;
            error_log('🔵 Execução concluída em ' . round($duration, 2) . ' segundos');
            
            return [
                'success' => true,
                'message' => sprintf(
                    'Campanha executada! %d registros agendados em %d provedor(es)%s%s',
                    $total_inserted,
                    count($distribution),
                    $baits_message,
                    $skipped_message
                ),
                'records_inserted' => $total_inserted,
                'records_skipped' => $total_skipped,
                'exclusion_enabled' => $exclude_recent_phones
            ];
            
        } catch (Exception $e) {
            error_log('Painel Campanhas - Erro ao executar template: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Erro ao executar campanha: ' . $e->getMessage()
            ];
        }
    }

    private function extract_phone_for_recurring($record) {
        $phone = $record['telefone'] ?? '';
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
            $phone = substr($phone, 2);
        }
        return $phone;
    }

    private function distribute_records_for_recurring($records, $providers_config) {
        $mode = $providers_config['mode'] ?? 'split';
        $providers = $providers_config['providers'] ?? [];
        $percentages = $providers_config['percentages'] ?? [];
        
        if (empty($providers)) {
            return [];
        }
        
        $distribution = [];
        
        if ($mode === 'all') {
            foreach ($providers as $provider) {
                $distribution[$provider] = $records;
            }
        } else {
            $total_records = count($records);
            $shuffled_records = $records;
            shuffle($shuffled_records);
            
            $start_index = 0;
            
            foreach ($providers as $i => $provider) {
                $percentage = $percentages[$provider] ?? (100 / count($providers));
                $count = (int) ceil(($percentage / 100) * $total_records);
                
                if ($i === count($providers) - 1) {
                    $count = $total_records - $start_index;
                }
                
                $provider_records = array_slice($shuffled_records, $start_index, $count);
                
                if (!empty($provider_records)) {
                    $distribution[$provider] = $provider_records;
                }
                
                $start_index += $count;
                
                if ($start_index >= $total_records) {
                    break;
                }
            }
        }
        
        return $distribution;
    }

    private function bulk_insert_recurring($table, $data_array) {
        global $wpdb;
        
        if (empty($data_array)) {
            return;
        }
        
        $values = [];
        
        foreach ($data_array as $data) {
            $values[] = $wpdb->prepare(
                "(%s, %s, %d, %d, %s, %s, %s, %s, %s, %d, %d, %s)",
                $data['telefone'],
                $data['nome'],
                $data['idgis_ambiente'],
                $data['idcob_contrato'],
                $data['cpf_cnpj'],
                $data['mensagem'],
                $data['fornecedor'],
                $data['agendamento_id'],
                $data['status'],
                $data['current_user_id'],
                $data['valido'],
                $data['data_cadastro']
            );
        }
        
        $sql = "INSERT INTO {$table} 
                (telefone, nome, idgis_ambiente, idcob_contrato, cpf_cnpj, mensagem, fornecedor, agendamento_id, status, current_user_id, valido, data_cadastro) 
                VALUES " . implode(', ', $values);
        
        $wpdb->query($sql);
    }

    // ========== HANDLERS PARA APROVAR CAMPANHAS ==========
    
    public function handle_get_pending_campaigns() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'envios_pendentes';
        $users_table = $wpdb->prefix . 'users';

        $filter_agendamento = sanitize_text_field($_POST['filter_agendamento'] ?? '');
        $filter_fornecedor = sanitize_text_field($_POST['filter_fornecedor'] ?? '');

        $where = ["LOWER(TRIM(t1.status)) = 'pendente_aprovacao'"];
        
        if (!empty($filter_agendamento)) {
            $where[] = $wpdb->prepare("t1.agendamento_id LIKE %s", '%' . $wpdb->esc_like($filter_agendamento) . '%');
        }
        
        if (!empty($filter_fornecedor)) {
            $where[] = $wpdb->prepare("t1.fornecedor LIKE %s", '%' . $wpdb->esc_like($filter_fornecedor) . '%');
        }

        $where_sql = implode(' AND ', $where);

        // Query otimizada: agrupa corretamente e conta todos os registros
        $query = "
            SELECT
                t1.agendamento_id,
                t1.idgis_ambiente,
                t1.fornecedor AS provider,
                t1.status,
                MIN(t1.data_cadastro) AS created_at,
                COUNT(*) AS total_clients,
                MAX(t1.current_user_id) AS current_user_id,
                COALESCE(MAX(u.display_name), 'Usuário Desconhecido') AS scheduled_by
            FROM `{$table}` AS t1
            LEFT JOIN `{$users_table}` AS u ON t1.current_user_id = u.ID
            WHERE {$where_sql}
            GROUP BY t1.agendamento_id, t1.idgis_ambiente, t1.fornecedor
            ORDER BY MIN(t1.data_cadastro) DESC
        ";

        error_log('🔵 [Aprovar Campanhas] Query: ' . $query);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        error_log('🔵 [Aprovar Campanhas] Resultados encontrados: ' . count($results));
        if (!empty($results)) {
            error_log('🔵 [Aprovar Campanhas] Primeiro resultado: ' . print_r($results[0], true));
        }
        
        wp_send_json_success($results ?: []);
    }
    
    private function build_dispatch_url($microservice_url) {
        $base_url = rtrim($microservice_url, '/');
        
        // Remove /api se estiver na URL base (o NestJS não tem prefixo /api por padrão)
        if (substr($base_url, -4) === '/api') {
            $base_url = rtrim($base_url, '/api');
        }
        
        return $base_url . '/campaigns/dispatch';
    }
    
    public function handle_get_microservice_config() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        $microservice_config = get_option('acm_microservice_config', []);
        $microservice_url = $microservice_config['url'] ?? '';
        $microservice_api_key = $microservice_config['api_key'] ?? '';
        $master_api_key = get_option('acm_master_api_key', '');

        // Usa a API key do microserviço, ou fallback para master API key
        $api_key = !empty($microservice_api_key) ? $microservice_api_key : $master_api_key;

        wp_send_json_success([
            'url' => $microservice_url,
            'api_key' => $api_key,
            'dispatch_url' => !empty($microservice_url) ? $this->build_dispatch_url($microservice_url) : ''
        ]);
    }
    
    public function handle_update_campaign_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'envios_pendentes';
        $agendamento_id = sanitize_text_field($_POST['agendamento_id'] ?? '');
        $new_status = sanitize_text_field($_POST['status'] ?? '');

        if (empty($agendamento_id) || empty($new_status)) {
            wp_send_json_error('Parâmetros inválidos');
            return;
        }

        $updated = $wpdb->update(
            $table,
            ['status' => $new_status],
            [
                'agendamento_id' => $agendamento_id,
                'status' => 'pendente_aprovacao'
            ],
            ['%s'],
            ['%s', '%s']
        );

        if ($updated === false) {
            wp_send_json_error('Erro ao atualizar status no banco de dados');
            return;
        }

        wp_send_json_success([
            'message' => 'Status atualizado com sucesso!',
            'agendamento_id' => $agendamento_id,
            'new_status' => $new_status
        ]);
    }
    
    public function handle_approve_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'envios_pendentes';
        $agendamento_id = sanitize_text_field($_POST['agendamento_id'] ?? '');
        $fornecedor = sanitize_text_field($_POST['fornecedor'] ?? '');

        if (empty($agendamento_id)) {
            wp_send_json_error('Agendamento ID é obrigatório');
            return;
        }

        // Busca configuração do microserviço
        $microservice_config = get_option('acm_microservice_config', []);
        $microservice_url = $microservice_config['url'] ?? '';
        $microservice_api_key = $microservice_config['api_key'] ?? '';
        $master_api_key = get_option('acm_master_api_key', '');

        if (empty($microservice_url)) {
            wp_send_json_error('URL do microserviço não configurada. Configure em API Manager.');
            return;
        }

        // Envia para o microserviço
        $api_key = !empty($microservice_api_key) ? $microservice_api_key : $master_api_key;
        
        if (empty($api_key)) {
            wp_send_json_error('API Key não configurada. Configure em API Manager.');
            return;
        }

        // Endpoint correto: /campaigns/dispatch (sem /api, pois não há prefixo global)
        $base_url = rtrim($microservice_url, '/');
        
        // Remove /api se estiver na URL base (o NestJS não tem prefixo /api por padrão)
        if (substr($base_url, -4) === '/api') {
            $base_url = rtrim($base_url, '/api');
        }
        
        $dispatch_url = $base_url . '/campaigns/dispatch';
        
        $payload = [
            'agendamento_id' => $agendamento_id
        ];
        
        error_log('🔵 [Aprovar Campanha] ========================================');
        error_log('🔵 [Aprovar Campanha] URL do Microserviço: ' . $dispatch_url);
        error_log('🔵 [Aprovar Campanha] API Key: ' . substr($api_key, 0, 10) . '...' . substr($api_key, -4));
        error_log('🔵 [Aprovar Campanha] Payload: ' . json_encode($payload, JSON_PRETTY_PRINT));
        error_log('🔵 [Aprovar Campanha] Agendamento ID: ' . $agendamento_id);
        error_log('🔵 [Aprovar Campanha] Fornecedor: ' . $fornecedor);
        
        $start_time = microtime(true);
        
        $response = wp_remote_post($dispatch_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY' => $api_key
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
            'sslverify' => false,
            'blocking' => true,
            'data_format' => 'body'
        ]);

        $elapsed_time = round((microtime(true) - $start_time) * 1000, 2);
        error_log('🔵 [Aprovar Campanha] Tempo de resposta: ' . $elapsed_time . 'ms');

        // Se falhar a comunicação, mantém como pendente_aprovacao
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_code = $response->get_error_code();
            error_log('🔴 [Aprovar Campanha] Erro WP: ' . $error_message);
            error_log('🔴 [Aprovar Campanha] Código do erro: ' . $error_code);
            error_log('🔴 [Aprovar Campanha] Dados do erro: ' . print_r($response->get_error_data(), true));
            wp_send_json_error('Erro ao comunicar com o microserviço: ' . $error_message . ' (Código: ' . $error_code . '). A campanha permanecerá pendente para nova tentativa.');
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        error_log('🔵 [Aprovar Campanha] Status HTTP: ' . $response_code);
        error_log('🔵 [Aprovar Campanha] Headers: ' . print_r($response_headers, true));
        error_log('🔵 [Aprovar Campanha] Body completo: ' . $response_body);

        // Aceita 202 (Accepted) e 200 (OK) como sucesso
        if ($response_code < 200 || $response_code >= 300) {
            error_log('🔴 [Aprovar Campanha] Erro HTTP: ' . $response_code . ' - ' . $response_body);
            $error_msg = 'Microserviço retornou erro (' . $response_code . ')';
            if (!empty($response_body)) {
                try {
                    $error_data = json_decode($response_body, true);
                    if (isset($error_data['message'])) {
                        $error_msg .= ': ' . $error_data['message'];
                    } elseif (isset($error_data['error'])) {
                        $error_msg .= ': ' . $error_data['error'];
                    } else {
                        $error_msg .= ': ' . substr($response_body, 0, 200);
                    }
                } catch (Exception $e) {
                    $error_msg .= ': ' . substr($response_body, 0, 200);
                }
            }
            $error_msg .= '. A campanha permanecerá pendente para nova tentativa.';
            wp_send_json_error($error_msg);
            return;
        }

        // Se sucesso, atualiza status para 'pendente' (será processado pelo microserviço)
        $updated = $wpdb->update(
            $table,
            ['status' => 'pendente'],
            [
                'agendamento_id' => $agendamento_id,
                'status' => 'pendente_aprovacao'
            ],
            ['%s'],
            ['%s', '%s']
        );

        if ($updated === false) {
            error_log('🔴 Erro ao atualizar status no banco');
            wp_send_json_error('Erro ao atualizar status no banco de dados');
            return;
        }

        error_log('🔵 Campanha aprovada e enviada com sucesso!');
        wp_send_json_success([
            'message' => 'Campanha aprovada e enviada ao microserviço com sucesso!',
            'agendamento_id' => $agendamento_id
        ]);
    }
    
    public function handle_deny_campaign() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Acesso negado');
            return;
        }

        check_ajax_referer('pc_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'envios_pendentes';
        $agendamento_id = sanitize_text_field($_POST['agendamento_id'] ?? '');

        if (empty($agendamento_id)) {
            wp_send_json_error('Agendamento ID é obrigatório');
            return;
        }

        $updated = $wpdb->update(
            $table,
            ['status' => 'negado'],
            [
                'agendamento_id' => $agendamento_id,
                'status' => 'pendente_aprovacao'
            ],
            ['%s'],
            ['%s', '%s']
        );

        if ($updated === false) {
            wp_send_json_error('Erro ao atualizar status no banco de dados');
            return;
        }

        wp_send_json_success([
            'message' => 'Campanha negada com sucesso!',
            'agendamento_id' => $agendamento_id
        ]);
    }
    
    public function get_credentials_rest($request) {
        $provider = strtoupper($request->get_param('provider'));
        $env_id = $request->get_param('env_id');
        
        // Log para debug
        error_log('🔵 [REST API] Buscando credenciais: Provider=' . $provider . ', EnvId=' . $env_id);
        
        // Lista de providers que usam credenciais estáticas
        $static_providers = ['RCS', 'CDA', 'SALESFORCE', 'MKC'];
        
        if (in_array($provider, $static_providers)) {
            // Para providers estáticos, ignoramos o envId
            error_log('🔵 [REST API] Provider estático detectado: ' . $provider . ' (envId ignorado)');
            
            // Retorna credenciais estáticas
            $static_credentials = get_option('acm_static_credentials', []);
            
            $credentials = [];
            
            if ($provider === 'RCS') {
                // RCS CDA (CromosApp) - funciona igual ao CDA
                // codigo_equipe = idgis_ambiente (vem dos dados da campanha)
                // codigo_usuario = sempre '1'
                // chave_api = vem das credenciais estáticas
                $chave_api = $static_credentials['rcs_chave_api'] ?? $static_credentials['rcs_token'] ?? '';
                
                error_log('🔵 [REST API] Credenciais RCS encontradas: chave_api=' . (!empty($chave_api) ? 'SIM' : 'NÃO'));
                
                if (empty($chave_api)) {
                    $error_message = 'Credenciais RCS incompletas. Configure a Chave API no API Manager. Acesse /painel/api-manager e preencha o campo "Chave API" na seção "Static Provider Credentials" > "RCS CDA (CromosApp)".';
                    error_log('🔴 [REST API] Credenciais RCS incompletas. Faltando: chave_api');
                    
                    return new WP_Error(
                        'invalid_credentials',
                        $error_message,
                        [
                            'status' => 400,
                            'code' => 'INCOMPLETE_RCS_CREDENTIALS',
                            'missing_fields' => ['chave_api'],
                            'provider' => 'RCS'
                        ]
                    );
                }
                
                // Retorna apenas chave_api e base_url
                // codigo_equipe e codigo_usuario serão definidos no microserviço usando idgis_ambiente e '1'
                $credentials = [
                    'chave_api' => $chave_api,
                    'base_url' => $static_credentials['rcs_base_url'] ?? 'https://cromosapp.com.br/api/importarcs/importarRcsCampanhaAPI',
                ];
                
                error_log('✅ [REST API] Credenciais RCS retornadas com sucesso (codigo_equipe e codigo_usuario serão definidos no microserviço)');
            } elseif ($provider === 'CDA') {
                $credentials = [
                    'api_url' => $static_credentials['cda_api_url'] ?? '',
                    'api_key' => $static_credentials['cda_api_key'] ?? '',
                ];
            } elseif ($provider === 'SALESFORCE') {
                $credentials = [
                    'client_id' => $static_credentials['sf_client_id'] ?? '',
                    'client_secret' => $static_credentials['sf_client_secret'] ?? '',
                    'username' => $static_credentials['sf_username'] ?? '',
                    'password' => $static_credentials['sf_password'] ?? '',
                    'token_url' => $static_credentials['sf_token_url'] ?? 'https://concilig.my.salesforce.com/services/oauth2/token',
                    'api_url' => $static_credentials['sf_api_url'] ?? 'https://concilig.my.salesforce.com/services/data/v59.0/composite/sobjects',
                ];
            } elseif ($provider === 'MKC') {
                $credentials = [
                    'client_id' => $static_credentials['mkc_client_id'] ?? '',
                    'client_secret' => $static_credentials['mkc_client_secret'] ?? '',
                    'token_url' => $static_credentials['mkc_token_url'] ?? '',
                    'api_url' => $static_credentials['mkc_api_url'] ?? '',
                ];
            }
            
            if (empty($credentials) || !$this->has_valid_credentials($credentials)) {
                return new WP_Error('no_credentials', 'Credenciais estáticas não configuradas para ' . $provider, ['status' => 404]);
            }
            
            return rest_ensure_response($credentials);
        } else {
            // Providers dinâmicos (GOSAC, NOAH) - busca credenciais por envId
            global $wpdb;
            $table = $wpdb->prefix . 'api_consumer_credentials';
            
            $query = $wpdb->prepare("
                SELECT credentials
                FROM {$table}
                WHERE provider = %s AND env_id = %s
                LIMIT 1
            ", $provider, $env_id);
            
            $result = $wpdb->get_var($query);
            
            if (empty($result)) {
                return new WP_Error('no_credentials', 'Credenciais não encontradas para ' . $provider . ':' . $env_id, ['status' => 404]);
            }
            
            $credentials = maybe_unserialize($result);
            return rest_ensure_response($credentials);
        }
    }
    
    private function has_valid_credentials($credentials) {
        // Verifica se pelo menos um campo não está vazio
        foreach ($credentials as $value) {
            if (!empty($value)) {
                return true;
            }
        }
        return false;
    }
}

// Inicializa o plugin
function painel_campanhas() {
    return Painel_Campanhas::get_instance();
}

// Inicia após plugins carregados
add_action('plugins_loaded', 'painel_campanhas');

