<?php
/**
 * Classe principal do CPF Campaign Manager
 */

if (!defined('ABSPATH')) exit;

class CPF_Campaign_Manager_Core {
    
    private $db_prefix = 'VW_BASE';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function register_menu() {
        add_menu_page(
            'Campanha por CPF',
            'CPF Campaigns',
            'edit_posts',
            'cpf-campaign',
            [$this, 'render_page'],
            'dashicons-media-spreadsheet',
            27
        );
    }
    
    public function enqueue_assets($hook) {
        if ('toplevel_page_cpf-campaign' !== $hook) return;
        
        wp_enqueue_style(
            'cpf-cm-styles',
            CPF_CM_ASSETS_URL . 'css/cpf-campaign.css',
            [],
            CPF_CM_VERSION
        );
        
        wp_enqueue_script(
            'cpf-cm-scripts',
            CPF_CM_ASSETS_URL . 'js/cpf-campaign.js',
            ['jquery'],
            CPF_CM_VERSION,
            true
        );
        
        wp_localize_script('cpf-cm-scripts', 'cpfCmAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpf-campaign-nonce')
        ]);
    }
    
    public function render_page() {
        global $wpdb;
        
        // Busca tabelas
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$this->db_prefix}%'", ARRAY_N);
        
        include CPF_CM_TEMPLATES_DIR . 'cpf-campaign-page.php';
    }
}