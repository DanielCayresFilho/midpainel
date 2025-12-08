<?php
/**
 * Classe principal do Campaign Manager
 */

if (!defined('ABSPATH')) exit;

class Campaign_Manager_Core {
    
    private $db_prefix = 'VW_BASE';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus'], 9);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function register_menus() {
        // Menu principal
        add_menu_page(
            'Criar Campanha',
            'Campanhas',
            'edit_posts',
            'create-campaign',
            [$this, 'render_create_campaign_page'],
            'dashicons-email-alt',
            26
        );
        
        // Submenu: Criar Campanha (remove duplicado)
        add_submenu_page(
            'create-campaign',
            'Criar Campanha',
            'Criar Campanha',
            'edit_posts',
            'create-campaign'
        );
        
        // Submenu: Templates Salvos
        add_submenu_page(
            'create-campaign',
            'Templates Salvos',
            'Templates Salvos',
            'edit_posts',
            'recurring-campaigns',
            [$this, 'render_recurring_page']
        );
    }
    
    public function enqueue_assets($hook) {
        // Só carrega nas páginas do plugin
        if (strpos($hook, 'create-campaign') === false && strpos($hook, 'recurring-campaigns') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'cm-styles',
            CM_ASSETS_URL . 'css/campaign-styles.css',
            [],
            CM_VERSION
        );
        
        // JS
        wp_enqueue_script(
            'cm-scripts',
            CM_ASSETS_URL . 'js/campaign-scripts.js',
            ['jquery'],
            CM_VERSION,
            true
        );
        
        wp_localize_script('cm-scripts', 'cmAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('campaign-manager-nonce')
        ]);
    }
    
    public function render_create_campaign_page() {
        global $wpdb;
        
        try {
            // Busca tabelas
            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$this->db_prefix}%'", ARRAY_N);
            if (!$tables) {
                $tables = [];
            }
            
            // Busca templates
            $message_templates = get_posts([
                'post_type' => 'message_template',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            if (!$message_templates) {
                $message_templates = [];
            }
            
            // Carrega template
            $template_path = CM_TEMPLATES_DIR . 'create-campaign.php';
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                echo '<div class="wrap">';
                echo '<h1>Erro: Template não encontrado</h1>';
                echo '<p>Arquivo esperado: ' . esc_html($template_path) . '</p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="wrap">';
            echo '<h1>Erro ao carregar página</h1>';
            echo '<p>' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    
    public function render_recurring_page() {
        $template_path = CM_TEMPLATES_DIR . 'recurring-campaigns.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap">';
            echo '<h1>Erro: Template não encontrado</h1>';
            echo '<p>Arquivo esperado: ' . esc_html($template_path) . '</p>';
            echo '</div>';
        }
    }
    
    public static function get_available_providers() {
        return [
            'CDA' => 'CDA',
            'GOSAC' => 'GOSAC',
            'NOAH' => 'NOAH',
            'SALESFORCE' => 'Salesforce',
            'RCS' => 'RCS Ótima'
        ];
    }
}