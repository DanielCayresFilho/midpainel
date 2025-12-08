// Arquivo: js/dashboard.js
document.addEventListener("DOMContentLoaded", function () {
  const refreshButton = document.getElementById("refresh-jobs-button");
  const tableBody = document.getElementById("jobs-table-body");
  const messageDiv = document.getElementById("campaign-message");

  // Busca campanhas via AJAX
  async function fetchCampaigns() {
    // Ajusta o colspan para o número correto de colunas (8)
    tableBody.innerHTML = '<tr><td colspan="8" class="loading-cell">Carregando...</td></tr>';
    messageDiv.style.display = "none";

    try {
      const formData = new URLSearchParams();
      formData.append("action", "ga_get_campaigns");
      formData.append("nonce", ga_ajax.nonce);

      const response = await fetch(ga_ajax.ajax_url, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("A resposta da rede não foi bem-sucedida");
      const result = await response.json();
      if (!result.success)
        throw new Error(result.data || "Falha ao buscar campanhas.");

      const campaigns = result.data;
      tableBody.innerHTML = "";
      if (campaigns.length === 0) {
        // Mensagem de "nenhuma campanha" também com o colspan correto
        tableBody.innerHTML =
          '<tr><td colspan="8" class="empty-cell">Nenhuma campanha para exibir no momento.</td></tr>';
        return;
      }

      campaigns.forEach((campaign) => {
        const row = document.createElement("tr");

        // Se não houver ação, exibe um traço (—), que é mais limpo que "N/A"
        let actionsHtml = "—";
        if (campaign.status === "pendente_aprovacao") {
          actionsHtml = `
            <button class="button button-primary approve-btn" data-id="${campaign.agendamento_id}">Aprovar</button>
            <button class="button button-secondary deny-btn" data-id="${campaign.agendamento_id}">Negar</button>
          `;
        }

        // Template HTML da linha, agora com as novas colunas e classes
        row.innerHTML = `
          <td>${campaign.agendamento_id}</td>
          <td>${campaign.idgis_ambiente}</td>
          <td>${campaign.provider}</td>
          <td>
            <span class="status-badge status-${campaign.status}">
              ${campaign.status.replace(/_/g, ' ')}
            </span>
          </td>
          <td>${campaign.total_clients}</td>
          <td>${new Date(campaign.created_at).toLocaleString('pt-BR')}</td>
          <td>${campaign.scheduled_by}</td>
          <td class="campaign-actions actions-cell">${actionsHtml}</td>
        `;
        tableBody.appendChild(row);
      });
    } catch (error) {
      tableBody.innerHTML = `<tr><td colspan="8" class="empty-cell">Falha ao carregar dados: ${error.message}</td></tr>`;
    }
  }

  // Dispara ação de Aprovar/Negar via PHP
  async function handleCampaignAction(agendamentoId, campaignAction) {
    try {
      const formData = new URLSearchParams();
      formData.append("action", "ga_handle_action");
      formData.append("nonce", ga_ajax.nonce);
      formData.append("agendamento_id", agendamentoId);
      formData.append("campaign_action", campaignAction);

      const response = await fetch(ga_ajax.ajax_url, {
        method: "POST",
        body: formData,
      });

      if (!response.ok) throw new Error("A resposta da rede não foi bem-sucedida");
      const result = await response.json();

      messageDiv.style.display = "block";
      if (result.success) {
        messageDiv.className = "notice notice-success is-dismissible";
        messageDiv.textContent = result.data;
        fetchCampaigns(); // Recarrega a lista
      } else {
        messageDiv.className = "notice notice-error is-dismissible";
        messageDiv.textContent = "Erro: " + result.data;
      }
    } catch (error) {
      messageDiv.style.display = "block";
      messageDiv.className = "notice notice-error is-dismissible";
      messageDiv.textContent = "Erro: " + error.message;
    }
  }

  // Botão de refresh
  refreshButton.addEventListener("click", fetchCampaigns);

  // Delegação de eventos para Aprovar/Negar
  tableBody.addEventListener("click", function (event) {
    const target = event.target;

    if (target.classList.contains("approve-btn")) {
      const agendamentoId = target.dataset.id;
      if (confirm(`Tem certeza que deseja APROVAR a campanha ${agendamentoId}?`)) {
        handleCampaignAction(agendamentoId, "approve");
      }
    } else if (target.classList.contains("deny-btn")) {
      const agendamentoId = target.dataset.id;
      if (confirm(`Tem certeza que deseja NEGAR a campanha ${agendamentoId}?`)) {
        handleCampaignAction(agendamentoId, "deny");
      }
    }
  });

  // Carrega campanhas ao abrir a página
  fetchCampaigns();
});