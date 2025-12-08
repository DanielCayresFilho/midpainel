/**
 * CPF Campaign Manager - JavaScript
 */

jQuery(document).ready(function ($) {
  let tempFileId = null;
  let selectedTable = null;
  let customFilters = {};
  let matchingField = null;

  console.log("✅ CPF Campaign Manager carregado");

  // ===== SELEÇÃO DE BASE =====

  $("#table-select").on("change", function () {
    selectedTable = $(this).val() || null;
    resetAfterTableChange();

    if (selectedTable) {
      $("#step-2").slideDown();
    } else {
      $("#step-2, #step-3, #step-4").slideUp();
    }
  });

  $("#matching-field").on("change", function () {
    matchingField = $(this).val() || null;
    updateDownloadButtonState();
  });

  function resetAfterTableChange() {
    tempFileId = null;
    customFilters = {};
    matchingField = null;

    $("#matching-field").val("");
    $("#csv-file-input").val("");
    $("#upload-preview").hide();
    $("#upload-area").show();
    resetUploadArea();
    $("#filters-container").html("<p>⏳ Carregue um arquivo primeiro</p>");
    $("#records-count").text("---");
    $("#step-3, #step-4").hide();
    updateDownloadButtonState();
  }

  // ===== UPLOAD DE ARQUIVO =====

  $("#upload-area").on("click", function () {
    if (!ensureReadyForUpload()) return;
    $("#csv-file-input").click();
  });

  $("#upload-area").on("dragover", function (e) {
    e.preventDefault();
    $(this).css("border-color", "#6366f1");
  });

  $("#upload-area").on("dragleave", function () {
    $(this).css("border-color", "#d1d5db");
  });

  $("#upload-area").on("drop", function (e) {
    e.preventDefault();
    $(this).css("border-color", "#d1d5db");
    if (!ensureReadyForUpload()) return;

    const files = e.originalEvent.dataTransfer.files;
    if (files.length > 0) {
      processFile(files[0]);
    }
  });

  $("#csv-file-input").on("change", function () {
    if (this.files.length > 0) {
      if (!ensureReadyForUpload()) return;
      processFile(this.files[0]);
    }
  });

  function ensureReadyForUpload() {
    if (!selectedTable) {
      showMessage("⚠️ Selecione a base antes de enviar o arquivo.", "error");
      return false;
    }

    if (!matchingField) {
      showMessage(
        "⚠️ Informe se o CSV contém CPFs ou telefones antes de enviar.",
        "error"
      );
      return false;
    }

    return true;
  }

  function processFile(file) {
    if (!file.name.endsWith(".csv")) {
      showMessage("❌ Apenas arquivos CSV são permitidos", "error");
      return;
    }

    const formData = new FormData();
    formData.append("action", "cpf_cm_upload_csv");
    formData.append("nonce", cpfCmAjax.nonce);
    formData.append("csv_file", file);
    formData.append("match_field", matchingField);
    formData.append("table_name", selectedTable);

    $("#upload-area").html("<p>⏳ Processando arquivo...</p>");

    $.ajax({
      url: cpfCmAjax.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          tempFileId = response.data.temp_id;
          matchingField = response.data.match_field;

          $("#upload-area").hide();
          $("#upload-preview").show();
          $("#cpf-count").text(response.data.count);
          $("#cpf-preview-list").html(
            response.data.preview.map((item) => `<div>${item}</div>`).join("")
          );

          $("#step-3").slideDown();
          loadCustomFilters(selectedTable);
          showMessage(
            `✅ ${response.data.count} registros carregados com sucesso!`,
            "success"
          );
        } else {
          showMessage("❌ " + response.data, "error");
          resetUploadArea();
        }
      },
      error: function () {
        showMessage("❌ Erro ao processar arquivo", "error");
        resetUploadArea();
      },
    });
  }

  $("#clear-upload").on("click", function () {
    tempFileId = null;
    selectedTable = null;
    customFilters = {};
    matchingField = null;

    $("#upload-preview").hide();
    $("#upload-area").show();
    resetUploadArea();

    $("#table-select").val("");
    $("#matching-field").val("");
    $("#step-2, #step-3, #step-4").hide();
    $("#csv-file-input").val("");
    $("#filters-container").html("<p>⏳ Selecione uma base e envie um arquivo</p>");
    $("#records-count").text("---");
    updateDownloadButtonState();
  });

  function resetUploadArea() {
    $("#upload-area").html(`
            <div class="cpf-cm-upload-icon">📄</div>
            <p><strong>Clique para selecionar</strong> ou arraste o arquivo aqui</p>
            <p class="cpf-cm-upload-hint">Apenas arquivos .csv (máx 10MB)</p>
        `);
  }

  // ===== FILTROS =====

  function loadCustomFilters(tableName) {
    $("#filters-container").html("<p>⏳ Carregando filtros...</p>");

    $.ajax({
      url: cpfCmAjax.ajax_url,
      type: "POST",
      data: {
        action: "cpf_cm_get_custom_filters",
        nonce: cpfCmAjax.nonce,
        table_name: tableName,
      },
      success: function (response) {
        if (response.success) {
          renderFilters(response.data);
          $("#step-4").slideDown();
          updatePreviewCount();
          updateDownloadButtonState();
        } else {
          $("#filters-container").html("<p>❌ Erro ao carregar filtros</p>");
        }
      },
    });
  }

  function renderFilters(filters) {
    const container = $("#filters-container");
    container.empty();

    if (Object.keys(filters).length === 0) {
      container.html("<p>✅ Nenhum filtro adicional disponível</p>");
      return;
    }

    Object.keys(filters).forEach(function (columnName) {
      const filterData = filters[columnName];

      const filterDiv = $('<div class="cpf-cm-filter-group"></div>');
      filterDiv.append(`<strong>${columnName}</strong>`);

      const checkboxGrid = $('<div class="cpf-cm-checkbox-grid"></div>');

      filterData.values.forEach(function (value) {
        const item = $(`
                    <label class="cpf-cm-checkbox-item">
                        <input type="checkbox" class="filter-checkbox" 
                               data-column="${columnName}" value="${value}">
                        <span>${value}</span>
                    </label>
                `);
        checkboxGrid.append(item);
      });

      filterDiv.append(checkboxGrid);
      container.append(filterDiv);
    });

    $(document).off("change", ".filter-checkbox");
    $(document).on("change", ".filter-checkbox", function () {
      const column = $(this).data("column");
      const checkedValues = [];

      $(`.filter-checkbox[data-column="${column}"]:checked`).each(function () {
        checkedValues.push($(this).val());
      });

      if (checkedValues.length > 0) {
        customFilters[column] = checkedValues;
      } else {
        delete customFilters[column];
      }

      updatePreviewCount();
    });
  }

  // ===== PREVIEW DE CONTAGEM =====

  function updatePreviewCount() {
    if (!tempFileId || !selectedTable || !matchingField) return;

    $("#records-count").text("⏳");

    $.ajax({
      url: cpfCmAjax.ajax_url,
      type: "POST",
      data: {
        action: "cpf_cm_preview_count",
        nonce: cpfCmAjax.nonce,
        temp_id: tempFileId,
        table_name: selectedTable,
        filters: JSON.stringify(customFilters),
        match_field: matchingField,
      },
      success: function (response) {
        if (response.success) {
          const count = response.data.count;
          $("#records-count").text(count.toLocaleString("pt-BR"));

          if (count === 0) {
            showMessage(
              "⚠️ Nenhum registro encontrado com os filtros aplicados",
              "error"
            );
          }
        }
      },
      error: function () {
        $("#records-count").text("---");
        showMessage("❌ Erro ao calcular a contagem", "error");
      },
    });
  }

  // ===== DOWNLOAD =====

  $("#download-clean-file-btn").on("click", function () {
    if (!tempFileId || !selectedTable || !matchingField) {
      showMessage(
        "⚠️ Selecione a base, o tipo de dado e envie o CSV para continuar.",
        "error"
      );
      return;
    }

    const btn = $(this);
    btn.prop("disabled", true).text("⏳ Gerando arquivo...");

    $.ajax({
      url: cpfCmAjax.ajax_url,
      type: "POST",
      data: {
        action: "cpf_cm_generate_clean_file",
        nonce: cpfCmAjax.nonce,
        temp_id: tempFileId,
        table_name: selectedTable,
        filters: JSON.stringify(customFilters),
        match_field: matchingField,
      },
      success: function (response) {
        if (response.success) {
          downloadBase64File(response.data.file, response.data.filename);
          showMessage("✅ Arquivo gerado com sucesso!", "success");
        } else {
          showMessage("❌ " + response.data, "error");
        }
      },
      error: function () {
        showMessage("❌ Erro ao gerar o arquivo", "error");
      },
      complete: function () {
        btn.prop("disabled", false).text("⬇️ Baixar arquivo limpo");
      },
    });
  });

  function updateDownloadButtonState() {
    const isReady = !!(tempFileId && selectedTable && matchingField);
    $("#download-clean-file-btn").prop("disabled", !isReady);
  }

  function downloadBase64File(base64Data, filename) {
    try {
      const binary = atob(base64Data);
      const len = binary.length;
      const bytes = new Uint8Array(len);
      for (let i = 0; i < len; i++) {
        bytes[i] = binary.charCodeAt(i);
      }
      const blob = new Blob([bytes], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
    } catch (error) {
      console.error("Erro ao baixar arquivo:", error);
      showMessage("❌ Não foi possível baixar o arquivo", "error");
    }
  }

  // ===== UTILS =====

  function showMessage(text, type) {
    $("#cpf-cm-message")
      .removeClass("success error")
      .addClass(type)
      .html(text)
      .show();

    $("html, body").animate({ scrollTop: 0 }, 300);

    setTimeout(() => {
      $("#cpf-cm-message").fadeOut();
    }, 5000);
  }

  console.log("✅ CPF Campaign Manager inicializado");
});
