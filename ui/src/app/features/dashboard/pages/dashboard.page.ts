import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-dashboard-page',
  imports: [RouterLink, MunicipalityOverviewComponent],
  templateUrl: './dashboard.page.html',
  styleUrl: './dashboard.page.css'
})
export class DashboardPage {
  private readonly pageMeta = inject(PageMetaService);

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Dashboard',
      description: 'Prehľad kanálov, miest a podujatí v dashboarde.'
    });
  }
}
