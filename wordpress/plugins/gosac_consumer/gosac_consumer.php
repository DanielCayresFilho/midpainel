<?php
/**
 * Plugin Name: Gosac
 * Description: GOSAC API consumer
 * Version: 0.9.2
 * Author: Daniel Cayres
 */

if (!defined('ABSPATH')) exit;

class WP_GOSAC_CONSUME {

    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'envios_pendentes';
        add_action('rest_api_init', [$this, 'routes']);

        // PASSO 1: Adicionar o "gancho" (hook) para a nossa tarefa agendada.
        // O último número (3) indica que a nossa função aceita 3 argumentos.
        add_action('gosac_start_campaign_event', [$this, 'run_scheduled_campaign_start'], 10, 3);
    }

    public function routes() {
        register_rest_route('gosac_disparo/v1', '/(?P<agendamento_id>[^/]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'gosac_disparo'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
    }

    /**
     * PASSO 3: Criar a função que será executada em segundo plano pelo WP-Cron.
     * Ela recebe os parâmetros que passamos no agendamento.
     */
    public function run_scheduled_campaign_start($url_update, $authorization, $agendamento_id) {
        $put_response = wp_remote_request($url_update, [
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $authorization
            ],
            'timeout' => 60
        ]);
        
        // Prepara a data para o banco de dados
        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            error_log('Erro ao criar DateTime no cron: ' . $e->getMessage());
            // Usa o horário do servidor se o objeto falhar
            $now = new DateTime(); 
        }

        if (is_wp_error($put_response)) {
            // Se o início da campanha falhar, atualizamos o status no banco
            $error_message = 'Erro no agendamento (PUT): ' . $put_response->get_error_message();
            $this->update_db('erro_inicio_campanha', $error_message, $now, $agendamento_id);
            error_log($error_message); // Loga o erro para depuração
        } else {
            // Se tudo deu certo, atualizamos o status final
            $this->update_db('enviado', 'Campanha iniciada com sucesso via Cron', $now, $agendamento_id);
        }
    }

    public function gosac_disparo(\WP_REST_Request $request) {
        global $wpdb;

        try {
            $now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            error_log('Erro ao criar DateTime: ' . $e->getMessage());
            return new WP_Error('datetime_error', 'Erro ao processar data/hora', ['status' => 500]);
        }

        $agendamento_id = $request->get_param('agendamento_id');
        if (empty($agendamento_id)) {
            return new WP_Error('invalid_agendamento', 'ID de agendamento ausente', ['status' => 400]);
        }

        // DEBUG: Log inicial
        error_log("GOSAC DEBUG: Iniciando disparo para agendamento: " . $agendamento_id);

        // Busca dados locais com API Key
        $local_api = home_url('/wp-json/agendamentos/v1/pendentes/' . urlencode($agendamento_id));
        $response = wp_remote_get($local_api, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-KEY' => get_option('acm_master_api_key')
            ],
            'timeout' => 30
        ]);

        // DEBUG: Log da resposta local
        error_log("GOSAC DEBUG: Resposta API local - Status: " . wp_remote_retrieve_response_code($response));

        if (is_wp_error($response)) {
            $error_msg = 'Erro na API de consulta: ' . $response->get_error_message();
            error_log("GOSAC DEBUG: " . $error_msg);
            $this->update_db('erro_envio', $error_msg, $now, $agendamento_id);
            return new WP_Error('Erro_Local_API', $error_msg, ['status' => 500]);
        }

        $dados = json_decode(wp_remote_retrieve_body($response), true);
        
        // DEBUG: Verificar dados retornados
        error_log("GOSAC DEBUG: Dados recebidos: " . print_r($dados, true));
        
        if (empty($dados) || !is_array($dados)) {
            error_log("GOSAC DEBUG: Nenhum JSON válido foi coletado");
            $this->update_db('erro_envio', 'Nenhum JSON foi coletado', $now, $agendamento_id);
            return new WP_Error('JSON_Error', 'Nenhum JSON foi coletado', ['status' => 204]);
        }

        if (!isset($dados[0]['idgis_ambiente'])) {
            error_log("GOSAC DEBUG: Campo idgis_ambiente não encontrado nos dados");
            $this->update_db('erro_envio', 'Campo idgis_ambiente não encontrado', $now, $agendamento_id);
            return new WP_Error('missing_field', 'Campo idgis_ambiente não encontrado', ['status' => 400]);
        }

        $id_regua = $dados[0]['idgis_ambiente'];
        
        // DEBUG: Verificar busca de credenciais
        error_log("GOSAC DEBUG: Buscando credenciais para ambiente: " . $id_regua);
        
        // Verificar se a classe do API Manager existe
        if (!class_exists('Api_Consumer_Manager_V3')) {
            error_log("GOSAC DEBUG: ERRO - Classe Api_Consumer_Manager_V3 não encontrada!");
            $this->update_db('erro_envio', 'API Manager V3 não está ativo', $now, $agendamento_id);
            return new WP_Error('missing_api_manager', 'API Manager V3 não encontrado', ['status' => 500]);
        }
        
        // Buscar credenciais diretamente para debug
        $credentials = Api_Consumer_Manager_V3::get_provider_credentials('gosac', $id_regua);
        error_log("GOSAC DEBUG: Credenciais encontradas: " . print_r($credentials, true));
        
        $url_infos = $this->wp_url_gosac($id_regua);

        if (is_wp_error($url_infos)) {
            error_log("GOSAC DEBUG: Erro ao buscar URL/credenciais: " . $url_infos->get_error_message());
            $this->update_db('erro_envio', 'Credenciais não encontradas: ' . $url_infos->get_error_message(), $now, $agendamento_id);
            return $url_infos;
        }
        
        // DEBUG: Verificar URLs obtidas
        error_log("GOSAC DEBUG: URL obtida: " . $url_infos[0]);
        error_log("GOSAC DEBUG: Token obtido: " . substr($url_infos[1], 0, 20) . "...");
        
        // Montar payload
        $mensagem = isset($dados[0]['mensagem']) ? $dados[0]['mensagem'] : 'Olá';
        $campanha = $agendamento_id;

        $contacts = [];
        if (!empty($dados)) {
            foreach ($dados as $dado) {
                if (isset($dado['nome']) && isset($dado['telefone'])) {
                    $contacts[] = [
                        'name' => $dado['nome'],
                        'number' => $dado['telefone'],
                        'hasWhatsapp' => true
                    ];
                }
            }
        }

        // DEBUG: Verificar contatos
        error_log("GOSAC DEBUG: Total de contatos preparados: " . count($contacts));

        $json_disparo = [
            "name"           => sprintf('%s_%s', $campanha, $now->format('Y-m-d_H-i-s')),
            "message"        => (string) $mensagem,
            "kind"           => "whats",
            "connectionId"   => null,
            "contacts"       => $contacts,
            "defaultQueueId" => 1,
            "initialMinutes" => 480,
            "endMinutes"     => 1140,
            "customProps"    => [],
            "scheduled"      => false,
            "scheduledAt"    => $now->format('Y-m-d\TH:i:s\Z'),
            "speed"          => "low",
            "tagId"          => 0,
            "templateId"     => null
        ];

        // DEBUG: Log do payload
        error_log("GOSAC DEBUG: Payload preparado: " . wp_json_encode($json_disparo, JSON_UNESCAPED_UNICODE));

        $post_response = wp_remote_post($url_infos[0], [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0',
                'Authorization' => $url_infos[1]
            ],
            'body' => wp_json_encode($json_disparo, JSON_UNESCAPED_UNICODE),
            'timeout' => 30
        ]);

        if (is_wp_error($post_response)) {
            $error_msg = 'Erro na API do fornecedor: ' . $post_response->get_error_message() . ' | url:' . $url_infos[0];
            error_log("GOSAC DEBUG: " . $error_msg);
            $this->update_db('erro_envio', $error_msg, $now, $agendamento_id);
            return new WP_Error('erro_disparo', $error_msg, ['status' => 502]);
        }

        // DEBUG: Analisar resposta da API GOSAC
        $api_status = wp_remote_retrieve_response_code($post_response);
        $responde_body = wp_remote_retrieve_body($post_response);
        
        error_log("GOSAC DEBUG: API Status: " . $api_status);
        error_log("GOSAC DEBUG: API Response Body: " . $responde_body);
        
        // Salvar resposta no banco para análise
        $this->update_db('debug_response', "Status: {$api_status} | Response: " . substr($responde_body, 0, 500), $now, $agendamento_id);

        // Verificar se API retornou erro HTTP
        if ($api_status < 200 || $api_status >= 300) {
            $error_msg = "API GOSAC erro HTTP {$api_status}: {$responde_body}";
            error_log("GOSAC DEBUG: " . $error_msg);
            $this->update_db('erro_envio', $error_msg, $now, $agendamento_id);
            return new WP_Error('api_error', "GOSAC API erro: {$api_status}", ['status' => 502]);
        }

        $json_body = json_decode($responde_body, true);
        error_log("GOSAC DEBUG: JSON decodificado: " . print_r($json_body, true));

        // Tentar diferentes campos para o ID da campanha
        $id_campanha = $json_body['id'] ?? 
                       $json_body['campaign_id'] ?? 
                       $json_body['campaignId'] ?? 
                       $json_body['data']['id'] ?? 
                       null;

        error_log("GOSAC DEBUG: ID da campanha capturado: " . ($id_campanha ?? 'NULL'));

        if ($id_campanha == null) {
            $error_msg = 'Id campanha não encontrado. Resposta completa: ' . $responde_body;
            error_log("GOSAC DEBUG: " . $error_msg);
            $this->update_db('erro_envio', $error_msg, $now, $agendamento_id);
            return new WP_Error('Erro_id', 'Id campanha não capturado. Verificar logs para resposta completa.', ['status' => 400]);
        }

        // PASSO 2: Agendar o evento em vez de usar sleep()
        $url_update = "{$url_infos[0]}/{$id_campanha}/status/started";
        $authorization = $url_infos[1];
        
        error_log("GOSAC DEBUG: Agendando evento para URL: " . $url_update);
        
        // Agenda um evento único para daqui a 2 minutos
        wp_schedule_single_event(time() + 120, 'gosac_start_campaign_event', [
            $url_update, 
            $authorization,
            $agendamento_id
        ]);
        
        // Status intermediário
        $this->update_db('agendado', 'Campanha criada e agendada para iniciar em 2min', $now, $agendamento_id);

        error_log("GOSAC DEBUG: Disparo concluído com sucesso - ID: " . $id_campanha);

        // Retorna a resposta imediatamente para o usuário
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Campanha agendada para iniciar em 2 minutos.',
            'campaign_id' => $id_campanha
        ], 202);
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
                'status' => $field_status,
                'resposta_api' => $field_api,
                'data_disparo' => $field_date
            ],
            [
                'agendamento_id' => $where_filter
            ],
            [
                '%s', // formato para status
                '%s', // formato para resposta_api
                '%s'  // formato para data_disparo
            ],
            [
                '%s' // formato para agendamento_id
            ]
        );

        if ($result === false) {
            error_log("GOSAC DEBUG: Erro ao atualizar banco de dados: " . $wpdb->last_error);
        }

        return $result;
    }

    private function wp_url_gosac($idgis_ambiente) {
        error_log("GOSAC DEBUG: Método wp_url_gosac chamado para ambiente: " . $idgis_ambiente);
        
        $credentials = Api_Consumer_Manager_V3::get_provider_credentials('gosac', $idgis_ambiente);
        
        error_log("GOSAC DEBUG: get_provider_credentials retornou: " . print_r($credentials, true));
        
        if (!$credentials) {
            error_log("GOSAC DEBUG: Credenciais não encontradas para ambiente: " . $idgis_ambiente);
            return new WP_Error('INVALID_IDGIS', 'IDGIS Ambiente não encontrado no API Manager', ['status' => 404]);
        }
        
        if (!isset($credentials['url']) || !isset($credentials['token'])) {
            error_log("GOSAC DEBUG: Credenciais incompletas - URL ou Token ausente");
            return new WP_Error('INVALID_CREDENTIALS', 'Credenciais incompletas no API Manager', ['status' => 500]);
        }
        
        error_log("GOSAC DEBUG: Retornando URL: " . $credentials['url'] . " e Token: " . substr($credentials['token'], 0, 20) . "...");
        
        return [$credentials['url'], $credentials['token']];
    }

    public function check_api_key(\WP_REST_Request $request) {
        return Api_Consumer_Manager_V3::check_api_key($request);
    }
}

if (!isset($GLOBALS['wp_gosac_consume'])) {
    $GLOBALS['wp_gosac_consume'] = new WP_GOSAC_CONSUME();
}