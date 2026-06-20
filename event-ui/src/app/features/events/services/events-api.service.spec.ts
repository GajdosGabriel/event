import '@angular/compiler';
import { describe, expect, it } from 'vitest';
import { EventsApiService } from './events-api.service';
import { EventApiItem } from '../models/event.model';

function createService(): EventsApiService {
  return Object.assign(Object.create(EventsApiService.prototype), {
    fallbackImage: 'fallback.jpg',
    emptyLocation: {
      id: 0,
      name: '',
      street: '',
      latitude: null,
      longitude: null,
    },
    emptyMunicipality: {
      id: 0,
      fullname: '',
      shortname: '',
      zip: null,
      districtId: null,
      regionId: null,
    },
  }) as EventsApiService;
}

describe('EventsApiService', () => {
  it('maps partial create responses without related canal and venue payloads', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 42,
      canal_id: 7,
      user_id: 3,
      municipality_id: 11,
      venue_id: null,
      name: 'Test event',
      slug: 'test-event',
      body: 'Body',
      body_ai: null,
      start_at: '2030-01-01T10:00:00.000Z',
      end_at: '2030-01-01T12:00:00.000Z',
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {
        view: true,
      },
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.id).toBe(42);
    expect(result.canal.id).toBe(7);
    expect(result.canal.name).toBe('');
    expect(result.venue.id).toBe(0);
    expect(result.venueId).toBeNull();
    expect(result.municipality.id).toBe(0);
    expect(result.imageUrl).toBe('http://event-api.local/fallback.jpg');
  });

  it('maps municipality payload to event municipality', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 43,
      canal_id: 7,
      user_id: 3,
      municipality_id: 2682,
      venue_id: null,
      name: 'Municipality event',
      slug: 'municipality-event',
      body: 'Body',
      body_ai: null,
      start_at: '2030-01-01T10:00:00.000Z',
      end_at: '2030-01-01T12:00:00.000Z',
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {},
      municipality: {
        id: 2682,
        fullname: 'Podbiel',
        shortname: 'Podbiel',
        zip: '027 42',
        district_id: 72,
        region_id: 8,
        use: 1,
        created_at: null,
        updated_at: null,
      },
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.municipality).toEqual({
      id: 2682,
      fullname: 'Podbiel',
      shortname: 'Podbiel',
      zip: '027 42',
      districtId: 72,
      regionId: 8,
    });
  });

  it('normalizes invalid api datetime fields to null', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 9,
      canal_id: 4,
      user_id: 2,
      municipality_id: 6,
      venue_id: 3,
      name: 'Broken event',
      slug: 'broken-event',
      body: 'Body',
      body_ai: null,
      start_at: 'lenka.mikulik',
      end_at: 'also-not-a-date',
      registration_deadline_at: '2026-05-01T12:00:00.000Z',
      published_at: '2030-01-01T09:00:00.000Z',
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {},
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.startAt).toBeNull();
    expect(result.endAt).toBeNull();
    expect(result.registrationDeadlineAt).toBe('2026-05-01T12:00:00.000Z');
    expect(result.publishedAt).toBe('2030-01-01T09:00:00.000Z');
  });

  it('maps api date range labels and weekdays', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 10,
      canal_id: 4,
      user_id: 2,
      municipality_id: 6,
      venue_id: 3,
      name: 'Range event',
      slug: 'range-event',
      body: 'Body',
      body_ai: null,
      start_at: '2026-05-01T16:00:00.000Z',
      end_at: '2026-05-09T10:30:00.000Z',
      date_range_label: '01.05.2026 16:00 - 09.05.2026 10:30',
      date_range_days: {
        start: 'Piatok',
        end: 'Sobota',
      },
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {},
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.dateRangeLabel).toBe('01.05.2026 16:00 - 09.05.2026 10:30');
    expect(result.dateRangeDays).toEqual({
      start: 'Piatok',
      end: 'Sobota',
    });
  });

  it('builds date range label from start and end when api omits it', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 11,
      canal_id: 4,
      user_id: 2,
      municipality_id: 6,
      venue_id: 3,
      name: 'Fallback range event',
      slug: 'fallback-range-event',
      body: 'Body',
      body_ai: null,
      start_at: '2026-02-28T10:00:00.000Z',
      end_at: '2026-07-05T23:29:00.000Z',
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {},
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.dateRangeLabel).toBeTruthy();
    expect(result.dateRangeDays.start).toBeTruthy();
    expect(result.dateRangeDays.end).toBeTruthy();
  });

  it('maps primary_image object payload to imageUrl', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 77,
      canal_id: 5,
      user_id: 1,
      municipality_id: 2,
      venue_id: null,
      name: 'Image event',
      slug: 'image-event',
      body: 'Body',
      body_ai: null,
      start_at: '2030-01-01T10:00:00.000Z',
      end_at: '2030-01-01T12:00:00.000Z',
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: null,
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: {
        thumb: 'http://event-api.local/storage/event/85/image/etSlsWlDEuLcl8bszAzprcx05lEBUECA9sJjOQj7_thumb.jpeg',
        large: 'http://event-api.local/storage/event/85/image/etSlsWlDEuLcl8bszAzprcx05lEBUECA9sJjOQj7_large.jpeg'
      },
      permissions: {},
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.imageUrl).toBe(
      'http://event-api.local/storage/event/85/image/etSlsWlDEuLcl8bszAzprcx05lEBUECA9sJjOQj7_large.jpeg'
    );
  });

  it('removes the ecav directory link block from event body', () => {
    const service = createService();
    const apiItem: EventApiItem = {
      id: 78,
      canal_id: 5,
      user_id: 1,
      municipality_id: 2,
      venue_id: null,
      name: 'ECAV event',
      slug: 'ecav-event',
      body: 'Obsah pozvanky\n\nOdkazy:\nPozvánky: https://www.ecav.sk/aktuality/pozvanky',
      body_ai: null,
      start_at: '2030-01-01T10:00:00.000Z',
      end_at: '2030-01-01T12:00:00.000Z',
      registration_deadline_at: null,
      published_at: null,
      status: 'draft',
      website: null,
      orginal_source: 'https://www.ecav.sk/aktuality/pozvanky/kulturne-podujatie-porajmos',
      meta: null,
      deleted_at: null,
      created_at: '2030-01-01T09:00:00.000Z',
      updated_at: '2030-01-01T09:00:00.000Z',
      owner: true,
      primary_image: [],
      permissions: {},
      canal: null,
      venue: null,
    };

    const result = (service as any).toEventItem(apiItem);

    expect(result.body).toBe('Obsah pozvanky');
  });
});
