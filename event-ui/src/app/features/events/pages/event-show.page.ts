import { DatePipe } from '@angular/common';
import { Component, HostListener, computed, effect, inject, signal } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ActivatedRoute, RouterLink, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { EventShowScope, EventsApiService } from '../services/events-api.service';
import { EventItem } from '../models/event.model';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import { UploadedFilesGalleryComponent } from '../../../shared/components/uploaded-files-gallery/uploaded-files-gallery.component';
import { UploadedFilesListComponent } from '../../../shared/components/uploaded-files-list/uploaded-files-list.component';
import { getModelStatusLabel } from '../../../shared/models/model-status';
import { ShowShellComponent } from '../../../shared/components/show-shell/show-shell.component';
import { LocationMapComponent } from '../../../shared/components/location-map/location-map.component';
import { PageMetaService } from '../../../shared/services/page-meta.service';
import { resolveShowPageContext } from '../../../shared/utils/show-page-context.utils';

@Component({
  selector: 'app-event-show-page',
  imports: [
    DatePipe,
    RouterLink,
    PageHeadComponent,
    UploadedFilesGalleryComponent,
    UploadedFilesListComponent,
    LocationMapComponent,
    ShowShellComponent
  ],
  templateUrl: './event-show.page.html',
  styleUrl: './event-show.page.css'
})
export class EventShowPage {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly eventsApi = inject(EventsApiService);
  private readonly pageMeta = inject(PageMetaService);
  private readonly sanitizer = inject(DomSanitizer);
  protected readonly getModelStatusLabel = getModelStatusLabel;
  protected readonly heroLightboxOpen = signal(false);
  private readonly normalizedRouteUrl = computed(() => {
    const currentUrl = this.router.url;

    if (currentUrl.startsWith('/admin/events/')) {
      return currentUrl.replace('/admin/events/', '/dashboard/events/');
    }

    return currentUrl;
  });

  protected readonly eventsScope = computed<EventShowScope>(() => {
    if (this.normalizedRouteUrl().startsWith('/dashboard/')) {
      return 'dashboard';
    }

    return 'public';
  });

  protected readonly vm = toSignal(
    this.route.paramMap.pipe(
      map((params) => Number(params.get('id'))),
      switchMap((id) => {
        if (!Number.isFinite(id) || id <= 0) {
          return of({ loading: false, event: null as EventItem | null });
        }

        return this.eventsApi.show(id, this.eventsScope()).pipe(
          map((event) => ({ loading: false, event })),
          startWith({ loading: true, event: null as EventItem | null }),
          catchError(() => of({ loading: false, event: null as EventItem | null }))
        );
      })
    ),
    { initialValue: { loading: true, event: null as EventItem | null } }
  );

  protected readonly pageContext = computed(() => {
    return resolveShowPageContext(this.normalizedRouteUrl(), {
      entityPath: 'events',
      backLinkText: 'Späť na podujatia'
    });
  });

  protected openHeroLightbox(): void {
    if (!this.vm().event?.imageUrl) {
      return;
    }

    this.heroLightboxOpen.set(true);
  }

  protected closeHeroLightbox(): void {
    this.heroLightboxOpen.set(false);
  }

  protected dateRangeDaysLabel(event: EventItem): string | null {
    const start = event.dateRangeDays.start;
    const end = event.dateRangeDays.end;

    if (start && end) {
      return `${start} - ${end}`;
    }

    return start ?? end ?? null;
  }

  protected municipalityLabel(event: EventItem): string | null {
    return event.municipality.shortname || event.municipality.fullname || null;
  }

  protected onHeroLightboxBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.closeHeroLightbox();
    }
  }

  protected formatBody(body: string | null | undefined): SafeHtml {
    const value = typeof body === 'string' ? body.trim() : '';
    if (!value) {
      return '';
    }

    if (/<[a-z][\s\S]*>/i.test(value)) {
      return this.sanitizer.bypassSecurityTrustHtml(value);
    }

    return this.sanitizer.bypassSecurityTrustHtml(
      value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/\n/g, '<br>'),
    );
  }

  @HostListener('document:keydown', ['$event'])
  protected onDocumentKeydown(event: KeyboardEvent): void {
    if (this.heroLightboxOpen() && event.key === 'Escape') {
      this.closeHeroLightbox();
    }
  }

  private readonly syncPageMetaEffect = effect(() => {
    const state = this.vm();

    if (state.loading) {
      this.pageMeta.setPageMeta({
        title: 'Podujatie',
        description: 'Detail podujatia.'
      });
      return;
    }

    if (!state.event) {
      this.pageMeta.setPageMeta({
        title: 'Podujatie sa nenašlo',
        description: 'Požadované podujatie sa nepodarilo načítať.'
      });
      return;
    }

    this.pageMeta.setPageMeta({
      title: state.event.name,
      description: state.event.body,
      imageUrl: state.event.imageUrl
    });
  });
}
