<?php
/**
 * Plugin Name: Campaign Manager Pro
 * Description: Sistema modular de gerenciamento de campanhas
 * Version: 3.0.1
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

define('CM_VERSION', '3.0.1');
define('CM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CM_INCLUDES_DIR', CM_PLUGIN_DIR . 'includes/');
define('CM_TEMPLATES_DIR', CM_PLUGIN_DIR . 'templates/');
define('CM_ASSETS_URL', CM_PLUGIN_URL . 'assets/');

/**
 * Carrega dependências
 */
function cm_load_dependencies() {
    $files = [
        'class-campaign-filters.php',
        'class-campaign-ajax.php',
        'class-campaign-recurring.php',
        'class-campaign-baits.php',
        'class-campaign-core.php',
    ];
    
    foreach ($files as $file) {
        $path = CM_INCLUDES_DIR . $file;
        if (file_exists($path)) {
            require_once $path;
        } else {
            error_log("Campaign Manager: Arquivo não encontrado - $file");
        }
    }
    
    // Carrega mappers se existirem (opcionais)
    if (file_exists(CM_INCLUDES_DIR . 'class-idgis-mapper.php')) {
        require_once CM_INCLUDES_DIR . 'class-idgis-mapper.php';
    }
    if (file_exists(CM_INCLUDES_DIR . 'class-robbu-mapper.php')) {
        require_once CM_INCLUDES_DIR . 'class-robbu-mapper.php';
    }
}
add_action('plugins_loaded', 'cm_load_dependencies', 1);

/**
 * Inicializa plugin
 */
function cm_init_plugin() {
    if (class_exists('Campaign_Manager_Core')) {
        new Campaign_Manager_Core();
    } else {
        error_log('Campaign Manager: Classe Campaign_Manager_Core não encontrada');
    }
}
add_action('init', 'cm_init_plugin', 10);

/**
 * Ativação
 */
function cm_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabela de campanhas recorrentes
    $table = $wpdb->prefix . 'cm_recurring_campaigns';
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
    
    // Cria tabelas dos mappers se as classes existirem
    if (class_exists('CM_IDGIS_Mapper')) {
        CM_IDGIS_Mapper::create_table();
    }
    if (class_exists('CM_Robbu_Mapper')) {
        CM_Robbu_Mapper::create_table();
    }
    if (class_exists('Campaign_Manager_Baits')) {
        Campaign_Manager_Baits::create_table();
    }
    
    // Força flush
    flush_rewrite_rules(true);
    update_option('cm_needs_flush', '1');
}
register_activation_hook(__FILE__, 'cm_activate_plugin');

/**
 * Flush adicional
 */
function cm_check_flush() {
    if (get_option('cm_needs_flush') === '1') {
        flush_rewrite_rules(true);
        delete_option('cm_needs_flush');
    }
}
add_action('admin_init', 'cm_check_flush');

/**
 * Desativação
 */
function cm_deactivate_plugin() {
    flush_rewrite_rules(true);
}
register_deactivation_hook(__FILE__, 'cm_deactivate_plugin');