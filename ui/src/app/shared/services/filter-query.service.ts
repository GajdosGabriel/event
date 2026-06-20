import { Injectable, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BehaviorSubject, Observable } from 'rxjs';
import {
  FilterParams,
  FilterState,
  DEFAULT_FILTER_PER_PAGE,
  createDefaultFilterState,
  filterParamsToState,
  filterStateToParams
} from '../models/filter-params.model';
import { ModelStatus } from '../models/model-status';

@Injectable({ providedIn: 'root' })
export class FilterQueryService {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  private defaultState = createDefaultFilterState();
  private filterState$ = new BehaviorSubject<FilterState>(this.defaultState);

  constructor() {
    this.initializeFromQueryParams();
  }

  private initializeFromQueryParams(): void {
    this.route.queryParams.subscribe((params) => {
      this.syncStateFromQueryParams(params);
    });
  }

  private syncStateFromQueryParams(params: FilterParams): void {
    const state = {
      ...this.defaultState,
      ...filterParamsToState(params, this.defaultState.per_page)
    };

    this.filterState$.next(state);
  }

  getFilterState$(): Observable<FilterState> {
    return this.filterState$.asObservable();
  }

  getFilterState(): FilterState {
    return this.filterState$.value;
  }

  setDefaultPerPage(perPage: number): void {
    const normalizedPerPage = Number.isFinite(perPage) && perPage > 0 ? perPage : DEFAULT_FILTER_PER_PAGE;

    if (this.defaultState.per_page === normalizedPerPage) {
      return;
    }

    this.defaultState = createDefaultFilterState(normalizedPerPage);
    this.syncStateFromQueryParams(this.route.snapshot.queryParams as FilterParams);
  }

  updateFilter(partial: Partial<FilterState>): void {
    const current = this.filterState$.value;
    const updated: FilterState = { ...current, ...partial };
    this.filterState$.next(updated);
    this.updateQueryParams(updated);
  }

  resetFilter(): void {
    this.filterState$.next(this.defaultState);
    this.updateQueryParams(this.defaultState);
  }

  private updateQueryParams(state: FilterState): void {
    const params = filterStateToParams(state, this.defaultState.per_page);
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: params,
      replaceUrl: true
    });
  }

  togglePublished(): void {
    const current = this.filterState$.value;
    const newState: FilterState = {
      ...current,
      published: !current.published && !current.unpublished ? true : false,
      unpublished: false,
      blocked: false
    };
    this.updateFilter(newState);
  }

  toggleUnpublished(): void {
    const current = this.filterState$.value;
    const newState: FilterState = {
      ...current,
      unpublished: !current.unpublished && !current.published ? true : false,
      published: false,
      blocked: false
    };
    this.updateFilter(newState);
  }

  toggleBlocked(): void {
    const current = this.filterState$.value;
    const newState: FilterState = {
      ...current,
      blocked: !current.blocked && !current.published && !current.unpublished ? true : false,
      published: false,
      unpublished: false
    };
    this.updateFilter(newState);
  }

  setStatus(status: ModelStatus | null): void {
    const current = this.filterState$.value;
    this.updateFilter({
      status: status,
      published: false,
      unpublished: false,
      blocked: false
    });
  }

  toggleDeleted(): void {
    const current = this.filterState$.value;
    this.updateFilter({ deleted: !current.deleted });
  }

  setSearch(search: string): void {
    this.updateFilter({ search: search.trim() });
  }

  setPerPage(perPage: number): void {
    this.updateFilter({ per_page: perPage });
  }

  isActive(): boolean {
    const current = this.filterState$.value;
    return (
      current.published ||
      current.unpublished ||
      current.blocked ||
      current.status !== null ||
      current.deleted ||
      current.search.length > 0 ||
      current.per_page !== this.defaultState.per_page
    );
  }
}
