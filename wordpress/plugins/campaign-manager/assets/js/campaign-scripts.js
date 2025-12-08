/**
 * Campaign Manager - JavaScript com Exibição Detalhada de Iscas
 */

jQuery(document).ready(function($) {
    let selectedTable = '';
    let availableFilters = {};
    let currentFilters = {};
    let audienceCount = 0;

    console.log('✅ Campaign Manager JS carregado');

    // ===== STEP 1: SELECIONAR TABELA =====
    $('#data-source-select').on('change', function() {
        selectedTable = $(this).val();
        console.log('📊 Tabela selecionada:', selectedTable);
        
        if (selectedTable) {
            loadFilters(selectedTable);
        }
    });

    // ===== CARREGAR FILTROS =====
    function loadFilters(tableName) {
        console.log('⏳ Carregando filtros para:', tableName);
        $('#filters-container').html('<p>⏳ Carregando filtros...</p>');
        
        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_get_filters',
                nonce: cmAjax.nonce,
                table_name: tableName
            },
            success: function(response) {
                console.log('✅ Filtros recebidos:', response);
                if (response.success) {
                    availableFilters = response.data;
                    renderFilters(availableFilters);
                    $('#step-2').show();
                    updateAudienceCount();
                } else {
                    alert('Erro ao carregar filtros: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro AJAX:', error);
                alert('Erro de conexão ao carregar filtros');
            }
        });
    }

    // ===== RENDERIZAR FILTROS (NOVA VERSÃO DINÂMICA) =====
    function renderFilters(filters) {
        const container = $('#filters-container');
        container.empty();

        if (Object.keys(filters).length === 0) {
            container.html('<p>✅ Nenhum filtro disponível. Todos os registros serão incluídos.</p>');
            return;
        }

        // Guarda os filtros disponíveis globalmente
        window.availableFiltersData = filters;

        // Cria interface de filtros dinâmicos
        const filtersUI = $(`
            <div class="cm-dynamic-filters">
                <!-- Barra de Filtros Ativos -->
                <div class="cm-active-filters-bar">
                    <div class="cm-active-filters-list" id="active-filters-list">
                        <span class="cm-no-filters-text">Nenhum filtro aplicado</span>
                    </div>
                    <button type="button" class="cm-btn-add-filter" id="btn-add-filter">
                        <span>➕</span> Adicionar Filtro
                    </button>
                </div>

                <!-- Dropdown de Seleção de Filtro -->
                <div class="cm-filter-dropdown" id="filter-dropdown" style="display:none;">
                    <div class="cm-filter-search">
                        <input type="text" id="filter-search-input" 
                               placeholder="🔍 Buscar coluna..." 
                               class="cm-input">
                    </div>
                    <div class="cm-filter-options" id="filter-options">
                        <!-- Opções serão preenchidas dinamicamente -->
                    </div>
                </div>

                <!-- Modal de Configuração de Filtro -->
                <div class="cm-filter-modal-overlay" id="filter-modal-overlay" style="display:none;">
                    <div class="cm-filter-modal">
                        <div class="cm-filter-modal-header">
                            <h3 id="filter-modal-title">Configurar Filtro</h3>
                            <button type="button" class="cm-filter-modal-close" id="filter-modal-close">×</button>
                        </div>
                        <div class="cm-filter-modal-body" id="filter-modal-body">
                            <!-- Conteúdo será preenchido dinamicamente -->
                        </div>
                    </div>
                </div>
            </div>
        `);

        container.append(filtersUI);
        renderFilterOptions();
        renderActiveFilters();
    }

    // ===== RENDERIZAR OPÇÕES DE FILTRO NO DROPDOWN =====
    function renderFilterOptions(searchTerm = '') {
        const optionsContainer = $('#filter-options');
        optionsContainer.empty();

        if (!window.availableFiltersData) return;

        const searchLower = searchTerm.toLowerCase();
        let hasResults = false;

        Object.keys(window.availableFiltersData).forEach(function(columnName) {
            // Verifica se já está ativo
            if (currentFilters[columnName]) {
                return; // Pula se já está sendo usado
            }

            // Filtra por busca
            if (searchTerm && !columnName.toLowerCase().includes(searchLower)) {
                return;
            }

            hasResults = true;
            const filterData = window.availableFiltersData[columnName];
            const typeLabel = filterData.type === 'numeric' ? '🔢 Numérico' : '📋 Categórico';
            
            const option = $(`
                <div class="cm-filter-option" data-column="${columnName}">
                    <div class="cm-filter-option-info">
                        <strong>${columnName}</strong>
                        <span class="cm-filter-option-type">${typeLabel}</span>
                    </div>
                    <span class="cm-filter-option-arrow">→</span>
                </div>
            `);

            option.on('click', function() {
                openFilterModal(columnName, window.availableFiltersData[columnName]);
                $('#filter-dropdown').hide();
                $('#filter-search-input').val('');
            });

            optionsContainer.append(option);
        });

        if (!hasResults) {
            optionsContainer.html('<div class="cm-filter-no-results">Nenhuma coluna disponível</div>');
        }
    }

    // ===== ABRIR MODAL DE CONFIGURAÇÃO DE FILTRO =====
    function openFilterModal(columnName, filterData) {
        const modal = $('#filter-modal-overlay');
        const modalBody = $('#filter-modal-body');
        const modalTitle = $('#filter-modal-title');

        modalTitle.text(`Filtro: ${columnName}`);
        modalBody.empty();

        let filterHTML = '';

        // FILTRO NUMÉRICO
        if (filterData.type === 'numeric') {
            const currentFilter = currentFilters[columnName] || {};
            
            filterHTML = $(`
                <div class="cm-filter-config">
                    <label>Operador:</label>
                    <select class="cm-select" id="modal-operator" data-column="${columnName}">
                        <option value="">-- Selecione --</option>
                        <option value="=" ${currentFilter.operator === '=' ? 'selected' : ''}>= (Igual)</option>
                        <option value="!=" ${currentFilter.operator === '!=' ? 'selected' : ''}>≠ (Diferente)</option>
                        <option value=">" ${currentFilter.operator === '>' ? 'selected' : ''}>> (Maior que)</option>
                        <option value="<" ${currentFilter.operator === '<' ? 'selected' : ''}>< (Menor que)</option>
                        <option value=">=" ${currentFilter.operator === '>=' ? 'selected' : ''}>≥ (Maior ou igual)</option>
                        <option value="<=" ${currentFilter.operator === '<=' ? 'selected' : ''}>≤ (Menor ou igual)</option>
                    </select>
                    <label style="margin-top:15px;">Valor:</label>
                    <input type="number" class="cm-input" id="modal-value" 
                           data-column="${columnName}" 
                           value="${currentFilter.value || ''}" 
                           placeholder="Digite o valor">
                    <div class="cm-filter-modal-actions">
                        <button type="button" class="cm-btn cm-btn-primary" id="btn-save-filter">
                            Salvar Filtro
                        </button>
                        <button type="button" class="cm-btn" id="btn-cancel-filter" style="background:#e5e7eb;">
                            Cancelar
                        </button>
                    </div>
                </div>
            `);
        } 
        // FILTRO CATEGÓRICO
        else if (filterData.type === 'categorical') {
            const currentFilter = currentFilters[columnName] || {};
            const selectedValues = currentFilter.value || [];

            filterHTML = $(`
                <div class="cm-filter-config">
                    <label>Selecione os valores:</label>
                    <div class="cm-checkbox-grid" style="max-height:400px;">
                        ${filterData.values.map(value => {
                            const isChecked = selectedValues.includes(value);
                            return `
                                <label class="cm-checkbox-item">
                                    <input type="checkbox" class="modal-checkbox" 
                                           data-column="${columnName}" 
                                           value="${escapeHtml(value)}" 
                                           ${isChecked ? 'checked' : ''}>
                                    <span>${escapeHtml(value)}</span>
                                </label>
                            `;
                        }).join('')}
                    </div>
                    <div class="cm-filter-modal-actions">
                        <button type="button" class="cm-btn cm-btn-primary" id="btn-save-filter">
                            Salvar Filtro
                        </button>
                        <button type="button" class="cm-btn" id="btn-cancel-filter" style="background:#e5e7eb;">
                            Cancelar
                        </button>
                    </div>
                </div>
            `);
        }

        modalBody.append(filterHTML);
        modal.fadeIn(200);

        // Event handlers
        $('#btn-save-filter').on('click', function() {
            saveFilter(columnName, filterData);
        });

        $('#btn-cancel-filter, #filter-modal-close').on('click', function() {
            modal.fadeOut(200);
        });

        // Fecha ao clicar fora
        modal.on('click', function(e) {
            if ($(e.target).is(modal)) {
                modal.fadeOut(200);
            }
        });
    }

    // ===== SALVAR FILTRO =====
    function saveFilter(columnName, filterData) {
        if (filterData.type === 'numeric') {
            const operator = $('#modal-operator').val();
            const value = $('#modal-value').val();

            if (!operator || value === '') {
                alert('Por favor, preencha operador e valor');
                return;
            }

            currentFilters[columnName] = {
                operator: operator,
                value: value
            };
        } else if (filterData.type === 'categorical') {
            const checkedValues = [];
            $(`.modal-checkbox[data-column="${columnName}"]:checked`).each(function() {
                checkedValues.push($(this).val());
            });

            if (checkedValues.length === 0) {
                alert('Selecione pelo menos um valor');
                return;
            }

            currentFilters[columnName] = {
                operator: 'IN',
                value: checkedValues
            };
        }

        $('#filter-modal-overlay').fadeOut(200);
        renderActiveFilters();
        updateAudienceCount();
    }

    // ===== RENDERIZAR FILTROS ATIVOS (CHIPS) =====
    function renderActiveFilters() {
        const container = $('#active-filters-list');
        container.empty();

        const filtersCount = Object.keys(currentFilters).length;

        if (filtersCount === 0) {
            container.html('<span class="cm-no-filters-text">Nenhum filtro aplicado</span>');
            return;
        }

        Object.keys(currentFilters).forEach(function(columnName) {
            const filter = currentFilters[columnName];
            const filterData = window.availableFiltersData[columnName];
            
            let filterText = columnName;
            
            if (filter.operator === 'IN') {
                const valuesCount = filter.value.length;
                filterText += ` IN (${valuesCount} ${valuesCount === 1 ? 'valor' : 'valores'})`;
            } else {
                const operatorSymbols = {
                    '=': '=',
                    '!=': '≠',
                    '>': '>',
                    '<': '<',
                    '>=': '≥',
                    '<=': '≤'
                };
                filterText += ` ${operatorSymbols[filter.operator] || filter.operator} ${filter.value}`;
            }

            const chip = $(`
                <div class="cm-filter-chip" data-column="${columnName}">
                    <span class="cm-filter-chip-text">${filterText}</span>
                    <button type="button" class="cm-filter-chip-remove" data-column="${columnName}">×</button>
                </div>
            `);

            chip.find('.cm-filter-chip-remove').on('click', function() {
                removeFilter(columnName);
            });

            // Permite editar ao clicar no chip
            chip.on('click', function(e) {
                if (!$(e.target).is('.cm-filter-chip-remove')) {
                    openFilterModal(columnName, filterData);
                }
            });

            container.append(chip);
        });
    }

    // ===== REMOVER FILTRO =====
    function removeFilter(columnName) {
        delete currentFilters[columnName];
        renderActiveFilters();
        updateAudienceCount();
    }

    // ===== ESCAPE HTML =====
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== EVENT HANDLERS PARA FILTROS DINÂMICOS =====
    $(document).on('click', '#btn-add-filter', function() {
        const dropdown = $('#filter-dropdown');
        if (dropdown.is(':visible')) {
            dropdown.slideUp(200);
        } else {
            dropdown.slideDown(200);
            $('#filter-search-input').focus();
            renderFilterOptions();
        }
    });

    $(document).on('input', '#filter-search-input', function() {
        const searchTerm = $(this).val();
        renderFilterOptions(searchTerm);
    });

    // Fecha dropdown ao clicar fora
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.cm-dynamic-filters').length) {
            $('#filter-dropdown').slideUp(200);
        }
    });

    // ===== ATUALIZAR CONTAGEM =====
    function updateAudienceCount() {
        if (!selectedTable) return;
        
        $('#audience-count').text('...');
        updateBaitsInfo(); // ← Atualiza info das iscas
        
        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_get_count',
                nonce: cmAjax.nonce,
                table_name: selectedTable,
                filters: JSON.stringify(currentFilters)
            },
            success: function(response) {
                if (response.success) {
                    audienceCount = parseInt(response.data) || 0;
                    $('#audience-count').text(audienceCount.toLocaleString('pt-BR'));
                    console.log('👥 Audiência:', audienceCount);
                }
            }
        });
    }
    
    // ===== 🎯 ATUALIZAR ISCAS COMPATÍVEIS - VERSÃO MELHORADA =====
    function updateBaitsInfo() {
        console.log('🎣 Verificando iscas compatíveis...');
        
        if (!selectedTable) {
            $('#cm-baits-info-container').empty();
            return;
        }
        
        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_get_compatible_baits',
                nonce: cmAjax.nonce,
                table_name: selectedTable,
                filters: JSON.stringify(currentFilters)
            },
            success: function(response) {
                console.log('🎣 Resposta das iscas:', response);
                
                if (response.success && response.data.count > 0) {
                    const count = response.data.count;
                    const details = response.data.details || [];
                    const plural = count > 1;
                    
                    // Monta lista de iscas
                    let baitsListHTML = '';
                    if (details.length > 0) {
                        baitsListHTML = '<ul style="margin:10px 0 0 0;padding-left:20px;color:#78350f;">';
                        details.forEach(function(bait) {
                            baitsListHTML += `<li><strong>${bait.nome}</strong> (IDGIS: ${bait.idgis}) - ${bait.telefone}</li>`;
                        });
                        baitsListHTML += '</ul>';
                    }
                    
                    $('#cm-baits-info-container').html(`
                        <div class="cm-baits-info">
                            <span class="cm-baits-icon">🎣</span>
                            <div style="flex:1;">
                                <strong>${count} isca${plural ? 's' : ''} ativa${plural ? 's' : ''} compatível${plural ? 'is' : ''}</strong>
                                <p style="margin:5px 0 0 0;">Será${plural ? 'ão' : ''} adicionada${plural ? 's' : ''} automaticamente à campanha</p>
                                ${baitsListHTML}
                            </div>
                        </div>
                    `);
                    
                    console.log('✅ Iscas exibidas:', count);
                } else {
                    $('#cm-baits-info-container').html(`
                        <div class="cm-baits-warning">
                            <span style="font-size:24px;">⚠️</span>
                            <div>
                                <strong>Nenhuma isca compatível</strong>
                                <p>Não há iscas ativas com IDGIS compatível com esta tabela</p>
                            </div>
                        </div>
                    `);
                    console.log('⚠️ Nenhuma isca compatível encontrada');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Erro ao buscar iscas:', error);
            }
        });
    }

    // ===== CONTINUAR PARA STEP 3 =====
    $('#continue-to-step-3').on('click', function() {
        $('#step-3').show();
        $('html, body').animate({
            scrollTop: $('#step-3').offset().top - 100
        }, 500);
    });

    // ===== VALIDAÇÃO STEP 3 =====
    function validateStep3() {
        const templateSelected = $('#template-select').val() !== '';
        const providersSelected = $('.provider-checkbox:checked').length > 0;
        const schedulingMode = $('input[name="scheduling_mode"]:checked').val();
        const campaignName = $('#campaign-name').val().trim();

        let isValid = false;

        if (schedulingMode === 'recurring') {
            isValid = campaignName && templateSelected && providersSelected;
        } else {
            isValid = templateSelected && providersSelected;
        }

        $('#schedule-campaign-btn').prop('disabled', !isValid);
    }

    $('#template-select, #campaign-name').on('change input', validateStep3);
    $(document).on('change', '.provider-checkbox', validateStep3);
    
    $('input[name="scheduling_mode"]').on('change', function() {
        const mode = $(this).val();
        
        if (mode === 'recurring') {
            $('#recurring-options').slideDown();
            $('#exclusion-option-immediate').slideUp(); // Esconde checkbox de envio imediato
            $('#schedule-campaign-btn').html('💾 Salvar Template');
        } else {
            $('#recurring-options').slideUp();
            $('#exclusion-option-immediate').slideDown(); // Mostra checkbox de envio imediato
            $('#schedule-campaign-btn').html('🚀 Agendar Campanha');
        }
        
        validateStep3();
    });
    
    // Inicializa visibilidade correta no carregamento
    const initialMode = $('input[name="scheduling_mode"]:checked').val();
    if (initialMode === 'recurring') {
        $('#exclusion-option-immediate').hide();
    } else {
        $('#exclusion-option-immediate').show();
    }

    $('input[name="distribution_mode"]').on('change', function() {
        const mode = $(this).val();
        $('.provider-percent').prop('disabled', mode === 'all');
    });

    // ===== AGENDAR CAMPANHA =====
    $('#schedule-campaign-btn').on('click', function() {
        const btn = $(this);
        const schedulingMode = $('input[name="scheduling_mode"]:checked').val();
        
        const templateId = $('#template-select').val();
        const selectedProviders = [];
        const percentages = {};
        
        $('.provider-checkbox:checked').each(function() {
            const provider = $(this).val();
            selectedProviders.push(provider);
            const percent = parseInt($(`.provider-percent[data-provider="${provider}"]`).val()) || 0;
            percentages[provider] = percent;
        });

        const distributionMode = $('input[name="distribution_mode"]:checked').val();
        const providersConfig = {
            mode: distributionMode,
            providers: selectedProviders,
            percentages: percentages
        };

        const recordLimit = parseInt($('#record-limit').val()) || 0;

        btn.prop('disabled', true).html('<span class="cm-loading-spinner"></span> Processando...');

        // MODO TEMPLATE
        if (schedulingMode === 'recurring') {
            const campaignName = $('#campaign-name').val().trim();
            // Busca o checkbox correto (dentro do recurring-options)
            const excludeRecentPhones = $('#exclude-recent-phones').is(':checked');
            
            console.log('Salvando template com exclusão:', excludeRecentPhones);
            
            $.ajax({
                url: cmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cm_save_recurring',
                    nonce: cmAjax.nonce,
                    nome_campanha: campaignName,
                    table_name: selectedTable,
                    filters: JSON.stringify(currentFilters),
                    providers_config: JSON.stringify(providersConfig),
                    template_id: templateId,
                    record_limit: recordLimit,
                    exclude_recent_phones: excludeRecentPhones ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Template Salvo!', response.data, 'success', 3000);
                        showMessage('✅ ' + response.data, 'success');
                        btn.prop('disabled', false).html('💾 Salvar Template');
                        setTimeout(() => window.location.href = '?page=recurring-campaigns', 1500);
                    } else {
                        showToast('Erro', response.data, 'error', 5000);
                        showMessage('❌ ' + response.data, 'error');
                        btn.prop('disabled', false).html('💾 Salvar Template');
                    }
                },
                error: function() {
                    showMessage('❌ Erro de conexão', 'error');
                    btn.prop('disabled', false).text('💾 Salvar Template');
                }
            });
        } 
        // MODO IMEDIATO
        else {
            // Busca o checkbox correto (para envio imediato)
            const excludeRecentPhones = $('#exclude-recent-phones-immediate').is(':checked');
            
            $.ajax({
                url: cmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cm_schedule_campaign',
                    nonce: cmAjax.nonce,
                    table_name: selectedTable,
                    filters: JSON.stringify(currentFilters),
                    providers_config: JSON.stringify(providersConfig),
                    template_id: templateId,
                    record_limit: recordLimit,
                    exclude_recent_phones: excludeRecentPhones ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        // Mostra toast notification
                        let title = 'Campanha Agendada!';
                        let message = response.data.message;
                        if (response.data.records_skipped > 0 && response.data.exclusion_enabled) {
                            message += ` | ⚠️ ${response.data.records_skipped} telefones excluídos`;
                        }
                        showToast(title, message, 'success', 6000);
                        
                        // Mantém mensagem inline também
                        showMessage('✅ ' + response.data.message, 'success');
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        showToast('Erro', response.data, 'error', 5000);
                        showMessage('❌ ' + response.data, 'error');
                        btn.prop('disabled', false).html('🚀 Agendar Campanha');
                    }
                },
                error: function() {
                    showMessage('❌ Erro de conexão', 'error');
                    btn.prop('disabled', false).text('🚀 Agendar Campanha');
                }
            });
        }
    });

    // ===== TOAST NOTIFICATIONS =====
    function createToastContainer() {
        if ($('#cm-toast-container').length === 0) {
            $('body').append('<div id="cm-toast-container" class="cm-toast-container"></div>');
        }
    }

    function showToast(title, message, type = 'info', duration = 5000) {
        createToastContainer();
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        
        const toast = $(`
            <div class="cm-toast ${type}">
                <span class="cm-toast-icon">${icons[type] || icons.info}</span>
                <div class="cm-toast-content">
                    <div class="cm-toast-title">${title}</div>
                    <div class="cm-toast-message">${message}</div>
                </div>
                <button class="cm-toast-close" onclick="$(this).closest('.cm-toast').fadeOut(300, function() { $(this).remove(); });">×</button>
            </div>
        `);
        
        $('#cm-toast-container').append(toast);
        
        // Auto remove após duration
        if (duration > 0) {
            setTimeout(() => {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        }
        
        return toast;
    }

    // ===== PREVIEW DE MENSAGEM =====
    $('#template-select').on('change', function() {
        const templateId = $(this).val();
        
        if (templateId) {
            // Busca conteúdo completo do template via AJAX
            $.ajax({
                url: cmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cm_get_template_content',
                    nonce: cmAjax.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        updateMessagePreview(response.data.content);
                        $('#message-preview-container').slideDown(300);
                    }
                },
                error: function() {
                    console.error('Erro ao carregar template');
                }
            });
        } else {
            $('#message-preview-container').slideUp(300);
        }
    });

    function updateMessagePreview(content) {
        // Simula preview com dados de exemplo
        let preview = content;
        
        // Substitui placeholders por exemplos
        const placeholders = {
            '{nome}': 'João Silva',
            '{cpf}': '123.456.789-00',
            '{cnpj}': '12.345.678/0001-90',
            '{telefone}': '(11) 98765-4321',
            '{idgis}': '123',
            '{contrato}': '12345',
            '{data}': new Date().toLocaleDateString('pt-BR')
        };
        
        Object.keys(placeholders).forEach(placeholder => {
            preview = preview.replace(new RegExp(placeholder.replace(/[{}]/g, '\\$&'), 'g'), placeholders[placeholder]);
        });
        
        $('#message-preview').html(preview || '<em style="color:#9ca3af;">Nenhuma mensagem</em>');
        
        // Atualiza contador de caracteres
        const charCount = preview.length;
        $('#char-count').text(charCount);
        
        // Alerta se passar do limite SMS (160 caracteres)
        if (charCount > 160) {
            $('#character-count').css('color', '#ef4444');
            $('#character-count').html(`<span id="char-count" style="font-weight:bold;">${charCount}</span> / 160 caracteres <span style="color:#f59e0b;">⚠️ Mensagem longa (pode ser cobrado como múltiplas SMS)</span>`);
        } else {
            $('#character-count').css('color', '#6b7280');
            $('#character-count').html(`<span id="char-count">${charCount}</span> / 160 caracteres`);
        }
    }

    // ===== VALIDAÇÃO DE TELEFONES =====
    function validatePhone(phone) {
        if (!phone) return { valid: false, message: 'Telefone vazio' };
        
        // Remove caracteres não numéricos
        const cleaned = phone.replace(/\D/g, '');
        
        // Valida comprimento (10 ou 11 dígitos para Brasil)
        if (cleaned.length < 10 || cleaned.length > 11) {
            return { valid: false, message: 'Telefone deve ter 10 ou 11 dígitos' };
        }
        
        // Remove código do país se presente
        let phoneNumber = cleaned;
        if (phoneNumber.length > 11 && phoneNumber.startsWith('55')) {
            phoneNumber = phoneNumber.substring(2);
        }
        
        // Valida DDD (deve começar com 0 e ter 2 dígitos após)
        if (phoneNumber.length === 11 && phoneNumber[2] !== '9') {
            return { valid: false, message: 'Celular deve começar com 9 após o DDD' };
        }
        
        return { valid: true, phone: phoneNumber };
    }

    // ===== UTILS =====
    function showMessage(text, type) {
        $('#schedule-message')
            .removeClass('success error')
            .addClass(type)
            .html(text);
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Inicialização
    validateStep3();
    createToastContainer();
    console.log('✅ Campaign Manager JS inicializado');
});

// ===== ESTILOS ADICIONAIS PARA ISCAS =====
if (!document.getElementById('cm-baits-custom-styles')) {
    const style = document.createElement('style');
    style.id = 'cm-baits-custom-styles';
    style.textContent = `
        .cm-baits-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f59e0b;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .cm-baits-warning strong {
            color: #92400e;
            font-size: 16px;
            display: block;
        }
        
        .cm-baits-warning p {
            margin: 5px 0 0 0;
            color: #78350f;
            font-size: 13px;
        }
        
        .cm-baits-info ul {
            list-style: none;
            padding-left: 0;
            margin: 10px 0 0 0;
        }
        
        .cm-baits-info ul li {
            padding: 5px 0;
            border-bottom: 1px solid rgba(120, 53, 15, 0.1);
        }
        
        .cm-baits-info ul li:last-child {
            border-bottom: none;
        }
    `;
    document.head.appendChild(style);
}