import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-admin-index-page',
  imports: [RouterLink, IndexShellComponent, MunicipalityOverviewComponent],
  templateUrl: './admin-index.page.html',
  styleUrl: './admin-index.page.css'
})
export class AdminIndexPage {
  private readonly pageMeta = inject(PageMetaService);

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Admin',
      description: 'Administrácia aplikácie, správa podujatí a systémových dát.'
    });
  }
}
