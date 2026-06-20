import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import {
  EventApiItem,
  EventApiMunicipality,
  EventItem,
  EventMunicipality,
} from '../models/event.model';
import { extractPrimaryImageUrl, extractUploadedFiles } from '../../../shared/utils/uploaded-files.utils';
import {
  MODEL_STATUS,
  AllowedStatusOption,
  ModelStatus,
  sanitizeAllowedStatuses,
  sanitizeModelStatus,
  togglePublishedModelStatus
} from '../../../shared/models/model-status';
import { CollectionPermissions, ModelPermissions } from '../../../shared/models/model-permissions';
import { FilterParams } from '../../../shared/models/filter-params.model';

export interface EventUpsertPayload {
  canal_id?: number;
  municipality_id?: number;
  venue_id?: number | null;
  name: string;
  slug?: string;
  body?: string;
  body_ai?: string | null;
  start_at?: string;
  end_at?: string;
  registration_deadline_at?: string | null;
  status?: ModelStatus;
  published_at?: string | null;
  deleted_at?: string | null;
  website?: string | null;
  location_name?: string | null;
  street?: string | null;
  postcode?: string | null;
  country?: string | null;
  latitude?: string | null;
  longitude?: string | null;
}

export interface EventFileUploadOptions {
  file_type?: 'image' | 'card' | 'file';
  file_disk?: string;
  make_primary_file?: boolean;
}

export interface EventListResult {
  items: EventItem[];
  currentPage: number;
  perPage: number;
  totalPages: number;
  total: number;
  permissions: CollectionPermissions;
  allowedStatuses: AllowedStatusOption[];
}

export type EventListScope = 'dashboard' | 'admin';
export type EventShowScope = EventListScope | 'public';

interface EventListApiResponse {
  current_page?: number;
  data?: EventApiItem[];
  per_page?: number;
  last_page?: number;
  total?: number;
  meta?: {
    current_page?: number;
    per_page?: number;
    last_page?: number;
    total?: number;
    permissions?: {
      create?: boolean;
    };
    allowed_statuses?: unknown[];
  };
}

type EventListEnvelope = EventListApiResponse;
type EventShowEnvelope = EventApiItem | { data: EventApiItem };

@Injectable({ providedIn: 'root' })
export class EventsApiService {
  private readonly http = inject(HttpClient);
  private readonly fallbackImage = 'https://placehold.co/800x500/e2e8f0/334155?text=Event';

  private readonly emptyLocation = {
    id: 0,
    name: '',
    street: '',
    latitude: null,
    longitude: null,
  };

  private readonly emptyMunicipality: EventMunicipality = {
    id: 0,
    fullname: '',
    shortname: '',
    zip: null,
    districtId: null,
    regionId: null,
  };

  private normalizeUpdatePayload(
    payload: Partial<EventUpsertPayload>,
  ): Partial<EventUpsertPayload> {
    const normalizedPayload: Partial<EventUpsertPayload> = { ...payload };

    if ('published_at' in payload && payload.published_at === null) {
      normalizedPayload.published_at = '';
    }

    if ('deleted_at' in payload && payload.deleted_at === null) {
      normalizedPayload.deleted_at = '';
    }

    return normalizedPayload;
  }

  private normalizeDateTime(value: unknown): string | null {
    if (typeof value !== 'string') {
      return null;
    }

    const trimmed = value.trim();
    if (!trimmed) {
      return null;
    }

    return Number.isFinite(new Date(trimmed).getTime()) ? trimmed : null;
  }

  private formatDateTimeLabel(value: string | null): string | null {
    if (!value) {
      return null;
    }

    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) {
      return null;
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}.${month}.${year} ${hours}:${minutes}`;
  }

  private formatWeekdayLabel(value: string | null): string | null {
    if (!value) {
      return null;
    }

    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) {
      return null;
    }

    const label = new Intl.DateTimeFormat('sk-SK', { weekday: 'long' }).format(date);
    return label.charAt(0).toUpperCase() + label.slice(1);
  }

  private formatDateRangeLabel(startAt: string | null, endAt: string | null): string | null {
    const startLabel = this.formatDateTimeLabel(startAt);
    const endLabel = this.formatDateTimeLabel(endAt);

    if (startLabel && endLabel) {
      return `${startLabel} - ${endLabel}`;
    }

    return startLabel ?? endLabel;
  }

  private normalizeText(value: unknown): string | null {
    if (typeof value !== 'string') {
      return null;
    }

    const trimmed = value.trim();
    return trimmed ? trimmed : null;
  }

  private normalizeBody(value: unknown, originalSource: unknown): string {
    if (typeof value !== 'string') {
      return '';
    }

    const body = value.replace(/\r\n/g, '\n');
    const isEcavSource = typeof originalSource === 'string' && /ecav\.sk/i.test(originalSource);

    if (!isEcavSource) {
      return body;
    }

    return body
      .replace(/\n{0,2}Odkazy:\nPozv[aá]nky:\s*https?:\/\/www\.ecav\.sk\/aktuality\/pozvanky\/?\s*$/i, '')
      .trimEnd();
  }

  private toMunicipality(api: EventApiMunicipality | null | undefined): EventMunicipality {
    if (!api) {
      return this.emptyMunicipality;
    }

    return {
      id: api.id,
      fullname: api.fullname,
      shortname: api.shortname,
      zip: api.zip ?? null,
      districtId: api.district_id ?? null,
      regionId: api.region_id ?? null,
    };
  }

  index(
    page?: number,
    perPage?: number,
    scope: EventListScope = 'dashboard',
    filters?: FilterParams,
  ): Observable<EventListResult> {
    const endpoint = scope === 'admin' ? API_ENDPOINTS.adminEvents : API_ENDPOINTS.events;
    return this.listFrom(endpoint, page, perPage, filters);
  }

  publicIndex(
    page?: number,
    perPage?: number,
    filters?: FilterParams,
  ): Observable<EventListResult> {
    return this.listFrom(API_ENDPOINTS.eventsPublic, page, perPage, filters);
  }

  private listFrom(
    endpoint: string,
    page?: number,
    perPage?: number,
    filters?: FilterParams,
  ): Observable<EventListResult> {
    let params = new HttpParams();

    if (page !== undefined) {
      params = params.set('page', page);
    }
    if (perPage !== undefined) {
      params = params.set('per_page', perPage);
    }

    if (filters?.published !== undefined) {
      params = params.set('published', String(filters.published));
    }
    if (filters?.unpublished !== undefined) {
      params = params.set('unpublished', String(filters.unpublished));
    }
    if (filters?.blocked !== undefined) {
      params = params.set('blocked', String(filters.blocked));
    }
    if (filters?.status) {
      params = params.set('status', filters.status);
    }
    if (filters?.deleted !== undefined) {
      params = params.set('deleted', String(filters.deleted));
    }
    if (filters?.search?.trim()) {
      params = params.set('search', filters.search.trim());
    }
    if (filters?.municipality !== undefined) {
      params = params.set('municipality', String(filters.municipality));
    }

    return this.http.get<EventListEnvelope>(endpoint, { params }).pipe(
      map((rawResponse) => {
        const response = this.unwrapListResponse(rawResponse);
        const items = (response.data ?? []).map((item) => this.toEventItem(item));
        const currentPageResolved = Math.max(
          1,
          response.current_page ?? response.meta?.current_page ?? page ?? 1,
        );
        const perPageResolved = Math.max(
          1,
          response.per_page ?? response.meta?.per_page ?? perPage ?? items.length ?? 1,
        );
        const totalResolved =
          response.total ??
          response.meta?.total ??
          Math.max(items.length, (currentPageResolved - 1) * perPageResolved + items.length);
        const totalPagesResolved =
          response.last_page ??
          response.meta?.last_page ??
          Math.max(currentPageResolved, Math.ceil(totalResolved / perPageResolved));

        return {
          items,
          currentPage: currentPageResolved,
          perPage: perPageResolved,
          totalPages: totalPagesResolved,
          total: totalResolved,
          permissions: {
            create: response.meta?.permissions?.create ?? true,
          },
          allowedStatuses: sanitizeAllowedStatuses(response.meta?.allowed_statuses),
        };
      }),
    );
  }

  show(id: number, scope: EventShowScope = 'public'): Observable<EventItem> {
    const endpoint =
      scope === 'admin'
        ? API_ENDPOINTS.adminEvents
        : scope === 'dashboard'
          ? API_ENDPOINTS.events
          : API_ENDPOINTS.eventsPublic;

    return this.http
      .get<EventShowEnvelope>(`${endpoint}/${id}`)
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  create(payload: EventUpsertPayload): Observable<EventItem> {
    return this.http
      .post<EventShowEnvelope>(API_ENDPOINTS.events, payload)
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  createWithFiles(
    payload: EventUpsertPayload,
    files: File[],
    options?: EventFileUploadOptions,
  ): Observable<EventItem> {
    const formData = new FormData();

    if (payload.canal_id !== undefined) {
      formData.set('canal_id', String(payload.canal_id));
    }
    if (payload.municipality_id !== undefined) {
      formData.set('municipality_id', String(payload.municipality_id));
    }
    formData.set('name', payload.name);
    if (payload.body) {
      formData.set('body', payload.body);
    }
    if (payload.start_at) {
      formData.set('start_at', payload.start_at);
    }
    if (payload.end_at) {
      formData.set('end_at', payload.end_at);
    }

    if (payload.venue_id !== null && payload.venue_id !== undefined) {
      formData.set('venue_id', String(payload.venue_id));
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.status) {
      formData.set('status', payload.status);
    }
    if (payload.published_at !== undefined) {
      formData.set('published_at', payload.published_at ?? '');
    }
    if (payload.website) {
      formData.set('website', payload.website);
    }
    if (payload.registration_deadline_at !== undefined) {
      formData.set('registration_deadline_at', payload.registration_deadline_at ?? '');
    }

    if (options?.file_type) {
      formData.set('file_type', options.file_type);
    }

    if (options?.file_disk?.trim()) {
      formData.set('file_disk', options.file_disk.trim());
    }

    if (options?.make_primary_file !== undefined) {
      formData.set('make_primary_file', options.make_primary_file ? '1' : '0');
    }

    for (const file of files) {
      formData.append('files[]', file, file.name);
    }

    return this.http
      .post<EventShowEnvelope>(API_ENDPOINTS.events, formData)
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  updateStatus(
    id: number,
    event: EventItem,
    status: ModelStatus,
    publishedAt: string | null,
    deletedAt: string | null,
  ): Observable<EventItem> {
    return this.update(id, {
      canal_id: event.canalId,
      municipality_id: event.municipalityId,
      venue_id: event.venueId,
      name: event.name,
      slug: event.slug,
      body: event.body,
      body_ai: event.body_ai,
      ...(event.startAt ? { start_at: event.startAt } : {}),
      ...(event.endAt ? { end_at: event.endAt } : {}),
      website: event.website,
      status,
      published_at: publishedAt,
      deleted_at: deletedAt,
    });
  }

  togglePublishedStatus(event: EventItem): Observable<EventItem> {
    const nextStatus = togglePublishedModelStatus(event.status);

    if (nextStatus === MODEL_STATUS.Published) {
      return this.publish(event.id);
    }

    return this.updateStatus(event.id, event, nextStatus, null, event.deletedAt);
  }

  update(id: number, payload: Partial<EventUpsertPayload>): Observable<EventItem> {
    return this.http
      .put<EventShowEnvelope>(`${API_ENDPOINTS.events}/${id}`, this.normalizeUpdatePayload(payload))
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  updateWithFiles(
    id: number,
    payload: Partial<EventUpsertPayload>,
    files: File[],
    options?: EventFileUploadOptions,
  ): Observable<EventItem> {
    const formData = new FormData();
    formData.set('_method', 'PUT');

    if (payload.canal_id !== undefined) {
      formData.set('canal_id', String(payload.canal_id));
    }
    if (payload.municipality_id !== undefined) {
      formData.set('municipality_id', String(payload.municipality_id));
    }
    if (payload.venue_id !== null && payload.venue_id !== undefined) {
      formData.set('venue_id', String(payload.venue_id));
    }
    if (payload.name) {
      formData.set('name', payload.name);
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.body) {
      formData.set('body', payload.body);
    }
    if (payload.start_at) {
      formData.set('start_at', payload.start_at);
    }
    if (payload.end_at) {
      formData.set('end_at', payload.end_at);
    }
    if (payload.status) {
      formData.set('status', payload.status);
    }
    if (payload.published_at !== undefined) {
      formData.set('published_at', payload.published_at ?? '');
    }
    if (payload.website) {
      formData.set('website', payload.website);
    }
    if (payload.registration_deadline_at !== undefined) {
      formData.set('registration_deadline_at', payload.registration_deadline_at ?? '');
    }

    if (options?.file_type) {
      formData.set('file_type', options.file_type);
    }
    if (options?.file_disk?.trim()) {
      formData.set('file_disk', options.file_disk.trim());
    }
    if (options?.make_primary_file !== undefined) {
      formData.set('make_primary_file', options.make_primary_file ? '1' : '0');
    }

    for (const file of files) {
      formData.append('files[]', file, file.name);
    }

    return this.http
      .post<EventShowEnvelope>(`${API_ENDPOINTS.events}/${id}`, formData)
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.events}/${id}`);
  }

  restore(id: number): Observable<void> {
    return this.http.post<void>(`${API_ENDPOINTS.events}/${id}/restore`, {});
  }

  publish(id: number): Observable<EventItem> {
    return this.http
      .post<EventShowEnvelope>(`${API_ENDPOINTS.events}/${id}/publish`, {})
      .pipe(map((response) => this.toEventItem(this.unwrapShowResponse(response))));
  }

  deleteUploadedFile(fileId: number | string): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.files}/${fileId}`);
  }

  private toEventItem(api: EventApiItem): EventItem {
    const resolvedImage = extractPrimaryImageUrl(api, this.fallbackImage) ?? this.fallbackImage;
    const uploadedFiles = extractUploadedFiles(api);
    const canal = api.canal ?? {
      ...this.emptyLocation,
      id: api.canal_id,
    };
    const venue = api.venue ?? {
      ...this.emptyLocation,
      id: api.venue_id ?? 0,
    };
    const startAt = this.normalizeDateTime(api.start_at);
    const endAt = this.normalizeDateTime(api.end_at);
    const apiDateRangeDays = api.date_range_days;

    return {
      id: api.id,
      canalId: api.canal_id,
      municipalityId: api.municipality_id,
      venueId: api.venue_id,
      name: api.name,
      slug: api.slug,
      body: this.normalizeBody(api.body, api.orginal_source),
      body_ai: api.body_ai,
      status: sanitizeModelStatus(api.status),
      canalName: canal.name,
      startAt,
      endAt,
      dateRangeLabel:
        this.normalizeText(api.date_range_label) ?? this.formatDateRangeLabel(startAt, endAt),
      dateRangeDays: {
        start: this.normalizeText(apiDateRangeDays?.start) ?? this.formatWeekdayLabel(startAt),
        end: this.normalizeText(apiDateRangeDays?.end) ?? this.formatWeekdayLabel(endAt),
      },
      registrationDeadlineAt: this.normalizeDateTime(api.registration_deadline_at),
      publishedAt: this.normalizeDateTime(api.published_at),
      deletedAt: this.normalizeDateTime(api.deleted_at),
      website: api.website,
      locationName: api.location_name ?? null,
      street: api.street ?? null,
      postcode: api.postcode ?? null,
      country: api.country ?? null,
      latitude: api.latitude ?? null,
      longitude: api.longitude ?? null,
      imageUrl: resolvedImage,
      uploadedFiles,
      permissions: this.resolvePermissions(api.permissions),
      allowedStatuses: sanitizeAllowedStatuses(api.allowed_statuses),
      municipality: this.toMunicipality(api.municipality),
      canal,
      venue,
    };
  }

  private resolvePermissions(permissions: Partial<ModelPermissions> | undefined): ModelPermissions {
    return {
      view: permissions?.view ?? false,
      update: permissions?.update ?? false,
      publish: permissions?.publish ?? false,
      delete: permissions?.delete ?? false,
      archive: permissions?.archive ?? false,
      restore: permissions?.restore ?? false,
    };
  }

  private unwrapListResponse(response: EventListEnvelope): EventListApiResponse {
    return response;
  }

  private unwrapShowResponse(response: EventShowEnvelope): EventApiItem {
    if ('data' in response) {
      return response.data;
    }
    return response;
  }
}
