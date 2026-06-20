import { isPlatformBrowser } from '@angular/common';
import { Component, inject, OnInit, PLATFORM_ID, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { EventItem } from '../../events/models/event.model';
import { EventsApiService } from '../../events/services/events-api.service';
import { EventCardComponent } from '../../../shared/components/event-card/event-card.component';
import { PaginatorComponent } from '../../../shared/components/paginator/paginator.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { timeout } from 'rxjs';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { FilterParams } from '../../../shared/models/filter-params.model';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-home-page',
  imports: [EventCardComponent, PaginatorComponent, ErrorBannerComponent, MunicipalityOverviewComponent],
  templateUrl: './home.page.html',
  styleUrl: './home.page.css'
})
export class HomePage implements OnInit {
  private readonly eventsApi = inject(EventsApiService);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly route = inject(ActivatedRoute);
  private readonly pageMeta = inject(PageMetaService);
  protected readonly getModelStatusLabel = getModelStatusLabel;

  protected readonly events = signal<EventItem[]>([]);
  protected readonly pageSize = 6;
  protected readonly currentPage = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly loading = signal(true);
  protected readonly errorMessage = signal('');
  protected readonly activeFilters = signal<FilterParams>({});

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: 'Nadchádzajúce podujatia',
      description: 'Prehľad najbližších podujatí na verejnej stránke.'
    });

    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    this.route.queryParamMap.subscribe((query) => {
      const filters: FilterParams = {};
      const municipality = query.get('municipality');
      if (municipality !== null) {
        filters.municipality = municipality;
      }
      this.activeFilters.set(filters);
      this.currentPage.set(1);
      this.loadPage(1, filters);
    });
  }

  protected onPageChange(page: number): void {
    this.currentPage.set(page);
    this.loadPage(page, this.activeFilters());
  }

  private loadPage(page: number, filters: FilterParams = {}): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.eventsApi.publicIndex(page, this.pageSize, filters).pipe(timeout(10000)).subscribe({
      next: (result) => {
        this.events.set(result.items);
        this.currentPage.set(result.currentPage);
        this.totalPages.set(result.totalPages);
        this.loading.set(false);
        this.pageMeta.setPageMeta({
          title: 'Nadchádzajúce podujatia',
          description:
            result.items[0]?.body || 'Prehlad najblizsich podujati na verejnej stranke.'
        });
      },
      error: () => {
        this.events.set([]);
        this.totalPages.set(1);
        this.loading.set(false);
        this.errorMessage.set('Nepodarilo sa nacitat eventy z API (timeout/chyba spojenia).');
        this.pageMeta.setPageMeta({
          title: 'Nadchádzajúce podujatia',
          description: 'Nepodarilo sa nacitat verejne podujatia.'
        });
      }
    });
  }
}
