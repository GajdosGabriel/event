import { Component, DestroyRef, OnInit, computed, effect, inject, input } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged } from 'rxjs';
import { FilterQueryService } from '../../services/filter-query.service';
import { createDefaultFilterState, createPerPageOptions, DEFAULT_FILTER_PER_PAGE, FilterState } from '../../models/filter-params.model';
import { MODEL_STATUS_OPTIONS, ModelStatus } from '../../models/model-status';

@Component({
  selector: 'app-filter',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './filter.component.html',
  styleUrl: './filter.component.css'
})
export class FilterComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly filterService = inject(FilterQueryService);
  readonly defaultPerPage = input(DEFAULT_FILTER_PER_PAGE);
  readonly perPageLoading = input(false);

  protected filterState: FilterState = createDefaultFilterState();
  protected isActive = false;
  protected activeFilterLabels: string[] = [];
  protected readonly statusOptions = MODEL_STATUS_OPTIONS;
  protected readonly searchControl = new FormControl('', { nonNullable: true });
  protected readonly perPageOptions = computed(() => createPerPageOptions(this.defaultPerPage()));
  private hasLoadedFilterState = false;

  constructor() {
    effect(() => {
      if (!this.hasLoadedFilterState) {
        this.filterState = createDefaultFilterState(this.defaultPerPage());
      }

      this.filterService.setDefaultPerPage(this.defaultPerPage());
    });
  }

  ngOnInit(): void {
    this.searchControl.valueChanges
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        this.filterService.setSearch(value);
      });

    this.filterService
      .getFilterState$()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((state) => {
        this.hasLoadedFilterState = true;
        this.filterState = state;
        this.isActive = this.filterService.isActive();
        this.activeFilterLabels = this.resolveActiveFilterLabels(state);
        if (this.searchControl.value !== state.search) {
          this.searchControl.setValue(state.search, { emitEvent: false });
        }
      });
  }

  togglePublished(): void {
    this.filterService.togglePublished();
  }

  toggleUnpublished(): void {
    this.filterService.toggleUnpublished();
  }

  toggleBlocked(): void {
    this.filterService.toggleBlocked();
  }

  toggleDeleted(): void {
    this.filterService.toggleDeleted();
  }

  resetFilter(): void {
    this.filterService.resetFilter();
  }

  onPerPageChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const value = parseInt(target.value, 10);
    this.filterService.setPerPage(value);
  }

  onStatusChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const value = target.value.trim();
    this.filterService.setStatus(value ? (value as ModelStatus) : null);
  }

  clearSearch(): void {
    this.searchControl.setValue('');
  }

  private resolveActiveFilterLabels(state: FilterState): string[] {
    const labels: string[] = [];

    if (state.published) {
      labels.push('Publikovane');
    }

    if (state.unpublished) {
      labels.push('Nepublikovane');
    }

    if (state.blocked) {
      labels.push('Blokovane');
    }

    if (state.status) {
      labels.push(`Status: ${state.status}`);
    }

    if (state.deleted) {
      labels.push('Vratane zmazanych');
    }

    if (state.search) {
      labels.push(`Hladat: ${state.search}`);
    }

    if (state.per_page !== this.defaultPerPage()) {
      labels.push(`Na stranku: ${state.per_page}`);
    }

    return labels;
  }
}
