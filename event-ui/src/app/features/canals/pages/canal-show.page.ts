import { Component, computed, effect, inject } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { getCanalIdentityModeLabel } from '../models/canal-identity-mode';
import { CanalItem } from '../models/canal.model';
import { CanalShowScope, CanalsApiService } from '../services/canals-api.service';
import { VenueItem } from '../../venue/models/venue.model';
import { VenuesApiService } from '../../venue/services/venues-api.service';
import { LocationMapComponent } from '../../../shared/components/location-map/location-map.component';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import { UploadedFilesGalleryComponent } from '../../../shared/components/uploaded-files-gallery/uploaded-files-gallery.component';
import { UploadedFilesListComponent } from '../../../shared/components/uploaded-files-list/uploaded-files-list.component';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { ShowShellComponent } from '../../../shared/components/show-shell/show-shell.component';
import { PageMetaService } from '../../../shared/services/page-meta.service';
import { resolveShowPageContext } from '../../../shared/utils/show-page-context.utils';

@Component({
  selector: 'app-canal-show-page',
  imports: [
    RouterLink,
    PageHeadComponent,
    UploadedFilesGalleryComponent,
    UploadedFilesListComponent,
    LocationMapComponent,
    ShowShellComponent
  ],
  templateUrl: './canal-show.page.html',
  styleUrl: './canal-show.page.css'
})
export class CanalShowPage {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly canalsApi = inject(CanalsApiService);
  private readonly venuesApi = inject(VenuesApiService);
  private readonly pageMeta = inject(PageMetaService);
  protected readonly getCanalIdentityModeLabel = getCanalIdentityModeLabel;
  protected readonly getModelStatusLabel = getModelStatusLabel;

  protected get canalsScope(): CanalShowScope {
    return this.router.url.startsWith('/admin') ? 'admin' : 'dashboard';
  }

  protected readonly vm = toSignal(
    this.route.paramMap.pipe(
      map((params) => Number(params.get('id'))),
      switchMap((id) => {
        if (!Number.isFinite(id) || id <= 0) {
          return of({ loading: false, canal: null as CanalItem | null, venue: null as VenueItem | null });
        }

        return this.canalsApi.show(id, this.canalsScope).pipe(
          switchMap((canal) => {
            if (!canal.venueId) {
              return of({ loading: false, canal, venue: null as VenueItem | null });
            }

            return this.venuesApi.show(canal.venueId, this.canalsScope).pipe(
              map((venue) => ({ loading: false, canal, venue })),
              catchError(() => of({ loading: false, canal, venue: null as VenueItem | null }))
            );
          }),
          startWith({ loading: true, canal: null as CanalItem | null, venue: null as VenueItem | null }),
          catchError(() =>
            of({ loading: false, canal: null as CanalItem | null, venue: null as VenueItem | null })
          )
        );
      })
    ),
    { initialValue: { loading: true, canal: null as CanalItem | null, venue: null as VenueItem | null } }
  );

  protected readonly pageContext = computed(() => {
    return resolveShowPageContext(this.router.url, {
      entityPath: 'canals',
      backLinkText: 'Späť na kanály',
      publicBackLink: '/dashboard/canals',
      publicBackLinkText: 'Späť na kanály',
      publicShowsEditAction: true
    });
  });

  private readonly syncPageMetaEffect = effect(() => {
    const state = this.vm();

    if (state.loading) {
      this.pageMeta.setPageMeta({
        title: 'Kanál',
        description: 'Detail kanála.'
      });
      return;
    }

    if (!state.canal) {
      this.pageMeta.setPageMeta({
        title: 'Kanál sa nenašiel',
        description: 'Požadovaný kanál sa nepodarilo načítať.'
      });
      return;
    }

    this.pageMeta.setPageMeta({
      title: state.canal.canal,
      description: state.canal.body,
      imageUrl: state.canal.imageUrl
    });
  });
}
