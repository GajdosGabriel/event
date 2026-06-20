import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { EventShowPage } from './event-show.page';
import { EventsApiService } from '../services/events-api.service';
import { EventItem } from '../models/event.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';

describe('EventShowPage', () => {
  let fixture: ComponentFixture<EventShowPage>;
  let component: EventShowPage;
  let eventsApi: {
    show: ReturnType<typeof vi.fn>;
  };
  let router: {
    url: string;
  };

  const eventItem: EventItem = {
    id: 42,
    canalId: 1,
    canalName: 'Canal',
    municipalityId: 1,
    venueId: 8,
    name: 'Test event',
    slug: 'test-event',
    body: 'Body podujatia',
    status: MODEL_STATUS.Published,
    startAt: '2030-01-01T10:00:00.000Z',
    endAt: '2030-01-01T11:00:00.000Z',
    dateRangeLabel: '01.01.2030 10:00 - 01.01.2030 11:00',
    dateRangeDays: {
      start: 'Utorok',
      end: 'Utorok'
    },
    registrationDeadlineAt: null,
    publishedAt: '2030-01-01T09:00:00.000Z',
    deletedAt: null,
    website: 'https://example.com',
    imageUrl: 'image.jpg',
    uploadedFiles: [
      { id: 1, name: 'poster.jpg', url: 'https://example.com/poster.jpg', mimeType: 'image/jpeg' }
    ],
    permissions: {
      view: true,
      update: true,
      publish: true,
      delete: false,
      restore: false
    },
    municipality: {
      id: 1,
      fullname: 'Podbiel',
      shortname: 'Podbiel',
      zip: '027 42',
      districtId: 72,
      regionId: 8
    },
    canal: {
      id: 1,
      name: 'Canal',
      street: 'Hlavna 1',
      latitude: 48.2,
      longitude: 17.2
    },
    venue: {
      id: 8,
      name: 'Dom kultury',
      street: 'Hlavna 2',
      latitude: 48.1,
      longitude: 17.1
    }
  };

  const createComponent = async (url: string) => {
    router.url = url;

    await TestBed.configureTestingModule({
      imports: [EventShowPage],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ id: String(eventItem.id) }))
          }
        },
        {
          provide: EventsApiService,
          useValue: eventsApi as unknown as EventsApiService
        },
        {
          provide: Router,
          useValue: router as unknown as Router
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(EventShowPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  };

  beforeEach(() => {
    TestBed.resetTestingModule();
    eventsApi = {
      show: vi.fn().mockReturnValue(of(eventItem))
    };
    router = {
      url: '/dashboard/events/42'
    };
  });

  it('should expose dashboard links for dashboard route', async () => {
    await createComponent('/dashboard/events/42');

    expect((component as any).pageContext().backLink).toBe('/dashboard/events');
    expect(eventsApi.show).toHaveBeenCalledWith(eventItem.id, 'dashboard');
    expect(fixture.nativeElement.textContent).toContain('Fotogaleria podujatia');
    expect(fixture.nativeElement.textContent).toContain('Upraviť podujatie');
  });

  it('should hide edit action on public route', async () => {
    await createComponent('/events/42');

    expect((component as any).pageContext().backLink).toBe('/');
    expect((component as any).pageContext().showEditAction).toBe(false);
    expect(fixture.nativeElement.textContent).not.toContain('Upraviť podujatie');
    expect(eventsApi.show).toHaveBeenCalledWith(eventItem.id, 'public');
  });

  it('should open and close hero image lightbox', async () => {
    await createComponent('/dashboard/events/42');

    const heroButton = fixture.nativeElement.querySelector('button[aria-label*="Otvorit obrazok"]') as
      | HTMLButtonElement
      | null;

    expect(heroButton).not.toBeNull();

    heroButton?.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Otvoriť originál');

    const backdrop = fixture.nativeElement.querySelector('.fixed.inset-0') as HTMLDivElement | null;
    expect(backdrop).not.toBeNull();

    backdrop?.click();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).not.toContain('Otvoriť originál');
  });
});
