import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { timeout } from 'rxjs';
import { EventItem } from '../models/event.model';
import { EventsApiService } from '../services/events-api.service';
import { PaginatorComponent } from '../../../shared/components/paginator/paginator.component';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { MunicipalityOverviewScope } from '../../../shared/services/municipalities-overview-api.service';
import { IndexItemAction, IndexItemComponent } from '../../../shared/components/index-item/index-item.component';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { FilterParams } from '../../../shared/models/filter-params.model';
import { PageMetaService } from '../../../shared/services/page-meta.service';
import { CompletionIndicatorComponent } from '../../../shared/components/completion-indicator/completion-indicator.component';



@Component({
  selector: 'app-event-index.page',
  imports: [
    PaginatorComponent,
    IndexItemComponent,
    RouterLink,
    IndexShellComponent,
    ErrorBannerComponent,
    MunicipalityOverviewComponent,
    DatePipe,
    CompletionIndicatorComponent
  ],
  templateUrl: './event-index.page.html',
  styleUrl: './event-index.page.css',
})
export class EventIndexPage implements OnInit {
  private readonly eventsApi = inject(EventsApiService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly events = signal<EventItem[]>([]);
  protected readonly loading = signal(true);
  protected readonly currentPage = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly canCreate = signal(true);
  protected readonly errorMessage = signal('');
  protected readonly activeFilters = signal<FilterParams>({});
  protected readonly currentPerPage = signal(this.pageSize);
  protected readonly defaultPerPage = signal(this.pageSize);
  protected readonly defaultPerPageLoading = signal(false);
  protected readonly publishedCount = computed(() =>
    this.events().filter((event) => !!event.publishedAt && !event.deletedAt).length
  );
  protected readonly draftCount = computed(() =>
    this.events().filter((event) => !event.publishedAt && !event.deletedAt).length
  );
  protected readonly nearestUpcomingEvent = computed(() => {
    const now = Date.now();

    return this.events()
      .filter((event) => !event.deletedAt && !!event.startAt)
      .map((event) => ({
        event,
        timestamp: new Date(event.startAt!).getTime()
      }))
      .filter((entry) => Number.isFinite(entry.timestamp) && entry.timestamp >= now)
      .sort((a, b) => a.timestamp - b.timestamp)[0]?.event ?? null;
  });

  protected get routePrefix(): '/dashboard' | '/admin' {
    return this.router.url.startsWith('/admin') ? '/admin' : '/dashboard';
  }

  protected completionOf(event: EventItem): { filled: number; total: number } {
    const fields = [
      event.canalId > 0,           // canal_id
      event.venueId !== null,       // venue_id
      !!event.name,                 // name
      !!event.body,                 // body
      !!event.startAt,              // start_date + start_time
      !!event.endAt,                // end_date + end_time
      !!event.registrationDeadlineAt, // registration_deadline_date + registration_deadline_time
      !!event.website,              // website
      event.uploadedFiles.length > 0  // files
    ];
    return { filled: fields.filter(Boolean).length, total: fields.length };
  }

  protected get municipalityScope(): MunicipalityOverviewScope {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  protected get eventsScope(): 'dashboard' | 'admin' {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  protected get pageSize(): number {
    return this.eventsScope === 'admin' ? 60 : 15;
  }

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: this.eventsScope === 'admin' ? 'Admin podujatia' : 'Podujatia',
      description:
        this.eventsScope === 'admin'
          ? 'Správa podujatí v administratorskej časti.'
          : 'Prehľad podujatí v dashboarde.'
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
      this.currentPerPage.set(perPage ?? this.defaultPerPage());
      this.currentPage.set(1);
      this.loadPage(1, perPage, filters);
    });
  }

  protected onPageChange(page: number): void {
    this.currentPage.set(page);
    this.loadPage(page, this.currentPerPage(), this.activeFilters());
  }

  protected onEventAction(payload: IndexItemAction, event: EventItem): void {
    if (payload.id === null) {
      return;
    }

    switch (payload.action) {
      case 'delete': {
        const deletedAt = this.normalizeNullableDateTime(event.deletedAt);
        const isDeleted = deletedAt !== null;

        if (isDeleted) {
          this.eventsApi
            .restore(payload.id)
            .pipe(timeout(10000))
            .subscribe({
              next: () => {
                this.loadPage(
                  this.currentPage(),
                  this.resolvePerPage(this.route.snapshot.queryParamMap.get('per_page')) ?? this.currentPerPage(),
                  this.activeFilters()
                );
              },
              error: (error: { status?: number } | null | undefined) => {
                const status = error?.status;
                if (status === 401 || status === 403) {
                  this.eventsApi
                    .updateStatus(payload.id!, event, event.status, event.publishedAt, '')
                    .pipe(timeout(10000))
                    .subscribe({
                      next: (updated) => {
                        this.events.update((items) =>
                          items.map((item) => (item.id === payload.id ? updated : item))
                        );
                      },
                      error: (updateError) => {
                        this.errorMessage.set(
                          resolveApiErrorMessage(updateError, 'Nepodarilo sa obnovit zmazane podujatie.')
                        );
                      }
                    });
                  return;
                }

                this.errorMessage.set(resolveApiErrorMessage(error, 'Nepodarilo sa obnovit zmazane podujatie.'));
              }
            });
          return;
        }

        if (!isDeleted) {
          const shouldDelete = confirm('Naozaj chcete zmazat toto podujatie?');
          if (!shouldDelete) {
            return;
          }
        }
        this.eventsApi
          .delete(payload.id)
          .pipe(timeout(10000))
          .subscribe({
            next: () => {
              this.loadPage(
                this.currentPage(),
                this.resolvePerPage(this.route.snapshot.queryParamMap.get('per_page')) ?? this.currentPerPage(),
                this.activeFilters()
              );
            },
            error: (error) => {
              this.errorMessage.set(resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav zmazania podujatia.'));
            }
          });
        break;
      }
      case 'status': {
        this.eventsApi
          .togglePublishedStatus(event)
          .pipe(timeout(10000))
          .subscribe({
            next: (updated) => {
              this.events.update((items) =>
                items.map((item) => (item.id === payload.id ? updated : item))
              );
            },
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav publikovania podujatia.')
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

    this.eventsApi
      .index(page, perPage, this.eventsScope, filters)
      .pipe(timeout(10000))
      .subscribe({
        next: (result) => {
          this.events.set(result.items);
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
          this.events.set([]);
          this.totalPages.set(1);
          this.canCreate.set(false);
          this.defaultPerPageLoading.set(false);
          this.loading.set(false);
          this.errorMessage.set('Nepodarilo sa nacitat events z API (timeout/chyba spojenia).');
        },
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

