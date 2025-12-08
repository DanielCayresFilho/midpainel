<?php
/**
 * Gerenciamento de requisições AJAX
 */

if (!defined('ABSPATH')) exit;

class CPF_Campaign_Manager_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_cpf_cm_upload_csv', [$this, 'upload_csv']);
        add_action('wp_ajax_cpf_cm_get_custom_filters', [$this, 'get_custom_filters']);
        add_action('wp_ajax_cpf_cm_preview_count', [$this, 'preview_count']);
        add_action('wp_ajax_cpf_cm_generate_clean_file', [$this, 'generate_clean_file']);
    }
    
    /**
     * Upload e processa CSV
     */
    public function upload_csv() {
        check_ajax_referer('cpf-campaign-nonce', 'nonce');
        
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
                $value = $this->clean_phone($line);
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
        $temp_id = uniqid('cpf_', true);
        $temp_file = CPF_CM_UPLOADS_DIR . $temp_id . '.json';
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
    
    /**
     * Retorna filtros customizados para uma tabela
     */
    public function get_custom_filters() {
        check_ajax_referer('cpf-campaign-nonce', 'nonce');
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        
        if (empty($table_name)) {
            wp_send_json_error('Tabela não especificada');
        }
        
        // Filtros permitidos (você pode customizar aqui)
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
    
    /**
     * Preview da quantidade de registros
     */
    public function preview_count() {
        check_ajax_referer('cpf-campaign-nonce', 'nonce');
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        $match_field = sanitize_text_field($_POST['match_field'] ?? '');
        
        if (empty($table_name) || empty($temp_id)) {
            wp_send_json_error('Dados incompletos');
        }

        $temp_payload = $this->load_temp_payload($temp_id);
        if (empty($temp_payload)) {
            wp_send_json_error('Arquivo temporário não encontrado');
        }

        $values = $temp_payload['values'];
        $match_field = $temp_payload['match_field'] ?? $match_field;

        // Constrói query
        global $wpdb;
        $where_sql = $this->build_where_sql($wpdb, $values, $filters, $match_field);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` {$where_sql}");
        
        wp_send_json_success(['count' => intval($count)]);
    }
    
    /**
     * Gera arquivo limpo para download
     */
    public function generate_clean_file() {
        check_ajax_referer('cpf-campaign-nonce', 'nonce');
        global $wpdb;
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $temp_id = sanitize_text_field($_POST['temp_id'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $filters = json_decode($filters_json, true);
        
        if (empty($table_name) || empty($temp_id)) {
            wp_send_json_error('Dados incompletos');
        }
        
        $temp_payload = $this->load_temp_payload($temp_id);
        if (empty($temp_payload)) {
            wp_send_json_error('Arquivo temporário não encontrado');
        }

        $values = $temp_payload['values'];
        $match_field = $temp_payload['match_field'] ?? 'cpf';

        $records = $this->get_records($wpdb, $table_name, $values, $filters, $match_field);

        if (empty($records)) {
            wp_send_json_error('Nenhum registro encontrado');
        }

        $csv = $this->build_clean_csv($records);
        $filename = 'cpf-campaign-' . current_time('YmdHis') . '.csv';

        wp_send_json_success([
            'file' => base64_encode($csv),
            'filename' => $filename
        ]);
    }

    private function get_records($wpdb, $table_name, $values, $filters, $match_field) {
        $where_sql = $this->build_where_sql($wpdb, $values, $filters, $match_field);

        $sql = "SELECT 
                    `NOME` as nome,
                    `TELEFONE` as telefone,
                    `CPF` as cpf_cnpj,
                    `IDCOB_CONTRATO` as idcob_contrato
                FROM `{$table_name}` {$where_sql}";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    private function build_where_sql($wpdb, $values, $filters, $match_field) {
        $where_clauses = ['1=1'];
        $where_clauses[] = $this->build_match_condition($wpdb, $values, $match_field);

        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $column => $column_values) {
                if (empty($column_values)) {
                    continue;
                }

                $sanitized_column = esc_sql($column);
                $placeholders = implode(',', array_fill(0, count($column_values), '%s'));
                $where_clauses[] = $wpdb->prepare(
                    "`{$sanitized_column}` IN ($placeholders)",
                    $column_values
                );
            }
        }

        return 'WHERE ' . implode(' AND ', $where_clauses);
    }

    private function build_match_condition($wpdb, $values, $match_field) {
        if (empty($values)) {
            return '0=1';
        }

        $placeholders = implode(',', array_fill(0, count($values), '%s'));

        if ('telefone' === $match_field) {
            $normalized_phone = $this->normalize_phone_sql('`TELEFONE`');
            return $wpdb->prepare(
                "{$normalized_phone} IN ($placeholders)",
                $values
            );
        }

        return $wpdb->prepare(
            "REPLACE(REPLACE(REPLACE(`CPF`, '.', ''), '-', ''), '/', '') IN ($placeholders)",
            $values
        );
    }

    private function normalize_phone_sql($column) {
        $expr = $column;
        $chars = ['.', '-', '/', '(', ')', ' ', '+'];
        foreach ($chars as $char) {
            $expr = "REPLACE({$expr}, '{$char}', '')";
        }

        return "CASE WHEN LEFT({$expr}, 2) = '55' THEN SUBSTRING({$expr}, 3) ELSE {$expr} END";
    }

    private function load_temp_payload($temp_id) {
        $temp_file = CPF_CM_UPLOADS_DIR . $temp_id . '.json';
        if (!file_exists($temp_file)) {
            return null;
        }

        $payload = json_decode(file_get_contents($temp_file), true);
        if (empty($payload['values'])) {
            return null;
        }

        return $payload;
    }

    private function build_clean_csv($records) {
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['nome', 'telefone', 'cpf', 'idcob_contrato'], ';');

        foreach ($records as $record) {
            fputcsv($handle, [
                $record['nome'] ?? '',
                $this->clean_phone($record['telefone'] ?? ''),
                $record['cpf_cnpj'] ?? '',
                $record['idcob_contrato'] ?? ''
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function clean_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
            $phone = substr($phone, 2);
        }
        return $phone;
    }
}