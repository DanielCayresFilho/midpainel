import { Processor } from '@nestjs/bullmq';
import { Logger } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { WebhookService } from '../../webhook/webhook.service';
import { RcsOtimaProvider } from '../../providers/rcs-otima/rcs-otima.provider';
import { BaseProviderProcessor } from './base-provider.processor';

@Processor('rcs-otima-send')
export class RcsOtimaSendProcessor extends BaseProviderProcessor {
  protected readonly logger = new Logger(RcsOtimaSendProcessor.name);
  protected readonly providerName = 'RCS_OTIMA';

  constructor(
    protected readonly provider: RcsOtimaProvider,
    protected readonly prisma: PrismaService,
    protected readonly webhookService: WebhookService,
  ) {
    super(provider, prisma, webhookService);
  }
}
