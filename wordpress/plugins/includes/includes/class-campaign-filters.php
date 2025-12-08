<?php
/**
 * Gerenciamento de filtros de campanhas
 * VERSÃO CORRIGIDA - Adiciona método get_filtered_records
 */

if (!defined('ABSPATH')) exit;

class Campaign_Manager_Filters {
    
    /**
     * COLUNAS QUE NÃO DEVEM APARECER NOS FILTROS
     */
    private static $excluded_columns = [
        'TELEFONE',
        'NOME',
        'IDGIS_AMBIENTE',
        'IDCOB_CONTRATO',
        'CPF',
        'CPF_CNPJ',
        'DATA_ATUALIZACAO',
        'DATA_CRIACAO',
        'DATA_INCLUSAO',
        'IDCOB_CLIENTE',
        'ID',
        'CODIGO_CLIENTE',
        'ULT_ATUALIZACAO',
        'CONTRATO',
        'ULTIMO_ENVIO_SMS',
        'FORNECEDOR',
        'ULT_FUP',
        'OPERADORA',
        'CONTRATO_PRODUTO',
        'IDCOB_TELEFONE',
        'ORIGEM_INFORMACAO',
        'PORTAL',
        'placa'
    ];
    
    /**
     * Retorna as colunas disponíveis para filtro de uma tabela
     */
    public static function get_filterable_columns($table_name) {
        global $wpdb;
        
        if (empty($table_name)) {
            return new WP_Error('invalid_table', 'Nome de tabela inválido');
        }
        
        // Busca informações das colunas
        $columns_info = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE 
             FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table_name
        ), ARRAY_A);
        
        if (empty($columns_info)) {
            return new WP_Error('no_columns', 'Não foi possível obter colunas da tabela');
        }
        
        $numeric_types = ['int', 'bigint', 'decimal', 'float', 'double', 'tinyint', 'smallint', 'mediumint', 'real'];
        $categorical_threshold = 50;
        $filters = [];
        
        foreach ($columns_info as $column) {
            $column_name = $column['COLUMN_NAME'];
            $data_type = strtolower($column['DATA_TYPE']);
            
            // Pula colunas excluídas
            if (in_array(strtoupper($column_name), self::$excluded_columns)) {
                continue;
            }
            
            $is_numeric = in_array($data_type, $numeric_types);
            
            // Conta valores distintos
            $distinct_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT `{$column_name}`) 
                 FROM `{$table_name}` 
                 WHERE `{$column_name}` IS NOT NULL"
            );
            
            // Se for numérico e tem muitos valores únicos, é filtro numérico
            if ($is_numeric && $distinct_count > $categorical_threshold) {
                $filters[$column_name] = [
                    'type' => 'numeric',
                    'data_type' => $data_type
                ];
            } else {
                // É categórico, busca os valores únicos
                $values = $wpdb->get_col(
                    "SELECT DISTINCT `{$column_name}` 
                     FROM `{$table_name}` 
                     WHERE `{$column_name}` IS NOT NULL 
                     AND `{$column_name}` != '' 
                     ORDER BY `{$column_name}` ASC
                     LIMIT 100"
                );
                
                if (!empty($values)) {
                    $filters[$column_name] = [
                        'type' => 'categorical',
                        'values' => $values,
                        'count' => count($values)
                    ];
                }
            }
        }
        
        return $filters;
    }
    
    /**
     * Constrói cláusula WHERE baseada nos filtros
     */
    public static function build_where_clause($filters) {
        global $wpdb;
        
        $where_clauses = ['1=1'];
        $allowed_operators = ['=', '!=', '>', '<', '>=', '<=', 'IN'];
        
        if (empty($filters) || !is_array($filters)) {
            return ' WHERE 1=1';
        }
        
        foreach ($filters as $column => $filter_data) {
            if (!is_array($filter_data) || empty($filter_data['operator'])) {
                continue;
            }
            
            if (!isset($filter_data['value']) || $filter_data['value'] === '') {
                continue;
            }
            
            $sanitized_column = esc_sql(str_replace('`', '', $column));
            $operator = strtoupper($filter_data['operator']);
            $value = $filter_data['value'];
            
            if (!in_array($operator, $allowed_operators)) {
                continue;
            }
            
            // Operador IN para múltiplos valores
            if ($operator === 'IN') {
                if (!is_array($value) || empty($value)) {
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($value), '%s'));
                $where_clauses[] = $wpdb->prepare(
                    "`{$sanitized_column}` IN ({$placeholders})",
                    $value
                );
            } else {
                // Operadores simples
                $where_clauses[] = $wpdb->prepare(
                    "`{$sanitized_column}` {$operator} %s",
                    $value
                );
            }
        }
        
        return ' WHERE ' . implode(' AND ', $where_clauses);
    }
    
    /**
     * Conta registros com filtros aplicados
     */
    public static function count_records($table_name, $filters) {
        global $wpdb;
        
        if (empty($table_name)) {
            return 0;
        }
        
        $where_sql = self::build_where_clause($filters);
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`" . $where_sql);
        
        return intval($count);
    }
    
    /**
     * 🎯 NOVO MÉTODO: Busca registros filtrados
     * Este método estava faltando e é chamado pelo execute_campaign
     */
    public static function get_filtered_records($table_name, $filters, $limit = 0) {
        global $wpdb;
        
        if (empty($table_name)) {
            return [];
        }
        
        // Constrói a cláusula WHERE
        $where_sql = self::build_where_clause($filters);
        
        // Adiciona LIMIT se especificado
        $limit_sql = '';
        if ($limit > 0) {
            $limit_sql = $wpdb->prepare(" LIMIT %d", $limit);
        }
        
        // Busca todos os campos necessários
        // Usa nomes flexíveis para compatibilidade com diferentes estruturas de tabela
        $sql = "SELECT 
                    `TELEFONE` as telefone,
                    `NOME` as nome,
                    `IDGIS_AMBIENTE` as idgis_ambiente,
                    `IDCOB_CONTRATO` as idcob_contrato,
                    `CPF` as cpf_cnpj
                FROM `{$table_name}`" . $where_sql . $limit_sql;
        
        $records = $wpdb->get_results($sql, ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('Campaign Manager - Erro ao buscar registros filtrados: ' . $wpdb->last_error);
            error_log('SQL executado: ' . $sql);
            return [];
        }
        
        // Normaliza os dados - converte para minúsculas para compatibilidade
        $normalized_records = [];
        foreach ($records as $record) {
            $normalized_records[] = [
                'telefone' => $record['telefone'] ?? '',
                'nome' => $record['nome'] ?? '',
                'idgis_ambiente' => $record['idgis_ambiente'] ?? 0,
                'idcob_contrato' => $record['idcob_contrato'] ?? 0,
                'cpf_cnpj' => $record['cpf_cnpj'] ?? ''
            ];
        }
        
        return $normalized_records;
    }
}