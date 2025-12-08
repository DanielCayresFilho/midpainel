<?php
/**
 * Template: Templates Salvos (Campanhas Recorrentes)
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap cm-wrap">
    <div class="cm-header">
        <h1>📋 Templates Salvos</h1>
        <p>Execute seus templates de campanha quando quiser</p>
    </div>

    <div id="recurring-campaigns-list"></div>
</div>

<script>
jQuery(document).ready(function($) {
    loadTemplates();

    function loadTemplates() {
        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_get_recurring',
                nonce: cmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderTemplates(response.data);
                }
            }
        });
    }

    function renderTemplates(templates) {
        const container = $('#recurring-campaigns-list');
        container.empty();

        if (templates.length === 0) {
            container.html('<div class="cm-card"><p>Nenhum template salvo ainda.</p></div>');
            return;
        }

        templates.forEach(function(template) {
            const providersConfig = JSON.parse(template.providers_config);
            const providers = providersConfig.providers.join(', ');
            const statusBadge = template.ativo == 1 
                ? '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:12px;font-size:11px;font-weight:700;">✓ ATIVO</span>' 
                : '<span style="background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:12px;font-size:11px;font-weight:700;">✗ INATIVO</span>';

            const card = $(`
                <div class="cm-card" style="margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;">
                        <div>
                            <h3 style="margin:0;font-size:18px;color:#6366f1;">${template.nome_campanha}</h3>
                            <div style="font-size:13px;color:#6b7280;margin-top:5px;">
                                📊 ${template.tabela_origem} | 🌐 ${providers}
                            </div>
                        </div>
                        ${statusBadge}
                    </div>
                    
                    <div style="background:#dbeafe;padding:10px;border-radius:8px;margin:15px 0;text-align:center;" data-id="${template.id}">
                        <strong style="font-size:20px;color:#1e40af;" class="template-count">⏳ Carregando...</strong>
                    </div>
                    
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button class="cm-btn cm-btn-primary execute-now" data-id="${template.id}">
                            ▶️ Executar
                        </button>
                        <button class="cm-btn toggle-status" data-id="${template.id}" data-ativo="${template.ativo}"
                                style="background:#f3f4f6;color:#374151;">
                            ${template.ativo == 1 ? '⏸️ Desativar' : '▶️ Ativar'}
                        </button>
                        <button class="cm-btn delete-template" data-id="${template.id}"
                                style="background:#fee2e2;color:#991b1b;">
                            🗑️ Deletar
                        </button>
                    </div>
                </div>
            `);

            container.append(card);
            loadCount(template.id);
        });
    }

    function loadCount(id) {
        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_preview_recurring_count',
                nonce: cmAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    const count = response.data.count.toLocaleString('pt-BR');
                    $(`.cm-card [data-id="${id}"] .template-count`).text(`👥 ${count} registros`);
                }
            }
        });
    }

    $(document).on('click', '.execute-now', function() {
        if (!confirm('⚡ Executar este template agora?')) return;

        const id = $(this).data('id');
        const btn = $(this);
        
        btn.prop('disabled', true).text('⏳ Executando...');

        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_execute_recurring_now',
                nonce: cmAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    loadTemplates();
                } else {
                    alert('❌ ' + response.data);
                    btn.prop('disabled', false).text('▶️ Executar');
                }
            }
        });
    });

    $(document).on('click', '.toggle-status', function() {
        const id = $(this).data('id');
        const ativo = $(this).data('ativo') == 1 ? 0 : 1;

        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_toggle_recurring',
                nonce: cmAjax.nonce,
                id: id,
                ativo: ativo
            },
            success: function(response) {
                if (response.success) {
                    loadTemplates();
                }
            }
        });
    });

    $(document).on('click', '.delete-template', function() {
        if (!confirm('⚠️ Deletar este template? Esta ação não pode ser desfeita!')) return;

        const id = $(this).data('id');

        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_delete_recurring',
                nonce: cmAjax.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    loadTemplates();
                }
            }
        });
    });
});
</script>