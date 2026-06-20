import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { EventsApiService } from '../services/events-api.service';
import { EventItem } from '../models/event.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';

import { EventIndexPage } from './event-index.page';

describe('EventIndexPage', () => {
  let component: EventIndexPage;
  let fixture: ComponentFixture<EventIndexPage>;
  let eventsApi: {
    index: ReturnType<typeof vi.fn>;
    publish: ReturnType<typeof vi.fn>;
    updateStatus: ReturnType<typeof vi.fn>;
    togglePublishedStatus: ReturnType<typeof vi.fn>;
  };

  const eventItem: EventItem = {
    id: 42,
    canalId: 1,
    canalName: 'Canal',
    municipalityId: 1,
    venueId: null,
    name: 'Test event',
    slug: 'test-event',
    body: 'Body',
    startAt: '2030-01-01T10:00:00.000Z',
    endAt: '2030-01-01T11:00:00.000Z',
    dateRangeLabel: '01.01.2030 10:00 - 01.01.2030 11:00',
    dateRangeDays: {
      start: 'Utorok',
      end: 'Utorok'
    },
    imageUrl: '',
    status: 'draft',
    website: null,
    registrationDeadlineAt: null,
    publishedAt: null,
    deletedAt: null,
    uploadedFiles: [],
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
      latitude: null,
      longitude: null
    },
    venue: {
      id: 0,
      name: '-',
      street: '-',
      latitude: null,
      longitude: null
    }
  };

  beforeEach(async () => {
    eventsApi = {
      index: vi.fn().mockReturnValue(
        of({
          items: [],
          currentPage: 1,
          perPage: 6,
          totalPages: 1,
          total: 0,
          permissions: {
            create: true
          }
        })
      ),
      publish: vi.fn().mockReturnValue(
        of({
          ...eventItem,
          status: MODEL_STATUS.Published,
          publishedAt: '2030-01-01 09:00:00'
        })
      ),
      updateStatus: vi.fn().mockReturnValue(of(eventItem)),
      togglePublishedStatus: vi.fn().mockReturnValue(
        of({
          ...eventItem,
          status: MODEL_STATUS.Published,
          publishedAt: '2030-01-01 09:00:00'
        })
      )
    };

    await TestBed.configureTestingModule({
      imports: [EventIndexPage],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({})),
            queryParamMap: of(convertToParamMap({})),
            queryParams: of({}),
            snapshot: {
              queryParamMap: convertToParamMap({})
            }
          }
        },
        {
          provide: EventsApiService,
          useValue: eventsApi as unknown as EventsApiService
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(EventIndexPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('loads dashboard events with the synchronized default page size', () => {
    expect(eventsApi.index).toHaveBeenCalledWith(1, undefined, 'dashboard', {});
  });

  it('should show API validation error when publish fails', () => {
    eventsApi.togglePublishedStatus.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
        status: 422,
        error: {
          message: 'Pole start at musi byt datum v buducnosti.',
          errors: {
            start_at: ['Pole start at musi byt datum v buducnosti.']
          }
        }
      })
      )
    );

    (component as any).events.set([eventItem]);
    (component as any).onEventAction({ action: 'status', id: eventItem.id }, eventItem);

    expect((component as any).errorMessage()).toBe('Pole start at musi byt datum v buducnosti.');
  });

  it('should publish draft event through publish endpoint', () => {
    const updatedItem: EventItem = {
      ...eventItem,
      status: MODEL_STATUS.Published,
      publishedAt: '2030-01-01 09:00:00'
    };
    eventsApi.togglePublishedStatus.mockReturnValue(of(updatedItem));

    (component as any).events.set([eventItem]);
    (component as any).onEventAction({ action: 'status', id: eventItem.id }, eventItem);

    expect(eventsApi.togglePublishedStatus).toHaveBeenCalledWith(eventItem);
    expect((component as any).events()[0].status).toBe(MODEL_STATUS.Published);
  });

  it('should unpublish event through update endpoint', () => {
    const publishedEvent: EventItem = {
      ...eventItem,
      status: MODEL_STATUS.Published,
      publishedAt: '2030-01-01 09:00:00'
    };
    const updatedItem: EventItem = {
      ...publishedEvent,
      status: MODEL_STATUS.Draft,
      publishedAt: null
    };
    eventsApi.togglePublishedStatus.mockReturnValue(of(updatedItem));

    (component as any).events.set([publishedEvent]);
    (component as any).onEventAction({ action: 'status', id: publishedEvent.id }, publishedEvent);

    expect(eventsApi.togglePublishedStatus).toHaveBeenCalledWith(publishedEvent);
    expect((component as any).events()[0].status).toBe(MODEL_STATUS.Draft);
  });
});
