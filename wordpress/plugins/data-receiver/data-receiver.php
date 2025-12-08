<?php
/**
 * Plugin Name: Webhook Receiver Data
 * Description: Receives data via webhook, automatically identifies the structure, and saves it to the correct database table.
 * Version: 1.1.0
 * Author: Daniel Cayres
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Data_Receiver {

    const HOOK_SECRET_KEY = 'ij12Jj3jwuA9GMw0HCnMHs0j6DVPzN'; // Mantenha esta chave em segredo

    public function __construct() {
        // Register REST route
        add_action('rest_api_init', [$this, 'register_routes']);

        // Run table creation on plugin activation
        register_activation_hook(__FILE__, [$this, 'create_tables_on_activation']);
    }

    public function register_routes() {
        register_rest_route('webhook/v1', '/receive/', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_webhook_data'],
            'permission_callback' => [$this, 'check_webhook_secret'],
        ]);
    }

    /**
     * Main function to create all necessary tables on plugin activation.
     */
    public function create_tables_on_activation() {
        $this->create_table_envios();
        $this->create_table_tempos();
        $this->create_table_indicadores();
    }

    private function create_table_envios() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eventos_envios';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE,
            hora TIME,
            fornecedor VARCHAR(100),
            codigoCarteira VARCHAR(50),
            carteira VARCHAR(50),
            segmento VARCHAR(50),
            contrato VARCHAR(50),
            cpf VARCHAR(20),
            status VARCHAR(20),
            numeroSaida VARCHAR(50),
            login VARCHAR(50),
            template VARCHAR(100),
            coringa1 VARCHAR(100),
            coringa2 VARCHAR(100),
            coringa3 VARCHAR(100),
            coringa4 VARCHAR(100),
            telefone VARCHAR(20),
            tipo VARCHAR(20)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_table_tempos() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eventos_tempos';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE,
            hora TIME,
            fornecedor VARCHAR(100),
            codigoCarteira VARCHAR(50),
            carteira VARCHAR(50),
            segmento VARCHAR(50),
            contrato VARCHAR(50),
            cpf VARCHAR(20),
            telefone VARCHAR(20),
            status VARCHAR(20),
            numeroSaida VARCHAR(50),
            login VARCHAR(50),
            evento VARCHAR(100),
            tma VARCHAR(100),
            tmc VARCHAR(100),
            tmpro VARCHAR(100),
            tme VARCHAR(100),
            tmrc VARCHAR(20),
            tmro VARCHAR(20),
            tmf VARCHAR(20),
            protocolo VARCHAR(100)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function create_table_indicadores() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eventos_indicadores';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE,
            hora TIME,
            fornecedor VARCHAR(100),
            codigoCarteira VARCHAR(50),
            carteira VARCHAR(50),
            segmento VARCHAR(50),
            contrato VARCHAR(50),
            cpf VARCHAR(20),
            telefone VARCHAR(20),
            status VARCHAR(20),
            evento VARCHAR(50),
            login VARCHAR(50),
            envio VARCHAR(100),
            falha VARCHAR(100),
            entregue VARCHAR(100),
            lido VARCHAR(100),
            cpc VARCHAR(100),
            cpcProdutivo VARCHAR(20),
            boleto VARCHAR(20),
            tipoAtendimento VARCHAR(20),
            notaNps VARCHAR(100),
            obsNps VARCHAR(100),
            inicioAtendimento VARCHAR(100),
            fimAtendimento VARCHAR(100),
            tma VARCHAR(100),
            protocolo VARCHAR(100)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function check_webhook_secret(WP_REST_Request $request) {
        $header_key = $request->get_header('x-hook-secret');

        if ($header_key && $header_key === self::HOOK_SECRET_KEY) {
            return true;
        }

        error_log('Webhook rejected: invalid secret key.');
        return new WP_Error('forbidden', 'Invalid API Key', ['status' => 403]);
    }

    /**
     * Handles the incoming webhook data, automatically identifies the structure,
     * filters the data to match table columns, and inserts it into the correct table.
     */
    public function handle_webhook_data(WP_REST_Request $request) {
        global $wpdb;
        $data = $request->get_json_params();

        if (empty($data) || !is_array($data)) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid or empty JSON payload.'], 400);
        }

        // 1. Lógica de Identificação baseada em chaves exclusivas
        $table_name = '';

        // Chaves exclusivas para 'indicadores': notaNps, cpcProdutivo, boleto, entregue, lido
        if (isset($data['notaNps']) || isset($data['cpcProdutivo']) || isset($data['boleto']) || isset($data['entregue'])) {
            $table_name = $wpdb->prefix . 'eventos_indicadores';
        }
        // Chaves exclusivas para 'envios': template, coringa1, coringa2, etc.
        elseif (isset($data['template']) || isset($data['coringa1'])) {
            $table_name = $wpdb->prefix . 'eventos_envios';
        }
        // Chaves exclusivas para 'tempos': tmc, tmpro, tme (tma é compartilhado com indicadores)
        elseif (isset($data['tmc']) || isset($data['tmpro']) || isset($data['tme'])) {
            $table_name = $wpdb->prefix . 'eventos_tempos';
        }
        // Se nenhuma estrutura for identificada
        else {
            error_log('Webhook data structure not identified: ' . print_r($data, true));
            return new WP_REST_Response(['status' => 'error', 'message' => 'Could not determine data structure.'], 400);
        }

        // 2. Filtrar os dados para garantir que apenas colunas existentes sejam inseridas
        // Pega o nome de todas as colunas da tabela de destino
        $table_columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);
        
        // Filtra o array de dados, mantendo apenas as chaves que correspondem a uma coluna
        $filtered_data = array_filter(
            $data,
            function ($key) use ($table_columns) {
                return in_array($key, $table_columns);
            },
            ARRAY_FILTER_USE_KEY
        );
        
        if (empty($filtered_data)) {
             error_log('Webhook data did not contain any valid columns for table ' . $table_name . ': ' . print_r($data, true));
             return new WP_REST_Response(['status' => 'error', 'message' => 'No valid data fields for the target table.'], 400);
        }

        // 3. Inserir os dados filtrados de forma segura
        $result = $wpdb->insert($table_name, $filtered_data);

        if ($result === false) {
            error_log('Webhook DB insert error: ' . $wpdb->last_error);
            return new WP_REST_Response(['status' => 'error', 'message' => 'Failed to save data.'], 500);
        }

        return new WP_REST_Response(['status' => 'success', 'message' => "Data identified as '{$table_name}' and saved."], 200);
    }
}

new WP_Data_Receiver();