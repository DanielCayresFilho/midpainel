import { Processor } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { WebhookService } from '../../webhook/webhook.service';
import { WhatsappOtimaProvider } from '../../providers/whatsapp-otima/whatsapp-otima.provider';
import { BaseProviderProcessor } from './base-provider.processor';

@Processor('whatsapp-otima-send')
export class WhatsappOtimaSendProcessor extends BaseProviderProcessor {
  protected readonly logger = new Logger(WhatsappOtimaSendProcessor.name);
  protected readonly providerName = 'WHATSAPP_OTIMA';

  constructor(
    protected readonly provider: WhatsappOtimaProvider,
    protected readonly prisma: PrismaService,
    protected readonly webhookService: WebhookService,
  ) {
    super(provider, prisma, webhookService);
  }
}
