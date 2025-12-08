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

<style>
/* Toast Notifications */
.cm-toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.cm-toast {
    min-width: 300px;
    max-width: 400px;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease-out;
    background: white;
}
.cm-toast.success {
    border-left: 4px solid #10b981;
}
.cm-toast.error {
    border-left: 4px solid #ef4444;
}
.cm-toast.info {
    border-left: 4px solid #3b82f6;
}
.cm-toast.warning {
    border-left: 4px solid #f59e0b;
}
.cm-toast-icon {
    font-size: 24px;
    flex-shrink: 0;
}
.cm-toast-content {
    flex: 1;
}
.cm-toast-title {
    font-weight: 600;
    margin-bottom: 4px;
}
.cm-toast-message {
    font-size: 14px;
    color: #6b7280;
}
.cm-toast-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #9ca3af;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cm-toast-close:hover {
    color: #374151;
}
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
@keyframes cm-spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
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
        
        if (duration > 0) {
            setTimeout(() => {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        }
        
        return toast;
    }
    
    createToastContainer();
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
                    
                    <!-- Opção de Exclusão para Execução -->
                    <div class="cm-form-group" style="margin:15px 0;background:#fef3c7;padding:12px;border-radius:8px;border-left:4px solid #f59e0b;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; margin:0;">
                            <input type="checkbox" class="exclude-recent-execute" data-template-id="${template.id}" checked style="width: 20px; height: 20px; cursor: pointer; margin-top:2px; flex-shrink:0;">
                            <div>
                                <strong style="color:#92400e;font-size:14px;">🚫 Excluir telefones que receberam mensagem recentemente nesta execução</strong>
                                <p style="margin: 4px 0 0 0; color: #78350f; font-size: 12px;">
                                    Se marcado, telefones que receberam mensagem ontem ou hoje serão excluídos
                                </p>
                            </div>
                        </label>
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
        
        // Busca o checkbox de exclusão para este template específico
        const excludeRecent = $(`.exclude-recent-execute[data-template-id="${id}"]`).is(':checked');
        
        btn.prop('disabled', true).html('<span style="display:inline-block;width:14px;height:14px;border:2px solid #ffffff;border-top-color:transparent;border-radius:50%;animation:cm-spin 0.6s linear infinite;vertical-align:middle;margin-right:6px;"></span> Executando...');

        $.ajax({
            url: cmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'cm_execute_recurring_now',
                nonce: cmAjax.nonce,
                id: id,
                exclude_recent_phones: excludeRecent ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    // Mostra toast notification
                    let title = 'Template Executado!';
                    let message = response.data.message;
                    if (response.data.records_skipped > 0 && response.data.exclusion_enabled) {
                        message += ` | ⚠️ ${response.data.records_skipped} telefones excluídos`;
                    }
                    showToast(title, message, 'success', 6000);
                    
                    // Mantém alert para compatibilidade
                    alert('✅ ' + response.data.message);
                    loadTemplates();
                } else {
                    showToast('Erro', response.data, 'error', 5000);
                    alert('❌ ' + response.data);
                    btn.prop('disabled', false).html('▶️ Executar');
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