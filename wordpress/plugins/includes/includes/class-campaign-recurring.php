<?php
/**
 * Gerenciamento de campanhas recorrentes (templates salvos)
 * VERSÃO COMPLETA - Com iscas e nome no agendamento
 */

if (!defined('ABSPATH')) exit;

class Campaign_Manager_Recurring {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cm_recurring_campaigns';
        
        // Registra handlers AJAX
        add_action('wp_ajax_cm_save_recurring', [$this, 'save_recurring']);
        add_action('wp_ajax_cm_get_recurring', [$this, 'get_recurring']);
        add_action('wp_ajax_cm_delete_recurring', [$this, 'delete_recurring']);
        add_action('wp_ajax_cm_toggle_recurring', [$this, 'toggle_recurring']);
        add_action('wp_ajax_cm_execute_recurring_now', [$this, 'execute_recurring_now']);
        add_action('wp_ajax_cm_preview_recurring_count', [$this, 'preview_recurring_count']);
    }
    
    /**
     * Salva um novo template de campanha
     */
    public function save_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $nome_campanha = sanitize_text_field($_POST['nome_campanha'] ?? '');
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        $filters_json = stripslashes($_POST['filters'] ?? '[]');
        $providers_config_json = stripslashes($_POST['providers_config'] ?? '{}');
        $template_id = intval($_POST['template_id'] ?? 0);
        $record_limit = intval($_POST['record_limit'] ?? 0);
        
        if (empty($nome_campanha) || empty($table_name) || empty($template_id)) {
            wp_send_json_error('Dados incompletos para criar template.');
        }
        
        $result = $wpdb->insert(
            $this->table,
            [
                'nome_campanha' => $nome_campanha,
                'tabela_origem' => $table_name,
                'filtros_json' => $filters_json,
                'providers_config' => $providers_config_json,
                'template_id' => $template_id,
                'record_limit' => $record_limit,
                'ativo' => 1,
                'criado_por' => get_current_user_id()
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d']
        );
        
        if ($result === false) {
            wp_send_json_error('Erro ao salvar template: ' . $wpdb->last_error);
        }
        
        wp_send_json_success('Template salvo com sucesso!');
    }
    
    /**
     * Lista todos os templates salvos
     */
    public function get_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $campaigns = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY criado_em DESC",
            ARRAY_A
        );
        
        wp_send_json_success($campaigns);
    }
    
    /**
     * Deleta um template
     */
    public function delete_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        $result = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        
        if ($result === false) {
            wp_send_json_error('Erro ao deletar template.');
        }
        
        wp_send_json_success('Template deletado!');
    }
    
    /**
     * Ativa/desativa um template
     */
    public function toggle_recurring() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        $ativo = intval($_POST['ativo'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        $result = $wpdb->update(
            $this->table,
            ['ativo' => $ativo],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error('Erro ao atualizar status.');
        }
        
        wp_send_json_success($ativo ? 'Template ativado!' : 'Template desativado!');
    }
    
    /**
     * Preview: conta quantos registros serão afetados
     */
    public function preview_recurring_count() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Template não encontrado.');
        }
        
        $filters = json_decode($campaign['filtros_json'], true);
        $total_count = Campaign_Manager_Filters::count_records($campaign['tabela_origem'], $filters);
        
        $final_count = $campaign['record_limit'] > 0 ? min($total_count, $campaign['record_limit']) : $total_count;
        
        wp_send_json_success([
            'count' => $final_count,
            'total_available' => $total_count,
            'has_limit' => $campaign['record_limit'] > 0
        ]);
    }
    
    /**
     * Executa um template agora
     */
    public function execute_recurring_now() {
        check_ajax_referer('campaign-manager-nonce', 'nonce');
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido.');
        }
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$campaign) {
            wp_send_json_error('Template não encontrado.');
        }
        
        if ($campaign['ativo'] != 1) {
            wp_send_json_error('Este template está desativado. Ative-o antes de executar.');
        }
        
        // EXECUTA A CAMPANHA
        $result = $this->execute_campaign($campaign);
        
        // Atualiza última execução
        $wpdb->update(
            $this->table,
            ['ultima_execucao' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'details' => $result
            ]);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * 🎯 EXECUTA UMA CAMPANHA RECORRENTE - VERSÃO COMPLETA
     */
    private function execute_campaign($campaign) {
        global $wpdb;
        
        try {
            // 1. Decodifica configurações
            $filters = json_decode($campaign['filtros_json'], true);
            if (!is_array($filters)) {
                $filters = [];
            }
            
            $providers_config = json_decode($campaign['providers_config'], true);
            
            if (!$providers_config || empty($providers_config['providers'])) {
                return [
                    'success' => false,
                    'message' => 'Configuração de provedores inválida'
                ];
            }
            
            // 2. 🎯 Busca registros excluindo telefones que já receberam mensagem recentemente
            // Busca até completar o limite, mantendo os filtros da campanha
            $records = $this->get_available_records_for_campaign(
                $campaign['tabela_origem'],
                $filters,
                $campaign['record_limit']
            );
            
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum registro disponível encontrado (todos já receberam mensagem recentemente ou não atendem aos filtros)'
                ];
            }
            
            error_log('Campaign Manager - Records disponíveis encontrados: ' . count($records));
            
            // 🎣 ADICIONA ISCAS ATIVAS (apenas com IDGIS compatível)
            $all_baits = Campaign_Manager_Baits::get_active_baits();
            $idgis_found = array();
            
            // Descobre quais IDGIS existem nos registros filtrados
            foreach ($records as $record) {
                if (!empty($record['idgis_ambiente'])) {
                    $idgis_found[$record['idgis_ambiente']] = true;
                }
            }
            
            $baits_count = 0;
            
            foreach ($all_baits as $bait) {
                // Só adiciona se o IDGIS da isca existe nos registros
                if (isset($idgis_found[$bait['idgis_ambiente']])) {
                    $records[] = [
                        'telefone' => $bait['telefone'],
                        'nome' => $bait['nome'] . ' [ISCA]',
                        'idgis_ambiente' => $bait['idgis_ambiente'],
                        'idcob_contrato' => 0,
                        'cpf_cnpj' => ''
                    ];
                    $baits_count++;
                }
            }
            
            if ($baits_count > 0) {
                error_log("Campaign Manager - Adicionando {$baits_count} iscas compatíveis");
                error_log("Campaign Manager - Total com iscas: " . count($records));
            }
            
            // 3. Busca o conteúdo do template
            $template_post = get_post($campaign['template_id']);
            if (!$template_post) {
                return [
                    'success' => false,
                    'message' => 'Template de mensagem não encontrado'
                ];
            }
            $mensagem_template = $template_post->post_content;
            
            // 4. Distribui registros entre provedores
            $distribution = $this->distribute_records($records, $providers_config);
            
            if (empty($distribution)) {
                return [
                    'success' => false,
                    'message' => 'Erro ao distribuir registros entre provedores'
                ];
            }
            
            // 5. Insere agendamentos na tabela envios_pendentes
            $total_inserted = 0;
            $total_skipped = 0;
            $envios_table = $wpdb->prefix . 'envios_pendentes';
            
            // Gera um ID único para este agendamento
            $agendamento_base_id = current_time('YmdHis');
            $current_user_id = get_current_user_id();
            
            foreach ($distribution as $provider => $provider_records) {
                $prefix = strtoupper(substr($provider, 0, 1));
                
                // 🎯 ADICIONA NOME DO TEMPLATE NO AGENDAMENTO_ID
                $campaign_name_clean = preg_replace('/[^a-zA-Z0-9]/', '', $campaign['nome_campanha']);
                $campaign_name_short = substr($campaign_name_clean, 0, 30);
                $agendamento_id = $prefix . $agendamento_base_id . '_' . $campaign_name_short;
                
                error_log("Campaign Manager - Provedor {$provider}: " . count($provider_records) . " registros");
                
                $skipped_count = 0;
                $inserted_count_provider = 0;
                
                foreach ($provider_records as $record) {
                    // Extrai e normaliza telefone
                    $telefone_normalizado = $this->extract_phone($record);
                    
                    // 🚫 VERIFICA: Não pode enviar mensagem dois dias seguidos para o mesmo telefone
                    if ($this->has_received_message_recently($telefone_normalizado)) {
                        error_log("Campaign Manager - ⏭️ Telefone {$telefone_normalizado} já recebeu mensagem recentemente. Pulando...");
                        $skipped_count++;
                        $total_skipped++;
                        continue;
                    }
                    
                    // 🎯 Aplica mapeamento IDGIS
                    $idgis_original = intval($record['idgis_ambiente'] ?? 0);
                    $idgis_mapeado = $idgis_original;
                    
                    if ($idgis_original > 0 && class_exists('CM_IDGIS_Mapper')) {
                        $idgis_mapeado = CM_IDGIS_Mapper::get_mapped_idgis(
                            $campaign['tabela_origem'],
                            $provider,
                            $idgis_original
                        );
                        
                        if ($idgis_mapeado != $idgis_original) {
                            error_log("Campaign Manager - ✅ MAPEAMENTO APLICADO: {$idgis_original} → {$idgis_mapeado} (Provedor: {$provider})");
                        }
                    }
                    
                    // Prepara mensagem com placeholders substituídos
                    $mensagem_final = $this->replace_placeholders($mensagem_template, $record);
                    
                    // Monta dados do agendamento
                    $insert_data = [
                        'telefone' => $telefone_normalizado,
                        'nome' => $record['nome'] ?? '',
                        'idgis_ambiente' => $idgis_mapeado,
                        'idcob_contrato' => intval($record['idcob_contrato'] ?? 0),
                        'cpf_cnpj' => $record['cpf_cnpj'] ?? '',
                        'data_cadastro' => current_time('mysql'),
                        'mensagem' => $mensagem_final,
                        'fornecedor' => $provider,
                        'agendamento_id' => $agendamento_id,
                        'status' => 'pendente_aprovacao',
                        'resposta_api' => null,
                        'current_user_id' => $current_user_id,
                        'data_disparo' => null,
                        'valido' => 1
                    ];
                    
                    $inserted = $wpdb->insert(
                        $envios_table, 
                        $insert_data,
                        ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
                    );
                    
                    if ($inserted !== false) {
                        $total_inserted++;
                        $inserted_count_provider++;
                    } else {
                        error_log('Campaign Manager - Erro ao inserir: ' . $wpdb->last_error);
                    }
                }
                
                if ($skipped_count > 0 || $inserted_count_provider > 0) {
                    error_log("Campaign Manager - Provedor {$provider}: Inseridos: {$inserted_count_provider} | Pulados: {$skipped_count}");
                }
            }
            
            if ($total_inserted === 0) {
                return [
                    'success' => false,
                    'message' => 'Nenhum registro foi agendado. Erro: ' . $wpdb->last_error
                ];
            }
            
            $skipped_message = $total_skipped > 0 ? " | ⏭️ {$total_skipped} pulados (já receberam mensagem recentemente)" : "";
            
            return [
                'success' => true,
                'message' => sprintf(
                    'Campanha executada! %d registros agendados em %d provedor(es)%s%s',
                    $total_inserted,
                    count($distribution),
                    $baits_count > 0 ? " | 🎣 {$baits_count} iscas" : "",
                    $skipped_message
                ),
                'records_inserted' => $total_inserted,
                'records_skipped' => $total_skipped,
                'providers_used' => array_keys($distribution),
                'distribution_details' => array_map('count', $distribution),
                'baits_count' => $baits_count
            ];
            
        } catch (Exception $e) {
            error_log('Campaign Manager - Erro ao executar template: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return [
                'success' => false,
                'message' => 'Erro ao executar campanha: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Distribui registros entre provedores
     */
    private function distribute_records($records, $providers_config) {
        $mode = $providers_config['mode'] ?? 'split';
        $providers = $providers_config['providers'] ?? [];
        $percentages = $providers_config['percentages'] ?? [];
        
        if (empty($providers)) {
            error_log('Campaign Manager - Nenhum provedor configurado');
            return [];
        }
        
        $distribution = [];
        
        if ($mode === 'all') {
            // Envia para TODOS os provedores
            foreach ($providers as $provider) {
                $distribution[$provider] = $records;
            }
        } else {
            // Divide entre provedores
            $total_records = count($records);
            $shuffled_records = $records;
            shuffle($shuffled_records);
            
            $start_index = 0;
            
            foreach ($providers as $i => $provider) {
                $percentage = $percentages[$provider] ?? (100 / count($providers));
                $count = (int) ceil(($percentage / 100) * $total_records);
                
                // Último provedor recebe o resto
                if ($i === count($providers) - 1) {
                    $count = $total_records - $start_index;
                }
                
                $provider_records = array_slice($shuffled_records, $start_index, $count);
                
                if (!empty($provider_records)) {
                    $distribution[$provider] = $provider_records;
                }
                
                $start_index += $count;
                
                if ($start_index >= $total_records) {
                    break;
                }
            }
        }
        
        return $distribution;
    }
    
    /**
     * Extrai telefone do registro
     */
    private function extract_phone($record) {
        $phone = $record['telefone'] ?? '';
        
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove código do país se tiver (55)
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '55') {
            $phone = substr($phone, 2);
        }
        
        return $phone;
    }
    
    /**
     * Substitui placeholders na mensagem
     */
    private function replace_placeholders($message, $record) {
        if (empty($message)) {
            return '';
        }
        
        $placeholders = [
            '{nome}' => $record['nome'] ?? '',
            '{cpf}' => $record['cpf_cnpj'] ?? '',
            '{cnpj}' => $record['cpf_cnpj'] ?? '',
            '{telefone}' => $this->extract_phone($record),
            '{idgis}' => $record['idgis_ambiente'] ?? '',
            '{contrato}' => $record['idcob_contrato'] ?? '',
            '{data}' => date('d/m/Y'),
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }
        
        return $message;
    }
    
    /**
     * 🎯 Busca registros disponíveis para campanha, excluindo telefones que já receberam mensagem
     * Busca incrementalmente até completar o limite ou até não ter mais registros
     */
    private function get_available_records_for_campaign($table_name, $filters, $limit) {
        global $wpdb;
        
        if (empty($table_name) || $limit <= 0) {
            return [];
        }
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        $ontem = date('Y-m-d 00:00:00', strtotime('-1 day'));
        
        // Busca telefones que já receberam mensagem recentemente (uma única vez)
        $telefones_bloqueados = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT telefone 
            FROM {$envios_table} 
            WHERE (
                (data_cadastro >= %s AND data_cadastro < NOW())
                OR (data_disparo >= %s AND data_disparo IS NOT NULL)
            )
            AND telefone IS NOT NULL 
            AND telefone != ''",
            $ontem,
            $ontem
        ));
        
        // Normaliza telefones bloqueados (remove caracteres não numéricos)
        $telefones_bloqueados_normalizados = [];
        foreach ($telefones_bloqueados as $tel) {
            $tel_limpo = preg_replace('/[^0-9]/', '', $tel);
            if (strlen($tel_limpo) > 11 && substr($tel_limpo, 0, 2) === '55') {
                $tel_limpo = substr($tel_limpo, 2);
            }
            if (!empty($tel_limpo)) {
                $telefones_bloqueados_normalizados[$tel_limpo] = true;
            }
        }
        
        error_log('Campaign Manager - Telefones bloqueados (já receberam mensagem): ' . count($telefones_bloqueados_normalizados));
        
        // Busca registros em lotes para garantir que teremos o suficiente após filtrar
        $batch_size = max($limit * 3, 500);
        $offset = 0;
        $available_records = [];
        $seen_phones = []; // Para evitar duplicatas
        
        while (count($available_records) < $limit) {
            // Busca lote de registros com os filtros (com ordenação para consistência)
            $where_sql = Campaign_Manager_Filters::build_where_clause($filters);
            
            // Tenta ordenar por ID ou por uma coluna que exista na tabela
            $order_by = '';
            // Verifica se existe coluna ID
            $has_id = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'ID'");
            if ($has_id) {
                $order_by = " ORDER BY `ID` ASC";
            } else {
                // Tenta por IDCOB_CONTRATO se existir
                $has_contrato = $wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'IDCOB_CONTRATO'");
                if ($has_contrato) {
                    $order_by = " ORDER BY `IDCOB_CONTRATO` ASC";
                }
            }
            
            $sql = "SELECT 
                        `TELEFONE` as telefone,
                        `NOME` as nome,
                        `IDGIS_AMBIENTE` as idgis_ambiente,
                        `IDCOB_CONTRATO` as idcob_contrato,
                        `CPF` as cpf_cnpj
                    FROM `{$table_name}`" . $where_sql . $order_by . " 
                    LIMIT {$batch_size} OFFSET {$offset}";
            
            $batch_records = $wpdb->get_results($sql, ARRAY_A);
            
            if (empty($batch_records)) {
                if ($wpdb->last_error) {
                    error_log('Campaign Manager - Erro ao buscar registros: ' . $wpdb->last_error);
                }
                // Não há mais registros
                break;
            }
            
            // Filtra registros excluindo telefones bloqueados
            foreach ($batch_records as $record) {
                if (count($available_records) >= $limit) {
                    break;
                }
                
                $telefone = $record['telefone'] ?? '';
                if (empty($telefone)) {
                    continue; // Pula registros sem telefone
                }
                
                // Normaliza telefone para comparação
                $telefone_normalizado = preg_replace('/[^0-9]/', '', $telefone);
                
                // Remove código do país se tiver (55)
                if (strlen($telefone_normalizado) > 11 && substr($telefone_normalizado, 0, 2) === '55') {
                    $telefone_normalizado = substr($telefone_normalizado, 2);
                }
                
                // Verifica se o telefone não está bloqueado e não foi visto antes
                if (!empty($telefone_normalizado) 
                    && !isset($telefones_bloqueados_normalizados[$telefone_normalizado])
                    && !isset($seen_phones[$telefone_normalizado])) {
                    
                    $available_records[] = [
                        'telefone' => $telefone,
                        'nome' => $record['nome'] ?? '',
                        'idgis_ambiente' => $record['idgis_ambiente'] ?? 0,
                        'idcob_contrato' => $record['idcob_contrato'] ?? 0,
                        'cpf_cnpj' => $record['cpf_cnpj'] ?? ''
                    ];
                    
                    $seen_phones[$telefone_normalizado] = true;
                }
            }
            
            // Se não há mais registros ou conseguiu menos que o lote, para de buscar
            if (count($batch_records) < $batch_size) {
                break;
            }
            
            $offset += $batch_size;
            
            // Limite de segurança para não fazer loop infinito (10.000 registros)
            if ($offset > 10000) {
                error_log('Campaign Manager - Atingido limite de segurança na busca incremental (10.000 registros)');
                break;
            }
        }
        
        error_log('Campaign Manager - Registros disponíveis encontrados: ' . count($available_records) . ' de ' . $limit . ' solicitados');
        
        return $available_records;
    }
    
    /**
     * 🚫 Verifica se o telefone já recebeu mensagem no dia anterior ou hoje
     * Retorna true se já recebeu, false caso contrário
     */
    private function has_received_message_recently($telefone) {
        global $wpdb;
        
        if (empty($telefone)) {
            return false;
        }
        
        $envios_table = $wpdb->prefix . 'envios_pendentes';
        
        // Data de ontem (início do dia) - verifica se enviou ontem ou hoje
        $ontem = date('Y-m-d 00:00:00', strtotime('-1 day'));
        
        // Verifica se existe algum registro com este telefone que tenha:
        // - data_cadastro ou data_disparo entre ontem e agora
        // Isso evita enviar mensagem dois dias seguidos para o mesmo telefone
        $query = $wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$envios_table} 
            WHERE telefone = %s 
            AND (
                (data_cadastro >= %s AND data_cadastro < NOW())
                OR (data_disparo >= %s AND data_disparo IS NOT NULL)
            )
            LIMIT 1",
            $telefone,
            $ontem,
            $ontem
        );
        
        $count = $wpdb->get_var($query);
        
        return $count > 0;
    }
}

// Inicializa
new Campaign_Manager_Recurring();