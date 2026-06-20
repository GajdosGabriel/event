import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { timeout } from 'rxjs';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { PaginatorComponent } from '../../../shared/components/paginator/paginator.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { IndexItemAction, IndexItemComponent } from '../../../shared/components/index-item/index-item.component';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { VenueItem } from '../models/venue.model';
import { VenueListScope, VenuesApiService } from '../services/venues-api.service';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { FilterParams } from '../../../shared/models/filter-params.model';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-venue-index-page',
  imports: [PaginatorComponent, RouterLink, IndexShellComponent, ErrorBannerComponent, IndexItemComponent, MunicipalityOverviewComponent],
  templateUrl: './venue-index.page.html',
  styleUrl: './venue-index.page.css'
})
export class VenueIndexPage implements OnInit {
  private readonly venuesApi = inject(VenuesApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly venues = signal<VenueItem[]>([]);
  protected readonly loading = signal(true);
  protected readonly pageSize = 6;
  protected readonly currentPage = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly canCreate = signal(true);
  protected readonly errorMessage = signal('');
  protected readonly activeFilters = signal<FilterParams>({});
  protected readonly currentPerPage = signal(this.pageSize);
  protected readonly defaultPerPage = signal(this.pageSize);
  protected readonly defaultPerPageLoading = signal(true);
  protected readonly getModelStatusLabel = getModelStatusLabel;

  protected get routePrefix(): '/dashboard' | '/admin' {
    return this.router.url.startsWith('/admin') ? '/admin' : '/dashboard';
  }

  protected get venuesScope(): VenueListScope {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  protected getMetaItems(venue: VenueItem): string[] {
    return [venue.street, venue.postcode, this.getModelStatusLabel(venue.status)].filter(
      (item): item is string => Boolean(item && item.trim())
    );
  }

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: 'Miesta',
      description: 'Prehľad miest a ich základných atribútov v dashboarde.'
    });

    this.route.queryParamMap.subscribe((query) => {
      const filters: FilterParams = {};
      const published = query.get('published');
      const unpublished = query.get('unpublished');
      const blocked = query.get('blocked');
      const status = query.get('status');
      const deleted = query.get('deleted');
      const search = query.get('search');

      if (published !== null) {
        filters.published = published;
      }
      if (unpublished !== null) {
        filters.unpublished = unpublished;
      }
      if (blocked !== null) {
        filters.blocked = blocked;
      }
      if (status) {
        filters.status = status;
      }
      if (deleted !== null) {
        filters.deleted = deleted;
      }
      if (search) {
        filters.search = search;
      }

      const municipality = query.get('municipality');
      if (municipality !== null) {
        filters.municipality = municipality;
      }

      const perPage = this.resolvePerPage(query.get('per_page'));

      this.activeFilters.set(filters);
      this.defaultPerPageLoading.set(perPage === undefined);
      this.currentPerPage.set(perPage ?? this.defaultPerPage());
      this.currentPage.set(1);
      this.loadPage(1, perPage, filters);
    });
  }

  protected onPageChange(page: number): void {
    this.currentPage.set(page);
    this.loadPage(page, this.currentPerPage(), this.activeFilters());
  }

  protected onVenueAction(payload: IndexItemAction, venue: VenueItem): void {
    if (payload.id === null) {
      return;
    }

    switch (payload.action) {
      case 'delete': {
        const deletedAt = this.normalizeNullableDateTime(venue.deletedAt);
        const isDeleted = deletedAt !== null;

        if (isDeleted) {
          this.venuesApi
            .restore(payload.id, this.venuesScope)
            .pipe(timeout(10000))
            .subscribe({
              next: () => {
                this.loadPage(this.currentPage(), this.currentPerPage(), this.activeFilters());
              },
              error: (error) => {
                this.errorMessage.set(
                  resolveApiErrorMessage(error, 'Nepodarilo sa obnovit zmazane miesto.')
                );
              }
            });
          return;
        }

        const shouldDelete = confirm('Naozaj chcete zmazat toto miesto?');
        if (!shouldDelete) {
          return;
        }

        this.venuesApi
          .delete(payload.id, this.venuesScope)
          .pipe(timeout(10000))
          .subscribe({
            next: () => {
              this.loadPage(this.currentPage(), this.currentPerPage(), this.activeFilters());
            },
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav zmazania miesta.')
              );
            }
          });
        break;
      }
      case 'status': {
        this.venuesApi
          .togglePublishedStatus(venue, this.venuesScope)
          .pipe(timeout(10000))
          .subscribe({
            next: (updated) => {
              this.venues.update((items) =>
                items.map((item) => (item.id === payload.id ? updated : item))
              );
            },
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav publikovania miesta.')
              );
            }
          });
        break;
      }
    }
  }

  private loadPage(page: number, perPage: number | undefined, filters: FilterParams): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.venuesApi
      .index(page, perPage, filters, this.venuesScope)
      .pipe(timeout(10000))
      .subscribe({
        next: (result) => {
          this.venues.set(result.items);
          this.currentPage.set(result.currentPage);
          this.currentPerPage.set(result.perPage);
          if (perPage === undefined) {
            this.defaultPerPage.set(result.perPage);
          }
          this.defaultPerPageLoading.set(false);
          this.totalPages.set(result.totalPages);
          this.canCreate.set(result.permissions.create);
          this.loading.set(false);
        },
        error: () => {
          this.venues.set([]);
          this.totalPages.set(1);
          this.canCreate.set(false);
          this.defaultPerPageLoading.set(false);
          this.loading.set(false);
          this.errorMessage.set('Nepodarilo sa nacitat venues z API (timeout/chyba spojenia).');
        }
      });
  }

  private resolvePerPage(rawPerPage: string | null): number | undefined {
    const parsed = Number.parseInt(rawPerPage ?? '', 10);
    if (!Number.isFinite(parsed) || parsed <= 0) {
      return undefined;
    }

    return parsed;
  }

  private normalizeNullableDateTime(value: string | null | undefined): string | null {
    if (value === null || value === undefined) {
      return null;
    }

    const normalized = value.trim().toLowerCase();
    if (!normalized || normalized === 'null' || normalized === 'undefined') {
      return null;
    }

    return value;
  }
}
