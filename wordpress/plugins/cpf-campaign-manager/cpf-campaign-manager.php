<?php
/**
 * Plugin Name: CPF Campaign Manager
 * Description: Agendamento de campanhas via upload de arquivo CSV de CPFs
 * Version: 1.0.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

define('CPF_CM_VERSION', '1.0.0');
define('CPF_CM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPF_CM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CPF_CM_INCLUDES_DIR', CPF_CM_PLUGIN_DIR . 'includes/');
define('CPF_CM_TEMPLATES_DIR', CPF_CM_PLUGIN_DIR . 'templates/');
define('CPF_CM_ASSETS_URL', CPF_CM_PLUGIN_URL . 'assets/');
define('CPF_CM_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/cpf-campaigns/');

/**
 * Cria diretório de uploads
 */
function cpf_cm_create_uploads_dir() {
    if (!file_exists(CPF_CM_UPLOADS_DIR)) {
        wp_mkdir_p(CPF_CM_UPLOADS_DIR);
        // Protege o diretório
        file_put_contents(CPF_CM_UPLOADS_DIR . '.htaccess', 'deny from all');
    }
}
add_action('init', 'cpf_cm_create_uploads_dir');

/**
 * Carrega dependências
 */
function cpf_cm_load_dependencies() {
    $files = [
        'class-cpf-campaign-core.php',
        'class-cpf-campaign-ajax.php',
    ];
    
    foreach ($files as $file) {
        $path = CPF_CM_INCLUDES_DIR . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
add_action('plugins_loaded', 'cpf_cm_load_dependencies', 1);

/**
 * Inicializa plugin
 */
function cpf_cm_init_plugin() {
    if (class_exists('CPF_Campaign_Manager_Core')) {
        new CPF_Campaign_Manager_Core();
    }
    if (class_exists('CPF_Campaign_Manager_Ajax')) {
        new CPF_Campaign_Manager_Ajax();
    }
}
add_action('init', 'cpf_cm_init_plugin', 10);

/**
 * Ativação
 */
function cpf_cm_activate_plugin() {
    cpf_cm_create_uploads_dir();
    flush_rewrite_rules(true);
}
register_activation_hook(__FILE__, 'cpf_cm_activate_plugin');

/**
 * Desativação
 */
function cpf_cm_deactivate_plugin() {
    flush_rewrite_rules(true);
}
register_deactivation_hook(__FILE__, 'cpf_cm_deactivate_plugin');