import { Injectable, Logger } from '@nestjs/common';
import { HttpService } from '@nestjs/axios';
import { firstValueFrom } from 'rxjs';
import { IProvider, ProviderResponse, ProviderCredentials, CampaignData, ErrorType, RetryStrategy } from './provider.interface';

@Injectable()
export abstract class BaseProvider implements IProvider {
  protected readonly logger: Logger;

  constructor(protected readonly httpService: HttpService, providerName: string) {
    this.logger = new Logger(providerName);
  }

  abstract send(data: CampaignData[], credentials: ProviderCredentials): Promise<ProviderResponse>;
  
  abstract validateCredentials(credentials: ProviderCredentials): boolean;

  abstract getRetryStrategy(): RetryStrategy;

  protected async executeWithRetry<T>(
    fn: () => Promise<T>,
    retryStrategy: RetryStrategy,
    context?: { agendamentoId?: string; provider?: string }
  ): Promise<T> {
    let lastError: Error | undefined;
    
    for (let attempt = 0; attempt <= retryStrategy.maxRetries; attempt++) {
      try {
        if (attempt > 0) {
          const delay = retryStrategy.delays[attempt - 1] || retryStrategy.delays[retryStrategy.delays.length - 1];
          this.logger.warn(
            `Retry attempt ${attempt}/${retryStrategy.maxRetries} after ${delay}ms`,
            context
          );
          await this.sleep(delay);
        }
        
        return await fn();
      } catch (error: any) {
        lastError = error;
        const errorType = this.classifyError(error);
        
        // Não retry para erros 4xx ou validação
        if (errorType === ErrorType.API_ERROR_4XX || errorType === ErrorType.VALIDATION_ERROR) {
          this.logger.error(`Non-retryable error: ${error.message}`, error.stack, context);
          throw error;
        }
        
        // Se ainda temos tentativas, continua
        if (attempt < retryStrategy.maxRetries) {
          this.logger.warn(
            `Attempt ${attempt + 1} failed: ${error.message}. Will retry.`,
            context
          );
          continue;
        }
      }
    }
    
    // Todas as tentativas falharam
    if (lastError) {
      this.logger.error(
        `All ${retryStrategy.maxRetries + 1} attempts failed. Last error: ${lastError.message}`,
        lastError.stack,
        context
      );
      throw lastError;
    }
    
    // Caso extremo: nenhuma tentativa foi feita
    throw new Error('No attempts were made');
  }

  protected classifyError(error: any): ErrorType {
    if (error.code === 'ECONNREFUSED' || error.code === 'ETIMEDOUT' || error.code === 'ENOTFOUND') {
      return ErrorType.NETWORK_ERROR;
    }
    
    if (error.response) {
      const status = error.response.status;
      if (status >= 400 && status < 500) {
        return ErrorType.API_ERROR_4XX;
      }
      if (status >= 500) {
        return ErrorType.API_ERROR_5XX;
      }
    }
    
    if (error.message?.includes('timeout') || error.code === 'ETIMEDOUT') {
      return ErrorType.TIMEOUT;
    }
    
    return ErrorType.NETWORK_ERROR;
  }

  protected async sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  protected handleError(error: any, context?: { agendamentoId?: string; provider?: string }): ProviderResponse {
    const errorType = this.classifyError(error);
    const errorMessage = error.response?.data?.message || error.message || 'Unknown error';
    
    this.logger.error(`Provider error (${errorType}): ${errorMessage}`, error.stack, context);
    
    return {
      success: false,
      error: errorMessage,
    };
  }
}

