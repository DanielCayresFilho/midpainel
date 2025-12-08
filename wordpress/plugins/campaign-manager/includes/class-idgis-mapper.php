<?php
/**
 * IDGIS Mapper - Mapeia IDGIS entre diferentes provedores
 * Permite que o mesmo IDGIS da tabela seja convertido para IDs diferentes em cada provedor
 */

if (!defined('ABSPATH')) exit;

class CM_IDGIS_Mapper {
    
    private $table_mappings;
    
    public function __construct() {
        global $wpdb;
        $this->table_mappings = $wpdb->prefix . 'cm_idgis_mappings';
        
        // Registra menu
        add_action('admin_menu', [$this, 'register_menu']);
        
        // Carrega assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Handlers AJAX
        add_action('wp_ajax_cm_idgis_save_mapping', [$this, 'save_mapping']);
        add_action('wp_ajax_cm_idgis_delete_mapping', [$this, 'delete_mapping']);
        add_action('wp_ajax_cm_idgis_get_mappings', [$this, 'get_mappings']);
        add_action('wp_ajax_cm_idgis_get_idgis_from_table', [$this, 'get_idgis_from_table']);
    }
    
    /**
     * Cria tabela de mapeamentos na ativação
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cm_idgis_mappings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tabela_origem varchar(150) NOT NULL,
            provedor_destino varchar(100) NOT NULL,
            idgis_ambiente_original int(11) NOT NULL,
            idgis_ambiente_mapeado int(11) NOT NULL,
            ativo tinyint(1) DEFAULT 1,
            criado_em datetime DEFAULT CURRENT_TIMESTAMP,
            atualizado_em datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (tabela_origem, provedor_destino, idgis_ambiente_original)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Registra página no menu
     */
    public function register_menu() {
        add_submenu_page(
            'create-campaign',
            'Mapeamento IDGIS',
            'Mapeamento IDGIS',
            'edit_posts',  // ✅ Mudado de 'manage_options' para 'edit_posts'
            'cm-idgis-mapper',
            [$this, 'render_page']
        );
    }
    
    /**
     * Carrega scripts
     */
    public function enqueue_scripts($hook) {
        if ('campanhas_page_cm-idgis-mapper' !== $hook) return;
        
        wp_enqueue_script(
            'cm-idgis-mapper',
            CM_ASSETS_URL . 'js/idgis-mapper.js',
            ['jquery'],
            CM_VERSION,
            true
        );
        
        wp_localize_script('cm-idgis-mapper', 'cmIdgisAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cm_idgis_mapper_nonce')
        ]);
    }
    
    /**
     * Renderiza página de mapeamento
     */
    public function render_page() {
        global $wpdb;
        $db_prefix = 'VW_BASE';
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$db_prefix}%'", ARRAY_N);
        $providers = Campaign_Manager_Core::get_available_providers();
        
        $table_exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$this->table_mappings}'");
        
        if (isset($_GET['create_table']) && $_GET['create_table'] === '1') {
            self::create_table();
            wp_redirect(admin_url('admin.php?page=cm-idgis-mapper'));
            exit;
        }
        ?>
        
        <div class="cm-wrap">
            <div class="cm-header">
                <div class="cm-header-icon">🔀</div>
                <div>
                    <h1>Mapeamento de IDGIS</h1>
                    <p>Configure diferentes IDs para cada provedor</p>
                </div>
            </div>
            
            <?php if (!$table_exists): ?>
            <div class="cm-alert cm-alert-error">
                <strong>⚠️ Tabela não existe!</strong>
                <a href="?page=cm-idgis-mapper&create_table=1" class="cm-btn cm-btn-primary">
                    Criar Tabela Agora
                </a>
            </div>
            <?php endif; ?>
            
            <div id="cm-idgis-message" class="cm-message" style="display:none;"></div>
            
            <!-- Formulário de Adicionar -->
            <div class="cm-card">
                <div class="cm-card-header">
                    <span class="cm-card-icon">➕</span>
                    <div>
                        <h2>Adicionar Mapeamento</h2>
                        <p>Defina IDs diferentes por provedor</p>
                    </div>
                </div>
                <div class="cm-card-body">
                    <form id="cm-idgis-form">
                        <div class="cm-form-grid">
                            <div class="cm-form-group">
                                <label>📊 Tabela de Origem</label>
                                <select id="tabela_origem" name="tabela_origem" class="cm-select" required>
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?php echo esc_attr($table[0]); ?>">
                                            <?php echo esc_html($table[0]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="cm-form-group">
                                <label>🌐 Provedor de Destino</label>
                                <select id="provedor_destino" name="provedor_destino" class="cm-select">
                                    <option value="">🌟 TODOS (Coringa)</option>
                                    <?php foreach ($providers as $code => $name): ?>
                                        <option value="<?php echo esc_attr($code); ?>">
                                            <?php echo esc_html($name); ?> (apenas)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="cm-form-group">
                                <label>🔢 IDGIS Original (na tabela)</label>
                                <select id="idgis_original" name="idgis_original" class="cm-select" required disabled>
                                    <option value="">-- Selecione a tabela primeiro --</option>
                                </select>
                            </div>
                            
                            <div class="cm-form-group">
                                <label>✨ IDGIS Mapeado (no provedor)</label>
                                <input type="number" id="idgis_mapeado" name="idgis_mapeado" 
                                       class="cm-input" placeholder="Ex: 365" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="cm-btn cm-btn-primary" <?php echo !$table_exists ? 'disabled' : ''; ?>>
                            ➕ Adicionar Mapeamento
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
                    <div id="mappings-loading">⏳ Carregando...</div>
                    <div id="mappings-table" style="display:none;">
                        <table class="cm-table">
                            <thead>
                                <tr>
                                    <th>Tabela</th>
                                    <th>Provedor</th>
                                    <th>IDGIS Original</th>
                                    <th>IDGIS Mapeado</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="mappings-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .cm-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .cm-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .cm-table th {
                background: var(--cm-gray-50);
                padding: 12px;
                text-align: left;
                font-weight: 700;
                border-bottom: 2px solid var(--cm-gray-200);
            }
            
            .cm-table td {
                padding: 12px;
                border-bottom: 1px solid var(--cm-gray-100);
            }
            
            .cm-table tr:hover {
                background: var(--cm-gray-50);
            }
            
            .cm-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
            }
            
            .cm-badge-success {
                background: #d1fae5;
                color: #065f46;
            }
            
            .cm-badge-danger {
                background: #fee2e2;
                color: #991b1b;
            }
            
            .cm-badge-primary {
                background: #dbeafe;
                color: #1e40af;
            }
            
            .cm-alert {
                padding: 15px;
                border-radius: var(--cm-radius);
                margin-bottom: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .cm-alert-error {
                background: #fee2e2;
                border: 2px solid #ef4444;
                color: #991b1b;
            }
            
            .cm-btn-danger {
                background: #fee2e2;
                color: #991b1b;
                padding: 6px 12px;
                border-radius: 8px;
                border: none;
                cursor: pointer;
                font-weight: 600;
            }
            
            .cm-btn-danger:hover {
                background: #fecaca;
            }
        </style>
        <?php
    }
    
    /**
     * Busca IDGIS disponíveis de uma tabela
     */
    public function get_idgis_from_table() {
        check_ajax_referer('cm_idgis_mapper_nonce', 'nonce');
        
        global $wpdb;
        $table_name = isset($_POST['table_name']) ? sanitize_text_field($_POST['table_name']) : '';
        
        if (empty($table_name)) {
            wp_send_json_error('Nome da tabela não fornecido.');
        }
        
        $idgis_list = $wpdb->get_col(
            "SELECT DISTINCT `IDGIS_AMBIENTE` 
             FROM `{$table_name}` 
             WHERE `IDGIS_AMBIENTE` IS NOT NULL 
             AND `IDGIS_AMBIENTE` != 0
             AND `IDGIS_AMBIENTE` != ''
             ORDER BY `IDGIS_AMBIENTE` ASC"
        );
        
        if (empty($idgis_list)) {
            wp_send_json_error('Nenhum IDGIS válido encontrado.');
        }
        
        wp_send_json_success(array_values($idgis_list));
    }
    
    /**
     * Salva um mapeamento
     */
    public function save_mapping() {
        check_ajax_referer('cm_idgis_mapper_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }

        $tabela_origem = sanitize_text_field($_POST['tabela_origem'] ?? '');
        $provedor_destino = sanitize_text_field($_POST['provedor_destino'] ?? '');
        $idgis_original = intval($_POST['idgis_original'] ?? 0);
        $idgis_mapeado = intval($_POST['idgis_mapeado'] ?? 0);

        if (empty($tabela_origem) || $idgis_original <= 0 || $idgis_mapeado <= 0) {
            wp_send_json_error('Dados incompletos.');
        }

        // Se provedor vazio, é coringa (*)
        if (empty($provedor_destino)) {
            $provedor_destino = '*';
        }

        global $wpdb;
        
        // Verifica se já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_mappings} 
             WHERE tabela_origem = %s 
             AND provedor_destino = %s 
             AND idgis_ambiente_original = %d",
            $tabela_origem,
            $provedor_destino,
            $idgis_original
        ));
        
        if ($existing) {
            // Atualiza
            $result = $wpdb->update(
                $this->table_mappings,
                ['idgis_ambiente_mapeado' => $idgis_mapeado, 'ativo' => 1],
                ['id' => $existing],
                ['%d', '%d'],
                ['%d']
            );
            $message = 'Mapeamento atualizado!';
        } else {
            // Insere
            $result = $wpdb->insert($this->table_mappings, [
                'tabela_origem' => $tabela_origem,
                'provedor_destino' => $provedor_destino,
                'idgis_ambiente_original' => $idgis_original,
                'idgis_ambiente_mapeado' => $idgis_mapeado,
                'ativo' => 1
            ], ['%s', '%s', '%d', '%d', '%d']);
            $message = 'Mapeamento criado!';
        }

        if ($result === false) {
            wp_send_json_error('Erro ao salvar: ' . $wpdb->last_error);
        }

        wp_send_json_success($message);
    }
    
    /**
     * Lista mapeamentos
     */
    public function get_mappings() {
        check_ajax_referer('cm_idgis_mapper_nonce', 'nonce');
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_mappings} ORDER BY criado_em DESC",
            ARRAY_A
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * Deleta um mapeamento
     */
    public function delete_mapping() {
        check_ajax_referer('cm_idgis_mapper_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }

        global $wpdb;
        $result = $wpdb->delete($this->table_mappings, ['id' => $id], ['%d']);

        if ($result === false) {
            wp_send_json_error('Erro ao deletar.');
        }

        wp_send_json_success('Mapeamento deletado!');
    }
    
    /**
     * 🎯 MÉTODO ESTÁTICO: Busca IDGIS mapeado
     * Usado pelos consumers para converter IDGIS
     */
    public static function get_mapped_idgis($tabela_origem, $provedor_destino, $idgis_original) {
    global $wpdb;
    $table = $wpdb->prefix . 'cm_idgis_mappings';
    
    // Validação de entrada
    if (empty($tabela_origem)) {
        error_log("⚠️ IDGIS Mapper - Tabela origem vazia");
        return intval($idgis_original);
    }
    
    $idgis_original = intval($idgis_original);
    
    if ($idgis_original <= 0) {
        error_log("⚠️ IDGIS Mapper - IDGIS inválido: {$idgis_original}");
        return 0;
    }
    
    // Log detalhado de entrada
    error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    error_log("🔍 IDGIS Mapper - INÍCIO");
    error_log("📋 Tabela: {$tabela_origem}");
    error_log("🌐 Provedor: {$provedor_destino}");
    error_log("🔢 IDGIS Original: {$idgis_original}");
    
    // ========================================
    // TENTATIVA 1: Mapeamento específico do provedor
    // ========================================
    if (!empty($provedor_destino)) {
        $sql_especifico = $wpdb->prepare(
            "SELECT idgis_ambiente_mapeado 
             FROM {$table} 
             WHERE tabela_origem = %s 
             AND provedor_destino = %s 
             AND idgis_ambiente_original = %d 
             AND ativo = 1
             LIMIT 1",
            $tabela_origem,
            $provedor_destino,
            $idgis_original
        );
        
        error_log("📝 Query Específica:");
        error_log($sql_especifico);
        
        $mapped = $wpdb->get_var($sql_especifico);
        
        if ($mapped) {
            $mapped = intval($mapped);
            error_log("✅ Mapeamento específico encontrado: {$idgis_original} → {$mapped}");
            error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            return $mapped;
        }
        
        error_log("⚠️ Nenhum mapeamento específico encontrado");
    }
    
    // ========================================
    // TENTATIVA 2: Mapeamento coringa (*)
    // ========================================
    $sql_coringa = $wpdb->prepare(
        "SELECT idgis_ambiente_mapeado 
         FROM {$table} 
         WHERE tabela_origem = %s 
         AND (provedor_destino = '*' OR provedor_destino = '' OR provedor_destino IS NULL) 
         AND idgis_ambiente_original = %d 
         AND ativo = 1
         LIMIT 1",
        $tabela_origem,
        $idgis_original
    );
    
    error_log("📝 Query Coringa:");
    error_log($sql_coringa);
    
    $mapped = $wpdb->get_var($sql_coringa);
    
    if ($mapped) {
        $mapped = intval($mapped);
        error_log("✅ Mapeamento coringa encontrado: {$idgis_original} → {$mapped}");
        error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        return $mapped;
    }
    
    error_log("⚠️ Nenhum mapeamento coringa encontrado");
    
    // ========================================
    // TENTATIVA 3: Debug - Verificar se existe ALGUM mapeamento
    // ========================================
    $debug_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$table} 
         WHERE tabela_origem = %s 
         AND idgis_ambiente_original = %d",
        $tabela_origem,
        $idgis_original
    ));
    
    if ($debug_count > 0) {
        error_log("⚠️ DEBUG: Existem {$debug_count} mapeamento(s) cadastrado(s), mas todos estão inativos ou com provedor diferente");
        
        // Mostra os mapeamentos existentes
        $debug_mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, provedor_destino, idgis_ambiente_mapeado, ativo 
             FROM {$table} 
             WHERE tabela_origem = %s 
             AND idgis_ambiente_original = %d",
            $tabela_origem,
            $idgis_original
        ), ARRAY_A);
        
        foreach ($debug_mappings as $map) {
            error_log("   ID: {$map['id']} | Provedor: '{$map['provedor_destino']}' | Mapeado: {$map['idgis_ambiente_mapeado']} | Ativo: {$map['ativo']}");
        }
    } else {
        error_log("⚠️ DEBUG: Nenhum mapeamento cadastrado para esta combinação");
        error_log("   Cadastre em: Campanhas → Mapeamento IDGIS");
    }
    
    // ========================================
    // SEM MAPEAMENTO: Retorna o original
    // ========================================
    error_log("❌ Usando IDGIS original: {$idgis_original}");
    error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    
    return $idgis_original;
}
}

// Inicializa
new CM_IDGIS_Mapper();