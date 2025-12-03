import { Processor } from '@nestjs/bullmq';
import { SalesforceProvider } from '../../providers/salesforce/salesforce.provider';
import { PrismaService } from '../../prisma/prisma.service';
import { WebhookService } from '../../webhook/webhook.service';
import { BaseProviderProcessor, ProviderSendJobData } from './base-provider.processor';
import { queueNames } from '../../config/bullmq.config';
import { Job } from 'bullmq';

@Processor(queueNames.SALESFORCE_SEND)
export class SalesforceSendProcessor extends BaseProviderProcessor {
  protected providerName = 'SALESFORCE';

  constructor(
    provider: SalesforceProvider,
    prisma: PrismaService,
    webhookService: WebhookService,
  ) {
    super(provider, prisma, webhookService, SalesforceSendProcessor.name);
  }

  async process(job: Job<ProviderSendJobData>) {
    return super.process(job);
  }
}

