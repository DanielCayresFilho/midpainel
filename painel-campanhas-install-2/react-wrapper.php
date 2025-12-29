<?php
/**
 * Wrapper para carregar aplicação React
 * Este arquivo substitui os templates PHP por uma aplicação React completa
 */

if (!defined('ABSPATH')) exit;

// Remove a admin bar do WordPress nas páginas do plugin
add_filter('show_admin_bar', '__return_false');

// Remove também via CSS caso o filtro não funcione
add_action('wp_head', function() {
    echo '<style>#wpadminbar { display: none !important; } html { margin-top: 0 !important; }</style>';
}, 999);

$current_page = get_query_var('pc_page');
if (empty($current_page)) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $home_path = parse_url(home_url(), PHP_URL_PATH);
    if ($home_path && strpos($request_uri, $home_path) === 0) {
        $request_uri = substr($request_uri, strlen($home_path));
    }
    $request_uri = trim(strtok($request_uri, '?'), '/');
    $route_map = [
        'painel/login' => 'login',
        'painel/home' => 'home',
        'painel/campanhas' => 'campanhas',
        'painel/nova-campanha' => 'nova-campanha',
        'painel/campanhas-recorrentes' => 'campanhas-recorrentes',
        'painel/aprovar-campanhas' => 'aprovar-campanhas',
        'painel/mensagens' => 'mensagens',
        'painel/relatorios' => 'relatorios',
        'painel/api-manager' => 'api-manager',
        'painel/configuracoes' => 'configuracoes',
        'painel/controle-custo' => 'controle-custo',
        'painel/controle-custo/cadastro' => 'controle-custo-cadastro',
        'painel/controle-custo/relatorio' => 'controle-custo-relatorio',
        'painel/campanha-arquivo' => 'campanha-arquivo',
    ];
    if (isset($route_map[$request_uri])) {
        $current_page = $route_map[$request_uri];
    }
}

$pc = Painel_Campanhas::get_instance();
$react_dist_path = $pc->plugin_path . 'react/dist/';
$react_dist_url = $pc->plugin_url . 'react/dist/';

// Verifica se o build do React existe
$index_html_path = $react_dist_path . 'index.html';
$assets_path = $react_dist_path . 'assets/';

if (!file_exists($index_html_path) || !is_dir($assets_path)) {
    wp_die('React app não foi construída. Execute "npm run build" na pasta react/.', 'Build não encontrado', ['response' => 500]);
}

// Lê todos os arquivos CSS e JS da pasta assets
$css_files = glob($assets_path . '*.css');
$js_files = glob($assets_path . '*.js');

// Ordena para garantir ordem consistente (index/main primeiro)
usort($js_files, function($a, $b) {
    $a_name = basename($a);
    $b_name = basename($b);
    if (strpos($a_name, 'index') !== false || strpos($a_name, 'main') !== false) return -1;
    if (strpos($b_name, 'index') !== false || strpos($b_name, 'main') !== false) return 1;
    return strcmp($a_name, $b_name);
});

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($current_page ?? 'Painel de Campanhas'); ?> - Painel de Campanhas</title>
    <?php 
    // Remove scripts e estilos padrão do WordPress que não são necessários
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    wp_head(); 
    ?>
    
    <?php
    // Carrega CSS do React
    foreach ($css_files as $css_file) {
        $css_url = $react_dist_url . 'assets/' . basename($css_file);
        echo '<link rel="stylesheet" href="' . esc_url($css_url) . '">' . "\n";
    }
    ?>
</head>
<body <?php body_class(); ?>>
    <div id="root"></div>
    
    <?php
    // Inline script com dados do WordPress para React (antes dos assets React carregarem)
    ?>
    <script>
        window.pcAjax = <?php 
        // admin_url() sempre retorna URL absoluta completa
        $ajax_url = admin_url('admin-ajax.php');
        
        // Se por algum motivo admin_url() não funcionar, usa home_url como fallback
        // Mas admin_url() sempre deve funcionar, então isso é apenas uma segurança extra
        if (empty($ajax_url)) {
            $ajax_url = home_url('/wp-admin/admin-ajax.php');
        }
        
        $ajax_data = [
            'ajaxurl' => esc_url_raw($ajax_url),
            'nonce' => wp_create_nonce('pc_nonce'),
            'cmNonce' => wp_create_nonce('campaign-manager-nonce'),
            'homeUrl' => home_url('/'),
            'restUrl' => rest_url('campaigns/v1/'),
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name ?? '',
                'email' => wp_get_current_user()->user_email ?? '',
                'isAdmin' => current_user_can('manage_options'),
            ],
            'currentPage' => $current_page ?? 'home',
        ];
        
        echo wp_json_encode($ajax_data, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    
    <?php
    // Carrega JavaScript do React
    foreach ($js_files as $js_file) {
        $js_url = $react_dist_url . 'assets/' . basename($js_file);
        echo '<script type="module" src="' . esc_url($js_url) . '"></script>' . "\n";
    }
    ?>
    
    <?php wp_footer(); ?>
</body>
</html>
