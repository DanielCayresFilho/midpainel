import { Module } from '@nestjs/common';
import { CdaModule } from './cda/cda.module';
import { GosacModule } from './gosac/gosac.module';
import { NoahModule } from './noah/noah.module';
import { RcsModule } from './rcs/rcs.module';
import { SalesforceModule } from './salesforce/salesforce.module';

@Module({
  imports: [CdaModule, GosacModule, NoahModule, RcsModule, SalesforceModule],
  exports: [CdaModule, GosacModule, NoahModule, RcsModule, SalesforceModule],
})
export class ProvidersModule {}
