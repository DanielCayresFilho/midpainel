<?php
/**
 * Plugin Name: CSV Export - Envios Pendentes
 * Description: Endpoint para exportar envios pendentes do dia anterior em CSV
 * Version: 1.0
 */

// Define sua API Key aqui (troque por uma chave segura)
define('CSV_EXPORT_API_KEY', '5f4b5f554f9058134c1c6751ebbc325ecc49ae71f135e5a5176d7ed6e39dec23372e57b358ad94a5');

/**
 * Registra o endpoint REST API
 */
add_action('rest_api_init', function () {
    register_rest_route('envios/v1', '/export-csv', array(
        'methods' => 'GET',
        'callback' => 'export_envios_pendentes_csv',
        'permission_callback' => 'validate_api_key'
    ));
});

/**
 * Valida a API Key enviada no header
 */
function validate_api_key($request) {
    $api_key = $request->get_header('X-API-Key');
    
    if (empty($api_key)) {
        return new WP_Error(
            'missing_api_key',
            'API Key não fornecida',
            array('status' => 401)
        );
    }
    
    if ($api_key !== CSV_EXPORT_API_KEY) {
        return new WP_Error(
            'invalid_api_key',
            'API Key inválida',
            array('status' => 403)
        );
    }
    
    return true;
}

/**
 * Função principal que gera e retorna o CSV
 */
function export_envios_pendentes_csv($request) {
    global $wpdb;
    
    // Nome da tabela (ajuste o prefixo se necessário)
    $table_name = $wpdb->prefix . 'envios_pendentes';
    
    // Query para buscar dados do dia anterior
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE CAST(data_cadastro AS DATE) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
    );
    
    // Executa a query
    $results = $wpdb->get_results($query, ARRAY_A);
    
    // Verifica se há dados
    if (empty($results)) {
        return new WP_Error(
            'no_data',
            'Nenhum registro encontrado para o dia anterior',
            array('status' => 404)
        );
    }
    
    // Gera o conteúdo CSV
    $csv_content = generate_csv_content($results);
    
    // Define o nome do arquivo com a data
    $filename = 'envios_pendentes_' . date('Y-m-d', strtotime('-1 day')) . '.csv';
    
    // Retorna a resposta com headers apropriados para download
    $response = new WP_REST_Response($csv_content);
    $response->set_headers(array(
        'Content-Type' => 'text/csv; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ));
    
    return $response;
}

/**
 * Gera o conteúdo do CSV a partir dos dados
 */
function generate_csv_content($data) {
    // Inicia o buffer de saída
    ob_start();
    $output = fopen('php://output', 'w');
    
    // Adiciona BOM para UTF-8 (ajuda com acentos no Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Adiciona o cabeçalho (nomes das colunas)
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]), ';');
        
        // Adiciona as linhas de dados
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
    }
    
    fclose($output);
    
    // Retorna o conteúdo do buffer
    return ob_get_clean();
}

/**
 * Função alternativa: Download direto sem usar REST API
 * Adicione ?action=export_csv_envios&apikey=SUA_CHAVE na URL
 */
add_action('template_redirect', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'export_csv_envios') {
        
        // Valida API Key
        if (!isset($_GET['apikey']) || $_GET['apikey'] !== CSV_EXPORT_API_KEY) {
            wp_die('API Key inválida', 'Erro de Autenticação', array('response' => 403));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'envios_pendentes';
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE CAST(data_cadastro AS DATE) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            wp_die('Nenhum registro encontrado', 'Sem Dados', array('response' => 404));
        }
        
        // Headers para download
        $filename = 'envios_pendentes_' . date('Y-m-d', strtotime('-1 day')) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        
        fputcsv($output, array_keys($results[0]), ';');
        foreach ($results as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
});