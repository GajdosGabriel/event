import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, of, take, timeout } from 'rxjs';
import { IndexItemAction, IndexItemComponent } from '../../../shared/components/index-item/index-item.component';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { PaginatorComponent } from '../../../shared/components/paginator/paginator.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { CanalItem } from '../models/canal.model';
import { CanalListScope, CanalsApiService } from '../services/canals-api.service';
import { MunicipalityOverviewComponent } from '../../../shared/components/municipality-overview/municipality-overview.component';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { AuthService } from '../../../core/services/auth.service';
import { FilterParams } from '../../../shared/models/filter-params.model';
import { getCanalIdentityModeLabel } from '../models/canal-identity-mode';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-canal-index.page',
  imports: [PaginatorComponent, IndexItemComponent, RouterLink, IndexShellComponent, ErrorBannerComponent, MunicipalityOverviewComponent],
  templateUrl: './canal-index.page.html',
  styleUrl: './canal-index.page.css'
})
export class CanalIndexPage implements OnInit {
  private readonly canalsApi = inject(CanalsApiService);
  private readonly authService = inject(AuthService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly pageMeta = inject(PageMetaService);
  private readonly currentIdentity = toSignal(this.authService.currentIdentity$, { initialValue: null });

  protected readonly canals = signal<CanalItem[]>([]);
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
  protected readonly activeCanalId = computed(() => this.currentIdentity()?.canal_id ?? null);
  protected readonly getModelStatusLabel = getModelStatusLabel;
  protected readonly getCanalIdentityModeLabel = getCanalIdentityModeLabel;
  protected readonly resolvedActiveCanalId = computed(() => {
    const activeCanalId = this.activeCanalId();
    if (activeCanalId !== null) {
      return activeCanalId;
    }

    const items = this.canals();
    return items.length === 1 ? items[0]?.id ?? null : null;
  });
  protected readonly canSwitchCanal = computed(() => this.canals().length > 1);

  protected get routePrefix(): '/dashboard' | '/admin' {
    return this.router.url.startsWith('/admin') ? '/admin' : '/dashboard';
  }

  protected get canalsScope(): CanalListScope {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: 'Kanály',
      description: 'Prehľad kanálov a možnosti správy aktívneho kanála.'
    });

    if (this.activeCanalId() === null) {
      this.authService
        .fetchCurrentIdentity()
        .pipe(
          take(1),
          catchError(() => of(null))
        )
        .subscribe();
    }

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

  protected onCanalAction(payload: IndexItemAction, canal: CanalItem): void {
    if (payload.id === null) {
      return;
    }

    switch (payload.action) {
      case 'delete': {
        const deletedAt = this.normalizeNullableDateTime(canal.deletedAt);
        const isDeleted = deletedAt !== null;

        if (isDeleted) {
          this.canalsApi
            .restore(payload.id, this.canalsScope)
            .pipe(timeout(10000))
            .subscribe({
              next: () => {
                this.loadPage(this.currentPage(), this.currentPerPage(), this.activeFilters());
              },
              error: (error) => {
                this.errorMessage.set(
                  resolveApiErrorMessage(error, 'Nepodarilo sa obnovit zmazany kanal.')
                );
              }
            });
          return;
        }

        const shouldDelete = confirm('Naozaj chcete zmazat tento kanal?');
        if (!shouldDelete) {
          return;
        }

        this.canalsApi
          .delete(payload.id, this.canalsScope)
          .pipe(timeout(10000))
          .subscribe({
            next: () => {
              this.loadPage(this.currentPage(), this.currentPerPage(), this.activeFilters());
            },
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav zmazania kanala.')
              );
            }
          });
        break;
      }
      case 'status': {
        this.canalsApi
          .togglePublishedStatus(canal, this.canalsScope)
          .pipe(timeout(10000))
          .subscribe({
            next: (updated) => {
              this.canals.update((items) =>
                items.map((item) => (item.id === payload.id ? updated : item))
              );
            },
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa zmenit stav publikovania kanala.')
              );
            }
          });
        break;
      }
      case 'switch': {
        this.authService
          .setActiveCanal(payload.id)
          .pipe(timeout(10000))
          .subscribe({
            error: (error) => {
              this.errorMessage.set(
                resolveApiErrorMessage(error, 'Nepodarilo sa prepnut aktivny kanal.')
              );
            }
          });
        break;
      }
    }
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

  private loadPage(page: number, perPage: number | undefined, filters: FilterParams): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.canalsApi
      .index(page, perPage, filters, this.canalsScope)
      .pipe(timeout(10000))
      .subscribe({
        next: (result) => {
          this.canals.set(result.items);
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
          this.canals.set([]);
          this.totalPages.set(1);
          this.canCreate.set(false);
          this.defaultPerPageLoading.set(false);
          this.loading.set(false);
          this.errorMessage.set('Nepodarilo sa nacitat canals z API (timeout/chyba spojenia).');
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
}
