<?php
/**
 * Robbu Mapper - Configuração RCS/Robbu
 * Mapeia IDGIS → Queue ID da API Robbu
 */

if (!defined('ABSPATH')) exit;

class CM_Robbu_Mapper {
    
    private $table_queue_map;
    private $bearer_token = 'Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiI0OGJhMWMyYzc5NmI0ZTliOTFlYjJkZmUwY2U5MTgzMyIsInVuaXF1ZV9uYW1lIjoiNTc5MzAiLCJlbWFpbCI6ImRhbmllbGNheXJlcyIsImlkQ2xpZW50ZSI6IjYyMCIsIm5iZiI6MTc1NTg4NDEyNiwiZXhwIjoyMDQzODU1MzI2LCJpYXQiOjE3NTU4ODQxMjYsImlzcyI6InJvYmJ1LmFwaXMiLCJhdWQiOiJodHRwczovL3JvYmJ1LmFjY291bnRzIn0.lUE3CLSPOM9KV0Z7NiO5zgnzKKcQscnxuxu_BbaNCMpU1m2Z6zqQBH5XSIdjt8QOjG6LB4OE3q4s2eMSRsDPfb0gFNly-M_XqHfPZNtF6d3QRIihGZ_203jVrvKWhl2tdvpxi7EfnaThG0H-thexxfGs31UcKC4lvBv7ZWWugyeW25sThcgRfGItHydtG-F2fk4Idj8u1QqnQtFe5F_45w7y4jbNPo_wgYMN40NiFQbae-m5S6Uv_n0CtkTio_XAQ-akYnl7gpjy6i_obrURaFYix3dXonVuhAhRqsvgHyX93qdh0iaXgJm3i_NuK94MCcWUhaQnmj-EwB9wP0iPTQ';

    public function __construct() {
        global $wpdb;
        $this->table_queue_map = $wpdb->prefix . 'cm_robbu_queue_map';
        
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX
        add_action('wp_ajax_cm_robbu_save_queue', [$this, 'save_queue_mapping']);
        add_action('wp_ajax_cm_robbu_delete_queue', [$this, 'delete_queue_mapping']);
        add_action('wp_ajax_cm_robbu_get_queues', [$this, 'get_queue_mappings']);
        add_action('wp_ajax_cm_robbu_fetch_queues_api', [$this, 'fetch_queues_from_api']);
    }

    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cm_robbu_queue_map';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            idgis_ambiente int(11) NOT NULL,
            queue_id varchar(100) NOT NULL,
            queue_name varchar(255) NOT NULL,
            ativo tinyint(1) DEFAULT 1,
            criado_em datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_idgis (idgis_ambiente)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function register_menu() {
        add_submenu_page(
            'create-campaign',
            'Config Robbu/RCS',
            'Config Robbu',
            'edit_posts',  // ✅ Mudado de 'manage_options' para 'edit_posts'
            'cm-robbu-config',
            [$this, 'render_page']
        );
    }

    public function enqueue_scripts($hook) {
        if ('campanhas_page_cm-robbu-config' !== $hook) return;
        
        wp_enqueue_script(
            'cm-robbu-config',
            CM_ASSETS_URL . 'js/robbu-config.js',
            ['jquery'],
            CM_VERSION,
            true
        );
        
        wp_localize_script('cm-robbu-config', 'cmRobbuAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cm_robbu_nonce')
        ]);
    }

    public function render_page() {
        $table_exists = (bool) $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '{$this->table_queue_map}'");
        
        if (isset($_GET['create_table']) && $_GET['create_table'] === '1') {
            self::create_table();
            wp_redirect(admin_url('admin.php?page=cm-robbu-config'));
            exit;
        }
        ?>
        
        <div class="cm-wrap">
            <div class="cm-header">
                <div class="cm-header-icon">📱</div>
                <div>
                    <h1>Configuração Robbu/RCS</h1>
                    <p>Mapeie IDGIS para Queues (Filas) da Robbu</p>
                </div>
            </div>
            
            <?php if (!$table_exists): ?>
            <div class="cm-alert cm-alert-error">
                <strong>⚠️ Tabela não existe!</strong>
                <a href="?page=cm-robbu-config&create_table=1" class="cm-btn cm-btn-primary">
                    Criar Tabela Agora
                </a>
            </div>
            <?php endif; ?>

            <div id="cm-robbu-message" class="cm-message" style="display:none;"></div>

            <!-- Formulário -->
            <div class="cm-card">
                <div class="cm-card-header">
                    <span class="cm-card-icon">➕</span>
                    <div>
                        <h2>Adicionar Mapeamento IDGIS → Queue</h2>
                        <p>Configure filas da Robbu para cada ambiente</p>
                    </div>
                    <button id="fetch-queues-btn" class="cm-btn cm-btn-primary" <?php echo !$table_exists ? 'disabled' : ''; ?>>
                        🔄 Buscar Queues da API
                    </button>
                </div>
                <div class="cm-card-body">
                    <form id="cm-robbu-queue-form">
                        <div class="cm-form-grid">
                            <div class="cm-form-group">
                                <label>🔢 IDGIS Ambiente</label>
                                <input type="number" name="idgis_ambiente" id="idgis_ambiente" 
                                       class="cm-input" placeholder="Ex: 364" required>
                                <small>ID do ambiente que está no banco de dados</small>
                            </div>
                            
                            <div class="cm-form-group">
                                <label>📋 Queue (Fila da Robbu)</label>
                                <select name="queue_select" id="queue_select" class="cm-select" required disabled>
                                    <option value="">-- Clique em "Buscar Queues da API" primeiro --</option>
                                </select>
                                <small>Selecione a fila/queue da Robbu</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="cm-btn cm-btn-primary" <?php echo !$table_exists ? 'disabled' : ''; ?>>
                            ➕ Salvar Mapeamento
                        </button>
                    </form>
                </div>
            </div>

            <!-- Lista de Mapeamentos -->
            <div class="cm-card">
                <div class="cm-card-header">
                    <span class="cm-card-icon">📋</span>
                    <div>
                        <h2>Mapeamentos Ativos</h2>
                        <p>Configurações atuais</p>
                    </div>
                </div>
                <div class="cm-card-body">
                    <div id="queues-loading">⏳ Carregando...</div>
                    <div id="queues-table" style="display:none;">
                        <table class="cm-table">
                            <thead>
                                <tr>
                                    <th>IDGIS Ambiente</th>
                                    <th>Queue ID</th>
                                    <th>Nome da Queue</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="queues-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .cm-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
        </style>
        <?php
    }

    // ==================== CRUD ====================
    
    public function get_queue_mappings() {
        check_ajax_referer('cm_robbu_nonce', 'nonce');
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_queue_map} ORDER BY idgis_ambiente ASC",
            ARRAY_A
        );
        
        wp_send_json_success($results);
    }

    public function save_queue_mapping() {
        check_ajax_referer('cm_robbu_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }

        $idgis = intval($_POST['idgis_ambiente'] ?? 0);
        $queue_data = sanitize_text_field($_POST['queue_data'] ?? '');

        if ($idgis <= 0 || empty($queue_data)) {
            wp_send_json_error('Dados incompletos.');
        }

        // Queue data vem como "ID|NOME" ex: "009|BV_VEICULOS"
        $parts = explode('|', $queue_data);
        if (count($parts) !== 2) {
            wp_send_json_error('Formato de queue inválido.');
        }

        $queue_id = $parts[0];
        $queue_name = $parts[1];

        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_queue_map} WHERE idgis_ambiente = %d",
            $idgis
        ));

        if ($existing) {
            $result = $wpdb->update(
                $this->table_queue_map,
                [
                    'queue_id' => $queue_id,
                    'queue_name' => $queue_name,
                    'ativo' => 1
                ],
                ['id' => $existing],
                ['%s', '%s', '%d'],
                ['%d']
            );
            $message = 'Mapeamento atualizado!';
        } else {
            $result = $wpdb->insert(
                $this->table_queue_map,
                [
                    'idgis_ambiente' => $idgis,
                    'queue_id' => $queue_id,
                    'queue_name' => $queue_name,
                    'ativo' => 1
                ],
                ['%d', '%s', '%s', '%d']
            );
            $message = 'Mapeamento criado!';
        }

        if ($result === false) {
            wp_send_json_error('Erro ao salvar: ' . $wpdb->last_error);
        }

        wp_send_json_success($message);
    }

    public function delete_queue_mapping() {
        check_ajax_referer('cm_robbu_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }

        global $wpdb;
        $result = $wpdb->delete($this->table_queue_map, ['id' => $id], ['%d']);

        if ($result === false) {
            wp_send_json_error('Erro ao deletar.');
        }

        wp_send_json_success('Mapeamento deletado!');
    }

    // ==================== API FETCHERS ====================

    public function fetch_queues_from_api() {
        check_ajax_referer('cm_robbu_nonce', 'nonce');
        
        $response = wp_remote_get('https://api.robbu.global/v1/queues', [
            'headers' => ['Authorization' => $this->bearer_token],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Erro ao conectar: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $queues = json_decode($body, true);
        
        if (!is_array($queues)) {
            wp_send_json_error('Resposta inválida da API.');
        }
        
        wp_send_json_success($queues);
    }

    // ==================== MÉTODO ESTÁTICO ====================
    
    public static function get_queue_by_idgis($idgis_ambiente) {
        global $wpdb;
        $table = $wpdb->prefix . 'cm_robbu_queue_map';
        
        $queue = $wpdb->get_row($wpdb->prepare(
            "SELECT queue_id, queue_name FROM {$table} 
             WHERE idgis_ambiente = %d AND ativo = 1 LIMIT 1",
            $idgis_ambiente
        ), ARRAY_A);
        
        return $queue;
    }
}

new CM_Robbu_Mapper();