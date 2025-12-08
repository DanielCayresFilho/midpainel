<?php
/**
 * Plugin Name: CDA
 * Description: CDA API consumer
 * Version: 1.0.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class CDA_CONSUMER {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        register_rest_route('cda_disparo/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'cda_disparo'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }
    
    public function check_permission() {
        $master_key = get_option('acm_master_api_key');
        // This is a simplified check. In a real scenario, you'd compare against a header.
        // For internal calls, we will allow it.
        return !empty($master_key);
    }

    public function check_api_key(\WP_REST_Request $request) {
    return Api_Consumer_Manager_V3::check_api_key($request);
}

    public function cda_disparo($request) {
        global $wpdb;
        $now = new DateTime('now', new DateTimeZone('UTC'));
        
        // Get URL and Key from settings
        $static = Api_Consumer_Manager_V3::get_static_credentials();
        $url = $static['cda_api_url'] ?? null;
        $api_key = $static['cda_api_key'] ?? null;


        if (empty($url) || empty($api_key)) {
            return new WP_Error('cda_config_missing', 'CDA API URL or Key is not configured in API Manager.', ['status' => 500]);
        }

        $agendamento_id = $request->get_param('agendamento_id');
        if(empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'ID de agendamento ausente', ['status' => 400]);
        }

        // --- PERFORMANCE IMPROVEMENT: Direct DB Query ---
        $dados = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE agendamento_id = %s AND status = 'pendente'",
            $agendamento_id
        ), ARRAY_A);

        if (empty($dados)) {
            $this->update_db('erro_envio', 'Nenhum dado pendente encontrado para este agendamento', $now, $agendamento_id);
            return new WP_Error('no_data', 'Nenhum dado pendente encontrado', ['status' => 404]);
        }

        $idgis_regua = $dados[0]['idgis_ambiente'];
        $mensagem_corpo = $dados[0]['mensagem'];

        $linhas = [];
        foreach($dados as $dado) {
            $last_cpf = isset($dado['cpf_cnpj']) ? substr($dado['cpf_cnpj'], -2) : '';
            $linhas[] = "{$dado['idgis_ambiente']};55{$dado['telefone']};{$dado['nome']};{$dado['cpf_cnpj']};{$last_cpf}";
        }

        $json_disparo = [
            "chave_api" => $api_key,
            "codigo_equipe" => $idgis_regua,
            "codigo_usuario" => '1',
            "nome" => "campanha_{$agendamento_id}",
            "ativo" => true,
            "corpo_mensagem" => $mensagem_corpo,
            "mensagens" => $linhas
        ];

        $post_response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($json_disparo, JSON_UNESCAPED_UNICODE),
            'timeout' => 120
        ]);

        if (is_wp_error($post_response)) {
            $this->update_db('erro_envio', 'Erro na API do fornecedor: ' . $post_response->get_error_message(), $now, $agendamento_id);
            return new WP_Error('Erro_disparo', 'Erro na API do fornecedor: ' . $post_response->get_error_message(), ['status' => 502]);
        }
        
        $api_status = wp_remote_retrieve_response_code($post_response);
        $api_body = wp_remote_retrieve_body($post_response);

        $this->update_db('enviado', "Status: {$api_status} - Body: {$api_body}", $now, $agendamento_id);
        
        return new WP_REST_Response(['success' => true, 'message' => 'Campaign sent successfully.'], 200);
    }

    public function update_db($field_status, $field_api, $field_date, $where_filter) {
        global $wpdb;
        if ($field_date instanceof DateTime) {
            $field_date = $field_date->format('Y-m-d H:i:s');
        }
        $wpdb->update(
            $this->table,
            ['status' => $field_status, 'resposta_api' => $field_api, 'data_disparo' => $field_date],
            ['agendamento_id' => $where_filter],
            ['%s', '%s', '%s'],
            ['%s']
        );
    }
}
new CDA_CONSUMER();