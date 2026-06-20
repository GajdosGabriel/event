import { Component, DestroyRef, OnInit, inject, input, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { timeout } from 'rxjs';
import { MunicipalityOverviewItem } from '../../models/municipality-overview.model';
import {
  MunicipalitiesOverviewApiService,
  MunicipalityOverviewResource,
  MunicipalityOverviewScope
} from '../../services/municipalities-overview-api.service';

@Component({
  selector: 'app-municipality-overview',
  imports: [RouterLink],
  templateUrl: './municipality-overview.component.html',
  styleUrl: './municipality-overview.component.css'
})
export class MunicipalityOverviewComponent implements OnInit {
  readonly title = input('Okresy Slovenska');
  readonly allLabel = input('Všetky regióny');
  readonly allLink = input('/events');
  readonly filterParam = input('municipality');
  readonly limit = input(8);
  readonly scope = input<MunicipalityOverviewScope>('public');
  readonly resource = input<MunicipalityOverviewResource>('events');

  private readonly overviewApi = inject(MunicipalitiesOverviewApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly items = signal<MunicipalityOverviewItem[]>([]);
  protected readonly totalEvents = signal(0);
  protected readonly loading = signal(true);
  protected readonly errorMessage = signal('');
  protected readonly selectedMunicipalityId = signal<number | null>(null);

  ngOnInit(): void {
    this.watchSelectedMunicipality();
    this.load();
  }

  protected trackByMunicipality(_index: number, item: MunicipalityOverviewItem): number {
    return item.municipalityId;
  }

  protected getItemQueryParams(item: MunicipalityOverviewItem): Record<string, number> {
    return { [this.filterParam()]: item.municipalityId };
  }

  protected shouldShowMunicipalityIcon(item: MunicipalityOverviewItem): boolean {
    return item.municipalityId === this.selectedMunicipalityId();
  }

  protected shouldShowAllIcon(): boolean {
    return this.selectedMunicipalityId() === null;
  }

  private watchSelectedMunicipality(): void {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((queryParamMap) => {
      const selectedValue = queryParamMap.get(this.filterParam());
      const selectedId = selectedValue === null ? null : Number(selectedValue);
      this.selectedMunicipalityId.set(Number.isFinite(selectedId) ? selectedId : null);
    });
  }

  private load(): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.overviewApi.list(this.scope(), this.resource()).pipe(timeout(10000)).subscribe({
      next: (items) => {
        const sorted = [...items].sort((left, right) => right.eventsCount - left.eventsCount);
        this.totalEvents.set(sorted.reduce((acc, item) => acc + item.eventsCount, 0));
        this.items.set(sorted.slice(0, Math.max(1, this.limit())));
        this.loading.set(false);
      },
      error: () => {
        this.items.set([]);
        this.totalEvents.set(0);
        this.loading.set(false);
        this.errorMessage.set('Nepodarilo sa nacitat prehlad obci.');
      }
    });
  }
}

