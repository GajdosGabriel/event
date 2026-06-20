import { Component, computed, effect, inject } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { VenueItem } from '../models/venue.model';
import { VenueShowScope, VenuesApiService } from '../services/venues-api.service';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import { UploadedFilesGalleryComponent } from '../../../shared/components/uploaded-files-gallery/uploaded-files-gallery.component';
import { UploadedFilesListComponent } from '../../../shared/components/uploaded-files-list/uploaded-files-list.component';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { ShowShellComponent } from '../../../shared/components/show-shell/show-shell.component';
import { PageMetaService } from '../../../shared/services/page-meta.service';
import { resolveShowPageContext } from '../../../shared/utils/show-page-context.utils';

@Component({
  selector: 'app-venue-show-page',
  imports: [RouterLink, PageHeadComponent, UploadedFilesListComponent, UploadedFilesGalleryComponent, ShowShellComponent],
  templateUrl: './venue-show.page.html',
  styleUrl: './venue-show.page.css'
})
export class VenueShowPage {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly venuesApi = inject(VenuesApiService);
  private readonly pageMeta = inject(PageMetaService);
  protected readonly getModelStatusLabel = getModelStatusLabel;

  protected get venuesScope(): VenueShowScope {
    return this.router.url.startsWith('/admin') ? 'admin' : 'dashboard';
  }

  protected readonly vm = toSignal(
    this.route.paramMap.pipe(
      map((params) => Number(params.get('id'))),
      switchMap((id) => {
        if (!Number.isFinite(id) || id <= 0) {
          return of({ loading: false, venue: null as VenueItem | null });
        }

        return this.venuesApi.show(id, this.venuesScope).pipe(
          map((venue) => ({ loading: false, venue })),
          startWith({ loading: true, venue: null as VenueItem | null }),
          catchError(() => of({ loading: false, venue: null as VenueItem | null }))
        );
      })
    ),
    { initialValue: { loading: true, venue: null as VenueItem | null } }
  );

  protected readonly openingHoursEntries = computed(() => {
    const venue = this.vm().venue;
    if (!venue?.openingHours || venue.openingHours.length === 0) {
      return [] as Array<{ day: string; hours: string }>;
    }

    const entries: Array<{ day: string; hours: string }> = [];

    for (let i = 0; i < venue.openingHours.length; i++) {
      const item = venue.openingHours[i];

      if (item !== null && typeof item === 'object' && !Array.isArray(item)) {
        const record = item as Record<string, unknown>;
        for (const [day, hours] of Object.entries(record)) {
          entries.push({
            day: day.toUpperCase(),
            hours: typeof hours === 'string' ? hours : hours == null ? 'Zatvorene' : String(hours)
          });
        }
      } else {
        entries.push({ day: String(i + 1), hours: item == null ? 'Zatvorene' : String(item) });
      }
    }

    return entries;
  });

  protected readonly pageContext = computed(() => {
    return resolveShowPageContext(this.router.url, {
      entityPath: 'venues',
      backLinkText: 'Späť na miesta',
      publicBackLink: '/dashboard/venues',
      publicBackLinkText: 'Späť na miesta',
      publicShowsEditAction: true
    });
  });

  private readonly syncPageMetaEffect = effect(() => {
    const state = this.vm();

    if (state.loading) {
      this.pageMeta.setPageMeta({
        title: 'Miesto',
        description: 'Detail miesta.'
      });
      return;
    }

    if (!state.venue) {
      this.pageMeta.setPageMeta({
        title: 'Miesto sa nenašlo',
        description: 'Požadované miesto sa nepodarilo načítať.'
      });
      return;
    }

    this.pageMeta.setPageMeta({
      title: state.venue.name,
      description: state.venue.body,
      imageUrl: state.venue.imageUrl
    });
  });
}
