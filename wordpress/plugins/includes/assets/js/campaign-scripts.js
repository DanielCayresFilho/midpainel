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

    // ===== RENDERIZAR FILTROS =====
    function renderFilters(filters) {
        const container = $('#filters-container');
        container.empty();

        if (Object.keys(filters).length === 0) {
            container.html('<p>✅ Nenhum filtro disponível. Todos os registros serão incluídos.</p>');
            return;
        }

        Object.keys(filters).forEach(function(columnName) {
            const filterData = filters[columnName];
            const filterDiv = $('<div style="margin-bottom:20px;"></div>');
            
            filterDiv.append(`<strong>${columnName}:</strong><br>`);

            // FILTRO NUMÉRICO
            if (filterData.type === 'numeric') {
                const numericDiv = $('<div style="display:flex;gap:10px;margin-top:8px;"></div>');
                
                const operatorSelect = $(`
                    <select class="cm-select filter-operator" data-column="${columnName}" style="width:100px;">
                        <option value="">--</option>
                        <option value="=">=</option>
                        <option value="!=">≠</option>
                        <option value=">">></option>
                        <option value="<"><</option>
                        <option value=">=">≥</option>
                        <option value="<=">≤</option>
                    </select>
                `);
                
                const valueInput = $(`
                    <input type="number" class="cm-input filter-value" 
                           data-column="${columnName}" placeholder="Valor" disabled 
                           style="flex:1;">
                `);

                operatorSelect.on('change', function() {
                    valueInput.prop('disabled', !$(this).val());
                    if (!$(this).val()) {
                        valueInput.val('');
                        delete currentFilters[columnName];
                        updateAudienceCount();
                    }
                });

                valueInput.on('input', debounce(function() {
                    const operator = operatorSelect.val();
                    const value = $(this).val();
                    
                    if (operator && value !== '') {
                        currentFilters[columnName] = {
                            operator: operator,
                            value: value
                        };
                        updateAudienceCount();
                    }
                }, 500));

                numericDiv.append(operatorSelect).append(valueInput);
                filterDiv.append(numericDiv);
            } 
            // FILTRO CATEGÓRICO (CHECKBOXES)
            else if (filterData.type === 'categorical') {
                const checkboxGrid = $('<div class="cm-checkbox-grid"></div>');
                
                filterData.values.forEach(function(value) {
                    const checkboxItem = $(`
                        <label class="cm-checkbox-item">
                            <input type="checkbox" class="filter-checkbox" 
                                   data-column="${columnName}" value="${value}">
                            <span>${value}</span>
                        </label>
                    `);
                    checkboxGrid.append(checkboxItem);
                });

                checkboxGrid.on('change', '.filter-checkbox', function() {
                    const column = $(this).data('column');
                    const checkedValues = [];
                    
                    $(`.filter-checkbox[data-column="${column}"]:checked`).each(function() {
                        checkedValues.push($(this).val());
                    });

                    if (checkedValues.length > 0) {
                        currentFilters[column] = {
                            operator: 'IN',
                            value: checkedValues
                        };
                    } else {
                        delete currentFilters[column];
                    }
                    
                    updateAudienceCount();
                });

                filterDiv.append(checkboxGrid);
            }

            container.append(filterDiv);
        });
    }

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
            $('#schedule-campaign-btn').text('💾 Salvar Template');
        } else {
            $('#recurring-options').slideUp();
            $('#schedule-campaign-btn').text('🚀 Agendar Campanha');
        }
        
        validateStep3();
    });

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

        btn.prop('disabled', true).text('⏳ Processando...');

        // MODO TEMPLATE
        if (schedulingMode === 'recurring') {
            const campaignName = $('#campaign-name').val().trim();
            
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
                    record_limit: recordLimit
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('✅ ' + response.data, 'success');
                        setTimeout(() => window.location.href = '?page=recurring-campaigns', 1500);
                    } else {
                        showMessage('❌ ' + response.data, 'error');
                        btn.prop('disabled', false).text('💾 Salvar Template');
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
                    record_limit: recordLimit
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('✅ ' + response.data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showMessage('❌ ' + response.data, 'error');
                        btn.prop('disabled', false).text('🚀 Agendar Campanha');
                    }
                },
                error: function() {
                    showMessage('❌ Erro de conexão', 'error');
                    btn.prop('disabled', false).text('🚀 Agendar Campanha');
                }
            });
        }
    });

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