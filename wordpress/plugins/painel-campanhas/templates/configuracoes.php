<?php
/**
 * Página de Configurações (Apenas Admin)
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die('Acesso negado. Apenas administradores podem acessar esta página.');
}

$current_page = 'configuracoes';
$page_title = 'Configurações';

ob_start();
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Configurações</h2>
    <p class="text-gray-600 dark:text-gray-400 mt-2">Gerencie as configurações do sistema</p>
</div>

<div class="bg-white dark:bg-surface-dark rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 theme-transition">
    <p class="text-gray-600 dark:text-gray-400 text-center py-12">
        <i class="fas fa-cog text-4xl mb-4"></i><br>
        Configurações do sistema serão implementadas em breve
    </p>
</div>
<?php
$content = ob_get_clean();
global $pc_plugin_path;
include $pc_plugin_path . 'templates/base.php';

