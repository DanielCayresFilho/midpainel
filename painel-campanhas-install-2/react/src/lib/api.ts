/**
 * API Client para integração com WordPress AJAX
 */

// Configuração da URL do WordPress
const getAjaxUrl = () => {
  if (typeof (window as any).pcAjax !== 'undefined' && (window as any).pcAjax?.ajaxurl) {
    let ajaxUrl = (window as any).pcAjax.ajaxurl;
    
    // Extrai o caminho base da URL atual (ex: /wordpress)
    const currentPath = window.location.pathname;
    const pathMatch = currentPath.match(/^(\/[^\/]+)/);
    const basePath = pathMatch ? pathMatch[1] : '';
    
    // Se a URL do AJAX não contém o caminho base, adiciona
    try {
      const urlObj = new URL(ajaxUrl);
      // Se o pathname não começa com o caminho base, corrige
      if (basePath && basePath !== '/' && !urlObj.pathname.startsWith(basePath)) {
        ajaxUrl = `${urlObj.origin}${basePath}/wp-admin/admin-ajax.php`;
        console.log('URL corrigida:', ajaxUrl);
      }
    } catch (e) {
      // Se não conseguir fazer parse, tenta construir manualmente
      if (basePath && basePath !== '/') {
        ajaxUrl = `${window.location.origin}${basePath}/wp-admin/admin-ajax.php`;
        console.log('URL construída manualmente:', ajaxUrl);
      }
    }
    
    return ajaxUrl;
  }
  
  // Fallback: constrói a partir do caminho atual
  const currentPath = window.location.pathname;
  const pathMatch = currentPath.match(/^(\/[^\/]+)/);
  const basePath = pathMatch ? pathMatch[1] : '';
  return `${window.location.origin}${basePath}/wp-admin/admin-ajax.php`;
};

// Helper para fazer requisições AJAX do WordPress
export const wpAjax = async (action: string, data: Record<string, any> = {}) => {
  const formData = new FormData();
  formData.append('action', action);
  
  // Adiciona nonce se disponível
  if (typeof (window as any).pcAjax !== 'undefined' && (window as any).pcAjax?.nonce) {
    formData.append('nonce', (window as any).pcAjax.nonce);
  }
  
  // Adiciona outros dados
  Object.keys(data).forEach(key => {
    if (data[key] !== null && data[key] !== undefined) {
      if (data[key] instanceof File) {
        formData.append(key, data[key]);
      } else if (typeof data[key] === 'object') {
        formData.append(key, JSON.stringify(data[key]));
      } else {
        formData.append(key, data[key]);
      }
    }
  });

  try {
    const ajaxUrl = getAjaxUrl();
    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    // Verifica se a resposta é JSON válido
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('Resposta não é JSON:', text.substring(0, 500));
      throw new Error(`Erro na requisição: ${response.status} ${response.statusText}. URL: ${ajaxUrl}`);
    }

    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.data?.message || result.data || 'Erro na requisição');
    }
    
    return result.data;
  } catch (error) {
    console.error('Erro na requisição AJAX:', error);
    throw error;
  }
};

// API específicas para o plugin

// Login
export const login = (email: string, password: string) => {
  return wpAjax('pc_login', { email, password });
};

// Logout
export const logout = () => {
  return wpAjax('pc_logout', {});
};

// Dashboard
export const getDashboardStats = () => {
  return wpAjax('pc_get_dashboard_stats', {});
};

// Bases disponíveis
export const getAvailableBases = () => {
  return wpAjax('pc_get_available_bases', {});
};

// Campanhas
export const getCampanhas = (params: Record<string, any> = {}) => {
  return wpAjax('pc_get_campanhas', params);
};

export const scheduleCampaign = (data: Record<string, any>) => {
  // Formata os dados conforme esperado pelo backend
  const payload: Record<string, any> = {
    table_name: data.base || data.table_name,
    filters: data.filters || [],
    providers_config: data.providers_config || {},
    template_id: data.template_id || data.template,
    record_limit: data.record_limit || 0,
    exclude_recent_phones: data.exclude_recent_phones !== undefined ? data.exclude_recent_phones : 1,
  };
  
  // Adiciona campos para templates da Ótima
  if (data.template_code) {
    payload.template_code = data.template_code;
  }
  if (data.template_source) {
    payload.template_source = data.template_source;
  }
  
  return wpAjax('cm_schedule_campaign', payload);
};

export const getPendingCampaigns = () => {
  return wpAjax('pc_get_pending_campaigns', {});
};

export const approveCampaign = (agendamentoId: string, fornecedor: string) => {
  return wpAjax('pc_approve_campaign', { agendamento_id: agendamentoId, fornecedor });
};

export const denyCampaign = (agendamentoId: string, fornecedor: string, motivo?: string) => {
  return wpAjax('pc_deny_campaign', { agendamento_id: agendamentoId, fornecedor, motivo });
};

// Filtros e bases
export const getFilters = (base: string) => {
  return wpAjax('cm_get_filters', { table_name: base });
};

export const getCount = (data: Record<string, any>) => {
  return wpAjax('cm_get_count', {
    table_name: data.table_name || data.base,
    filters: data.filters || [],
  });
};

// Templates de mensagem
export const getMessages = () => {
  return wpAjax('pc_get_messages', {});
};

export const getMessage = (id: string) => {
  return wpAjax('pc_get_message', { message_id: id });
};

export const createMessage = (data: Record<string, any>) => {
  return wpAjax('pc_create_message', data);
};

export const updateMessage = (id: string, data: Record<string, any>) => {
  return wpAjax('pc_update_message', { message_id: id, ...data });
};

export const deleteMessage = (id: string) => {
  return wpAjax('pc_delete_message', { message_id: id });
};

export const getTemplateContent = async (id: string) => {
  const content = await wpAjax('cm_get_template_content', { template_id: parseInt(id) });
  // O handler retorna apenas a string do conteúdo, normalizamos para objeto
  return typeof content === 'string' ? { content } : content;
};

// Relatórios
export const getReportData = (params: Record<string, any> = {}) => {
  // Normaliza os nomes dos parâmetros
  return wpAjax('pc_get_report_data', {
    filter_date_start: params.data_inicio || params.dateFrom || '',
    filter_date_end: params.data_fim || params.dateTo || '',
    filter_fornecedor: params.fornecedor || params.provider || '',
    filter_user: params.filter_user || '',
    filter_ambiente: params.filter_ambiente || '',
    filter_agendamento: params.filter_agendamento || '',
    filter_idgis: params.filter_idgis || 0,
    status: params.status || '',
  });
};

export const getReport1x1Stats = (params: Record<string, any> = {}) => {
  return wpAjax('pc_get_report_1x1_stats', params);
};

// Campanhas recorrentes
export const getRecurring = () => {
  return wpAjax('cm_get_recurring', {});
};

export const saveRecurring = (data: Record<string, any>) => {
  // Formata os dados conforme esperado pelo backend
  return wpAjax('cm_save_recurring', {
    nome_campanha: data.nome_campanha,
    table_name: data.table_name,
    template_id: data.template_id,
    providers_config: typeof data.providers_config === 'string' 
      ? data.providers_config 
      : JSON.stringify(data.providers_config || {}),
    filters: typeof data.filters === 'string' 
      ? data.filters 
      : JSON.stringify(data.filters || []),
    record_limit: data.record_limit || 0,
    exclude_recent_phones: data.exclude_recent_phones !== undefined ? data.exclude_recent_phones : 1,
    id: data.id, // Se tiver id, será update, senão será insert
  });
};

export const deleteRecurring = (id: string) => {
  return wpAjax('cm_delete_recurring', { id: parseInt(id) });
};

export const toggleRecurring = (id: string, active: boolean) => {
  return wpAjax('cm_toggle_recurring', { id: parseInt(id), ativo: active ? 1 : 0 });
};

export const executeRecurringNow = (id: string) => {
  return wpAjax('cm_execute_recurring_now', { id: parseInt(id) });
};

// Campanha por arquivo
export const uploadCampaignFile = async (file: File, matchField: string) => {
  const formData = new FormData();
  formData.append('csv_file', file);
  formData.append('match_field', matchField);
  
  const ajaxUrl = typeof (window as any).pcAjax !== 'undefined' && (window as any).pcAjax?.ajaxurl
    ? (window as any).pcAjax.ajaxurl
    : '/wp-admin/admin-ajax.php';
  
  const nonce = typeof (window as any).pcAjax !== 'undefined' && (window as any).pcAjax?.nonce
    ? (window as any).pcAjax.nonce
    : '';
  
  formData.append('action', 'cpf_cm_upload_csv');
  formData.append('nonce', nonce);
  
  const response = await fetch(ajaxUrl, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
  });
  
  const result = await response.json();
  
  if (!result.success) {
    throw new Error(result.data?.message || result.data || 'Erro no upload');
  }
  
  return result.data;
};

export const getCustomFilters = () => {
  return wpAjax('cpf_cm_get_custom_filters', {});
};

export const previewCount = (data: Record<string, any>) => {
  return wpAjax('cpf_cm_preview_count', data);
};

export const createCpfCampaign = (data: Record<string, any>) => {
  // O handler espera: temp_id, table_name, template_id, provider, match_field
  return wpAjax('cpf_cm_create_campaign', {
    temp_id: data.temp_id,
    table_name: data.table_name,
    template_id: data.template_id,
    provider: data.provider,
    match_field: data.match_field || 'cpf',
  });
};

// Controle de custos
export const saveCustoProvider = (data: Record<string, any>) => {
  return wpAjax('pc_save_custo_provider', data);
};

export const getCustosProviders = () => {
  return wpAjax('pc_get_custos_providers', {});
};

export const deleteCustoProvider = (id: string) => {
  return wpAjax('pc_delete_custo_provider', { id });
};

export const saveOrcamentoBase = (data: Record<string, any>) => {
  return wpAjax('pc_save_orcamento_base', data);
};

export const getOrcamentosBases = () => {
  return wpAjax('pc_get_orcamentos_bases', {});
};

export const deleteOrcamentoBase = (id: string) => {
  return wpAjax('pc_delete_orcamento_base', { id });
};

export const getRelatorioCustos = (params: Record<string, any> = {}) => {
  return wpAjax('pc_get_relatorio_custos', params);
};

// Configurações (Carteiras)
export const getCarteiras = () => {
  return wpAjax('pc_get_carteiras', {});
};

export const getCarteira = (id: string) => {
  return wpAjax('pc_get_carteira', { id });
};

export const createCarteira = (data: Record<string, any>) => {
  return wpAjax('pc_create_carteira', data);
};

export const updateCarteira = (id: string, data: Record<string, any>) => {
  return wpAjax('pc_update_carteira', { id, ...data });
};

export const deleteCarteira = (id: string) => {
  return wpAjax('pc_delete_carteira', { id });
};

export const getBasesCarteira = (carteiraId: string) => {
  return wpAjax('pc_get_bases_carteira', { carteira_id: parseInt(carteiraId) });
};

export const vincularBaseCarteira = (carteiraId: string, bases: string[]) => {
  return wpAjax('pc_vincular_base_carteira', { carteira_id: parseInt(carteiraId), bases });
};

// Iscas
export const getIscas = () => {
  return wpAjax('pc_get_iscas', {});
};

export const getIsca = (id: string) => {
  return wpAjax('pc_get_isca', { id });
};

export const createIsca = (data: Record<string, any>) => {
  return wpAjax('pc_create_isca', data);
};

export const updateIsca = (id: string, data: Record<string, any>) => {
  return wpAjax('pc_update_isca', { id, ...data });
};

export const deleteIsca = (id: string) => {
  return wpAjax('pc_delete_isca', { id });
};

// Validação de Base
export const checkBaseUpdate = (tableName: string) => {
  return wpAjax('cm_check_base_update', { table_name: tableName });
};

// Ranking
export const getRanking = () => {
  return wpAjax('pc_get_ranking', {});
};

// API Manager
export const saveMasterApiKey = (key: string) => {
  return wpAjax('pc_save_master_api_key', { master_api_key: key });
};

export const getMicroserviceConfig = () => {
  return wpAjax('pc_get_microservice_config', {});
};

export const saveMicroserviceConfig = (data: Record<string, any>) => {
  return wpAjax('pc_save_microservice_config', data);
};

export const getStaticCredentials = () => {
  return wpAjax('pc_get_static_credentials', {});
};

export const saveStaticCredentials = (data: Record<string, any>) => {
  return wpAjax('pc_save_static_credentials', data);
};

export const createCredential = (data: Record<string, any>) => {
  return wpAjax('pc_create_credential', data);
};

export const getCredential = (id: string) => {
  return wpAjax('pc_get_credential', { id });
};

export const updateCredential = (id: string, data: Record<string, any>) => {
  return wpAjax('pc_update_credential', { id, ...data });
};

export const deleteCredential = (id: string) => {
  return wpAjax('pc_delete_credential', { id });
};

// Custom Providers APIs
export const createCustomProvider = (data: Record<string, any>) => {
  return wpAjax('pc_create_custom_provider', data);
};

export const listCustomProviders = () => {
  return wpAjax('pc_list_custom_providers', {});
};

export const getCustomProvider = (providerKey: string) => {
  return wpAjax('pc_get_custom_provider', { provider_key: providerKey });
};

export const updateCustomProvider = (providerKey: string, data: Record<string, any>) => {
  return wpAjax('pc_update_custom_provider', { provider_key: providerKey, ...data });
};

export const deleteCustomProvider = (providerKey: string) => {
  return wpAjax('pc_delete_custom_provider', { provider_key: providerKey });
};

