<?php
/**
 * Plugin Name: Hook Tables (com Colunas Dinâmicas Inteligentes)
 * Description: Popula tabelas a partir de um webhook, criando tabelas e colunas com tipos de dados detectados automaticamente.
 * Version: 0.5.0
 * Author: Daniel Cayres (modificado por WP Dev Helper)
 */

defined('ABSPATH') || exit;

class WP_hook_tables {
    const HOOK_SECRET_KEY = 'yZ8lncm8S9fgh82lbhRD';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('webhook/v1', '/(?P<table>[^/]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'feed_by_hook'],
            'permission_callback' => [$this, 'check_webhook_secret'],
        ]);
    }

    /**
     * Tenta adivinhar o tipo de coluna MySQL com base em um valor.
     */
    private function get_mysql_type_from_value($value) {
        if (is_int($value) || (is_numeric($value) && strpos($value, '.') === false)) {
            if (abs($value) > 2147483647) {
                return 'BIGINT';
            }
            return 'INT';
        }
        if (is_numeric($value)) {
            return 'DECIMAL(18, 4)';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            return 'DATETIME';
        }
        if (is_string($value) && strlen($value) <= 255) {
            return 'VARCHAR(255)';
        }
        return 'TEXT';
    }

    /**
     * Verifica se uma tabela existe no banco de dados.
     */
    private function table_exists($table) {
        global $wpdb;
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        return $result === $table;
    }

    /**
     * Cria uma nova tabela com base no primeiro registro de dados.
     */
    private function create_table($table, $first_row) {
        global $wpdb;
        
        $columns_sql = ['id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'];
        
        foreach ($first_row as $col => $val) {
            $col_name = sanitize_key($col);
            if (empty($col_name) || $col_name === 'id') {
                continue;
            }
            $col_type = $this->get_mysql_type_from_value($val);
            $columns_sql[] = "`{$col_name}` {$col_type}";
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE `{$table}` (" . implode(', ', $columns_sql) . ") {$charset_collate}";
        
        $wpdb->query($sql);
        
        if ($wpdb->last_error) {
            return new \WP_Error('CREATE_TABLE_ERROR', "Erro ao criar tabela: " . $wpdb->last_error, ['status' => 500]);
        }
        
        return true;
    }

    public function feed_by_hook(\WP_REST_Request $req){
        global $wpdb;
        $table = sanitize_text_field($req->get_param('table'));
        if (empty($table)) {
            return new \WP_Error('INFO_ERROR', 'Nome da tabela não fornecido.', ['status' => 400]);
        }
        $body = $req->get_json_params();
        if (empty($body) || !is_array($body)) {
            return new \WP_Error('NO_DATA', 'Nenhum dado JSON válido recebido.', ['status' => 400]);
        }

        $first_row = $body[0];
        if (!is_array($first_row)) {
            return new \WP_Error('INVALID_DATA', 'Formato de dados inválido.', ['status' => 400]);
        }

        $table_created = false;

        // Verifica se a tabela existe, se não, cria
        if (!$this->table_exists($table)) {
            $result = $this->create_table($table, $first_row);
            if (is_wp_error($result)) {
                return $result;
            }
            $table_created = true;
        }

        $existing_columns = $wpdb->get_col("DESC `{$table}`", 0);
        if ($wpdb->last_error) {
            return new \WP_Error('TABLE_ERROR', "Erro ao acessar a tabela '{$table}'.", ['status' => 500]);
        }

        $incoming_columns = array_map('sanitize_key', array_keys($first_row));
        $new_columns_to_create = array_diff($incoming_columns, $existing_columns);

        if (!empty($new_columns_to_create)) {
            foreach ($new_columns_to_create as $new_col) {
                $sample_value = isset($first_row[$new_col]) ? $first_row[$new_col] : null;
                $col_type = $this->get_mysql_type_from_value($sample_value);
                
                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$new_col}` {$col_type}";
                $wpdb->query($sql);
            }
        }

        $limpar_tabela = $req->get_param('limpar_tabela');
        if ($limpar_tabela === 'true' || $limpar_tabela === '1') {
            $wpdb->query("TRUNCATE TABLE `{$table}`");
        }

        $inserted = 0;
        $errors   = [];
        foreach ($body as $row) {
            if (!is_array($row)) { continue; }
            $data = [];
            foreach ($row as $col => $val) {
                $data[sanitize_key($col)] = $val;
            }
            if (empty($data)) { continue; }
            if ($wpdb->insert($table, $data) === false) {
                $errors[] = $wpdb->last_error;
            } else {
                $inserted++;
            }
        }

        return new \WP_REST_Response([
            'status'                => 'ok',
            'table'                 => $table,
            'tabela_criada'         => $table_created,
            'colunas_adicionadas'   => $new_columns_to_create,
            'registros_inseridos'   => $inserted,
        ], 200);
    }

    public function check_webhook_secret(\WP_REST_Request $req) {
        $header_key = $req->get_header('x-hook-key');
        return $header_key === self::HOOK_SECRET_KEY ? true : new \WP_Error('FORBIDDEN', 'Chave de acesso inválida.', ['status' => 403]);
    }
}
new WP_hook_tables();