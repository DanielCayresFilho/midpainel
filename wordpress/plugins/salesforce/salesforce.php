<?php
/**
 * Plugin Name: Salesforce consumer
 * Description: Consumidor da API da Salesforce com agendamento.
 * Version: 1.1.0
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class WP_SALES_CONSUMER {

    private $table; 

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';
        add_action('rest_api_init', [$this, 'routes']);

        // CORREÇÃO 2: Informa ao WordPress que a função de callback aceita 2 argumentos.
        add_action('disparo_mkc_agendado_hook', [$this, 'run_scheduled_mkc_disparo'], 10, 2);
    }

    public function routes() {
        register_rest_route('salesforce/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods'  => 'POST',
            'callback' => [$this, 'alimenta_service'],
            'permission_callback' => [$this, 'check_api_key'],
        ]);
    }

    public function check_api_key(\WP_REST_Request $request) {
    $master_key = get_option('acm_master_api_key');
    if (empty($master_key)) {
        return new WP_Error('no_master_key', 'Master API Key não configurada.', ['status' => 503]);
    }

    $provided_key = $request->get_header('X-API-KEY');
    if ($provided_key !== $master_key) {
        return new WP_Error('invalid_key', 'API Key inválida.', ['status' => 401]);
    }
    
    return true;
}

    public function alimenta_service(\WP_REST_Request $request)
    {
        // 1. Validação do Parâmetro
        $agendamento_id = $request->get_param('agendamento_id');
        if (empty($agendamento_id)) {
            return new \WP_Error('bad_request', 'ID de agendamento ausente.', ['status' => 400]);
        }

        // 2. Coleta de Dados Locais
        $local_api_url = home_url('/wp-json/agendamentos/v1/pendentes/' . urlencode($agendamento_id));
        $response = wp_remote_get($local_api_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => get_option('acm_master_api_key') 
                ],
                'timeout' => 30
            ]);


        if (is_wp_error($response)) {
            $this->update_db('erro_envio', 'Erro na API de consulta local.', current_time('mysql'), $agendamento_id);
            return new \WP_Error('local_api_error', $response->get_error_message(), ['status' => 500]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $dados = json_decode($response_body, true);

        if ($response_code !== 200 || empty($dados) || !is_array($dados)) {
            $this->update_db('erro_envio', 'Nenhum dado válido foi coletado da API local.', current_time('mysql'), $agendamento_id);
            return new \WP_Error('no_data', 'Nenhum dado válido foi retornado.', ['status' => $response_code === 200 ? 204 : $response_code]);
        }

        // 3. Preparação dos Contatos
        if (!isset($dados[0]['idgis_ambiente'])) {
            $this->update_db('erro_envio', 'Campo idgis_ambiente não encontrado.', current_time('mysql'), $agendamento_id);
            return new \WP_Error('missing_field', 'Campo idgis_ambiente não encontrado.', ['status' => 400]);
        }

        $id_regua = $dados[0]['idgis_ambiente'];
        $operacao_data = $this->get_service_operacao($id_regua);

        if (is_wp_error($operacao_data)) {
            return $operacao_data;
        }

        $contacts = [];
        foreach ($dados as $dado) {
            if (isset($dado['nome'], $dado['telefone'])) {
                // Define CPF_CNPJ padrão se estiver vazio ou null
                $cpf_cnpj = $dado['CPF_CNPJ'] ?? null;
                if (empty($cpf_cnpj)) {
                    $cpf_cnpj = '12312312312';
                }
                
                $contacts[] = [
                    "attributes"  => ["type" => "Contact"],
                    "MobilePhone" => $dado['telefone'],
                    "LastName"    => $dado['nome'],
                    "CPF_CNPJ__c" => $cpf_cnpj,
                    "Operacao__c" => $operacao_data[0],
                    "disparo__c"  => true,
                ];
            }
        }

        if (empty($contacts)) {
            return new \WP_Error('no_contacts_to_send', 'Nenhum contato válido para enviar.', ['status' => 400]);
        }

        $static = Api_Consumer_Manager_V3::get_static_credentials();
        $tokenBody = [
            'grant_type' => 'password',
            'client_id' => $static['sf_client_id'],
            'client_secret' => $static['sf_client_secret'],
            'username' => $static['sf_username'],
            'password' => $static['sf_password']
        ];
        $token_url = $static['sf_token_url'];
        $token_response = wp_remote_post($token_url, [
            'body'    => $tokenBody,
            'timeout' => 30,
        ]);
        if (is_wp_error($token_response)) {
            return new \WP_Error('token_request_failed', $token_response->get_error_message(), ['status' => 500]);
        }
        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_data['access_token'])) {
            return new \WP_Error('token_error', 'Falha ao obter o token de acesso.', ['status' => 401]);
        }
        $access_token = $token_data['access_token'];

        $payload = ["allOrNone" => false, "records"   => $contacts];
        $contact_response = wp_remote_post($static['sf_api_url'], [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$access_token}",
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($contact_response) || wp_remote_retrieve_response_code($contact_response) >= 400) {
            $error_message = is_wp_error($contact_response) ? $contact_response->get_error_message() : wp_remote_retrieve_body($contact_response);
            $this->update_db('erro_salesforce', 'Erro ao enviar contatos: ' . $error_message, current_time('mysql'), $agendamento_id);
            return new \WP_Error('salesforce_post_failed', 'Erro na API da Salesforce.', ['status' => 502]);
        }

        // CORREÇÃO 1: Passa os argumentos como uma lista de valores, sem chaves.
        wp_schedule_single_event(time() + 20 * MINUTE_IN_SECONDS, 'disparo_mkc_agendado_hook', [
            $operacao_data[1], // Argumento 1: url_id
            $agendamento_id,   // Argumento 2: agendamento_id
        ]);

        $this->update_db('agendado_mkc', 'Contatos enviados, disparo final agendado para 20 min.', current_time('mysql'), $agendamento_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Ação principal concluída. O disparo final foi agendado para daqui a 20 minutos.',
        ], 202);
    }
    
    private function get_service_operacao($idgis_ambiente) {
    $credentials = Api_Consumer_Manager_V3::get_provider_credentials('salesforce', $idgis_ambiente);
    
    if (!$credentials) {
        return new WP_Error('INVALID_IDGIS', 'IDGIS Ambiente não encontrado no API Manager', ['status' => 404]);
    }
    
    return [$credentials['operacao'], $credentials['automation_id']];
}
    
    /**
     * CORREÇÃO 3: A função agora recebe os argumentos diretamente, não mais um array.
     */
    public function run_scheduled_mkc_disparo($url_id, $agendamento_id) {
        if (empty($url_id) || empty($agendamento_id)) {
            error_log("FALHA: Cron 'disparo_mkc_agendado_hook' foi chamado sem argumentos válidos.");
            return;
        }

        error_log("SUCESSO: Executando Cron 'disparo_mkc_agendado_hook' para o agendamento ID: " . $agendamento_id);
        $this->mkc_disparo($url_id, $agendamento_id);
    }

    private function mkc_disparo($url_id, $agendamento_id) {
        $static = Api_Consumer_Manager_V3::get_static_credentials();

        $tokenBody = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $static['mkc_client_id'],
            'client_secret' => $static['mkc_client_secret'],
        ];

        $token_response = wp_remote_post($static['mkc_token_url'], [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($tokenBody),
            'timeout' => 30,
        ]);
        if (is_wp_error($token_response)) {
            $this->update_db('erro_mkc', $token_response->get_error_message(), current_time('mysql'), $agendamento_id);
            return;
        }
        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        if (empty($token_data['access_token'])) {
            $this->update_db('erro_mkc', 'Falha ao obter o token de acesso do Marketing Cloud.', current_time('mysql'), $agendamento_id);
            return;
        }
        $access_token = $token_data['access_token'];

        $automation_response = wp_remote_post("{$static['mkc_api_url']}/{$url_id}/actions/runallonce", [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$access_token}",
            ],
            'timeout' => 30,
        ]);

        $agora = current_time('mysql');
        if (is_wp_error($automation_response)) {
            $this->update_db('erro_mkc', $automation_response->get_error_message(), $agora, $agendamento_id);
            return;
        }
        $api_status = wp_remote_retrieve_response_code($automation_response);
        $api_body = wp_remote_retrieve_body($automation_response);
        $status_final = ($api_status >= 200 && $api_status < 300) ? 'enviado' : 'erro_mkc';

        $this->update_db($status_final, $api_body, $agora, $agendamento_id);
    }


    public function update_db($field_status, $field_api, $field_date, $where_filter) {
        global $wpdb;
        if ($field_date instanceof DateTime) {
            $field_date = $field_date->format('Y-m-d H:i:s');
        }
        $table_name = $this->table;
        $result = $wpdb->update(
            $table_name,
            [
                'status'         => $field_status,
                'resposta_api'   => $field_api,
                'data_disparo'   => $field_date
            ],
            ['agendamento_id' => $where_filter],
            ['%s', '%s', '%s'],
            ['%s']
        );
        if ($result === false) {
            error_log("WP_SALES_CONSUMER: Erro ao atualizar banco de dados: " . $wpdb->last_error);
        }
        return $result;
    }
}

if (class_exists('WP_SALES_CONSUMER')) {
    new WP_SALES_CONSUMER();
}