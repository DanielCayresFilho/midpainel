import { Module } from '@nestjs/common';
import { BullModule } from '@nestjs/bullmq';
import { bullmqConfig, queueNames } from '../config/bullmq.config';
import { DispatchCampaignProcessor } from './dispatch-campaign.processor';
import { CampaignsModule } from '../campaigns/campaigns.module';
import { HttpModule } from '@nestjs/axios';

@Module({
  imports: [
    BullModule.forRoot(bullmqConfig),
    BullModule.registerQueue(
      { name: queueNames.DISPATCH_CAMPAIGN },
      { name: queueNames.CDA_SEND },
      { name: queueNames.GOSAC_SEND },
      { name: queueNames.GOSAC_START },
      { name: queueNames.NOAH_SEND },
      { name: queueNames.RCS_SEND },
      { name: queueNames.SALESFORCE_SEND },
      { name: queueNames.SALESFORCE_MKC },
    ),
    CampaignsModule,
    HttpModule,
  ],
  providers: [DispatchCampaignProcessor],
  exports: [BullModule],
})
export class JobsModule {}

