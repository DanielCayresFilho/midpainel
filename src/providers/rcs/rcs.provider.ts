import { Injectable } from '@nestjs/common';
import { HttpService } from '@nestjs/axios';
import { firstValueFrom } from 'rxjs';
import { BaseProvider } from '../base/base.provider';
import {
  CampaignData,
  ProviderResponse,
  ProviderCredentials,
  RetryStrategy,
} from '../base/provider.interface';

interface RcsTemplateConfig {
  template_code?: string;
  has_media?: boolean;
  file_url?: string;
  file_type?: string;
  file_name?: string;
  fallback_sms?: string;
}

@Injectable()
export class RcsProvider extends BaseProvider {
  constructor(httpService: HttpService) {
    super(httpService, 'RcsProvider');
  }

  getRetryStrategy(): RetryStrategy {
    return {
      maxRetries: 3,
      delays: [1000, 2000, 5000],
    };
  }

  validateCredentials(credentials: ProviderCredentials): boolean {
    return !!(
      credentials.base_url &&
      credentials.token &&
      credentials.broker_code &&
      credentials.customer_code &&
      typeof credentials.base_url === 'string' &&
      typeof credentials.token === 'string'
    );
  }

  async send(
    data: CampaignData[],
    credentials: ProviderCredentials,
    templateConfig?: RcsTemplateConfig,
  ): Promise<ProviderResponse> {
    if (!this.validateCredentials(credentials)) {
      return {
        success: false,
        error: 'Credenciais inválidas: base_url, token, broker_code e customer_code são obrigatórias',
      };
    }

    if (!data || data.length === 0) {
      return {
        success: false,
        error: 'Nenhum dado para enviar',
      };
    }

    // Determina o tipo de envio e monta o payload
    let dispatchData: {
      type: string;
      endpoint: string;
      payload: any;
    };

    if (templateConfig?.template_code) {
      // Envio com TEMPLATE
      dispatchData = this.prepareTemplateDispatch(
        data,
        credentials,
        templateConfig,
      );
    } else if (templateConfig?.has_media && templateConfig?.file_url) {
      // Envio com DOCUMENTO/IMAGEM
      dispatchData = this.prepareDocumentDispatch(
        data,
        credentials,
        templateConfig,
      );
    } else {
      // Envio de TEXTO SIMPLES
      dispatchData = this.prepareTextDispatch(data, credentials);
    }

    if (dispatchData.payload.messages.length === 0) {
      return {
        success: false,
        error: 'Nenhuma mensagem válida para enviar',
      };
    }

    // Limita a 1000 mensagens por requisição
    if (dispatchData.payload.messages.length > 1000) {
      dispatchData.payload.messages = dispatchData.payload.messages.slice(
        0,
        1000,
      );
    }

    const fullUrl = `${credentials.base_url}${dispatchData.endpoint}`;
    const now = new Date();

    // Retorna sucesso, mas indica que precisa agendar o envio (15 segundos depois)
    return {
      success: true,
      message: 'Envio RCS agendado para 15 segundos',
      data: {
        type: dispatchData.type,
        url: fullUrl,
        payload: dispatchData.payload,
        scheduledAt: new Date(Date.now() + 15000).toISOString(), // 15 segundos
        totalMessages: dispatchData.payload.messages.length,
      },
    };
  }

  /**
   * Executa o envio RCS (chamado após 15 segundos)
   */
  async executeDispatch(
    endpoint: string,
    payload: any,
    credentials: ProviderCredentials,
  ): Promise<ProviderResponse> {
    if (!this.validateCredentials(credentials)) {
      return {
        success: false,
        error: 'Credenciais inválidas',
      };
    }

    const fullUrl = `${credentials.base_url}${endpoint}`;

    try {
      const response = await this.executeWithRetry(
        async () => {
          const result = await firstValueFrom(
            this.httpService.post(fullUrl, payload, {
              headers: {
                'Content-Type': 'application/json',
                authorization: credentials.token as string,
              },
              timeout: 90000, // 90 segundos
            }),
          );
          return result;
        },
        this.getRetryStrategy(),
        { provider: 'RCS' },
      );

      return {
        success: true,
        message: 'Mensagens RCS enviadas com sucesso',
        data: {
          status: response.status,
          body: response.data,
        },
      };
    } catch (error: any) {
      return this.handleError(error, { provider: 'RCS' });
    }
  }

  private prepareTextDispatch(
    data: CampaignData[],
    credentials: ProviderCredentials,
  ) {
    const now = new Date();
    const messages = data
      .filter((dado) => dado.telefone && dado.mensagem)
      .map((dado) => ({
        phone: this.normalizePhoneNumber(dado.telefone),
        document: dado.idcob_contrato || '',
        message: dado.mensagem,
        date: now.toISOString().replace('T', ' ').substring(0, 19),
      }));

    return {
      type: 'text',
      endpoint: '/v1/rcs/bulk/message/text',
      payload: {
        broker_code: credentials.broker_code,
        customer_code: credentials.customer_code,
        messages,
      },
    };
  }

  private prepareTemplateDispatch(
    data: CampaignData[],
    credentials: ProviderCredentials,
    templateConfig: RcsTemplateConfig,
  ) {
    const now = new Date();
    const messages = data
      .filter((dado) => dado.telefone)
      .map((dado) => ({
        phone: this.normalizePhoneNumber(dado.telefone),
        document: dado.idcob_contrato || '',
        template_code: templateConfig.template_code,
        variables: this.extractTemplateVariables(dado),
        date: now.toISOString().replace('T', ' ').substring(0, 19),
      }));

    return {
      type: 'template',
      endpoint: '/v1/rcs/bulk/message/template',
      payload: {
        broker_code: credentials.broker_code,
        customer_code: credentials.customer_code,
        messages,
      },
    };
  }

  private prepareDocumentDispatch(
    data: CampaignData[],
    credentials: ProviderCredentials,
    templateConfig: RcsTemplateConfig,
  ) {
    const now = new Date();
    const messages = data
      .filter((dado) => dado.telefone)
      .map((dado) => ({
        phone: this.normalizePhoneNumber(dado.telefone),
        document: dado.idcob_contrato || '',
        message: dado.mensagem || '',
        file_url: templateConfig.file_url,
        file_type: templateConfig.file_type || 'application/pdf',
        file_name: templateConfig.file_name || 'documento.pdf',
        date: now.toISOString().replace('T', ' ').substring(0, 19),
      }));

    return {
      type: 'document',
      endpoint: '/v1/rcs/bulk/message/document',
      payload: {
        broker_code: credentials.broker_code,
        customer_code: credentials.customer_code,
        messages,
      },
    };
  }

  private extractTemplateVariables(dado: CampaignData) {
    return {
      nome: dado.nome || '',
      telefone: dado.telefone || '',
      contrato: dado.idcob_contrato || '',
      cpf_cnpj: dado.cpf_cnpj || '',
      mensagem: dado.mensagem || '',
    };
  }
}

