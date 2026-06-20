import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { VenueApiItem, VenueItem } from '../models/venue.model';
import { extractPrimaryImageUrl, extractUploadedFiles } from '../../../shared/utils/uploaded-files.utils';
import { ModelStatus, AllowedStatusOption, sanitizeAllowedStatuses, sanitizeModelStatus, togglePublishedModelStatus } from '../../../shared/models/model-status';
import { CollectionPermissions, ModelPermissions } from '../../../shared/models/model-permissions';
import { FilterParams } from '../../../shared/models/filter-params.model';

export interface VenueUpsertPayload {
  canal_id: number;
  village_id: number;
  name: string;
  street?: string | null;
  postcode?: string | null;
  slug?: string;
  body: string;
  website?: string | null;
  email?: string | null;
  phone?: string | null;
  country?: string | null;
  latitude?: string | null;
  longitude?: string | null;
  capacity?: number | null;
  opening_hours?: unknown[] | null;
  category?: string | null;
  status?: ModelStatus | null;
}

export interface VenueFileUploadOptions {
  file_type?: 'image' | 'card' | 'file';
  file_disk?: string;
  make_primary_file?: boolean;
}

export interface VenueDetectPayload {
  name: string;
  city: string;
  country?: string | null;
}

interface VenueDetectedPayload {
  village_id?: number | null;
  city?: string | null;
  name?: string | null;
  street?: string | null;
  postcode?: string | null;
  body?: string | null;
  object_description?: string | null;
  long_description?: string | null;
  website?: string | null;
  country?: string | null;
  latitude?: string | number | null;
  longitude?: string | number | null;
  capacity?: string | number | null;
  opening_hours?: unknown[] | string | null;
  category?: string | null;
  status?: string | null;
  image_url?: string | null;
  image_urls?: string[] | null;
  logo_url?: string | null;
  email?: string | null;
  phone?: string | null;
}

export interface VenueDetectResponse {
  success: boolean;
  message?: string;
  error?: string;
  venue_payload?: VenueDetectedPayload;
  venue_store_payload?: VenueDetectedPayload;
  missing_required_fields?: string[];
  can_store_immediately?: boolean;
  attached_files?: unknown[];
}

export interface VenueListResult {
  items: VenueItem[];
  currentPage: number;
  perPage: number;
  totalPages: number;
  total: number;
  permissions: CollectionPermissions;
  allowedStatuses: AllowedStatusOption[];
}

export type VenueListScope = 'dashboard' | 'admin';
export type VenueShowScope = VenueListScope;

interface VenueListApiResponse {
  current_page?: number;
  data?: VenueApiItem[];
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

type VenueListEnvelope = VenueListApiResponse;
type VenueShowEnvelope = VenueApiItem | { data: VenueApiItem };

@Injectable({ providedIn: 'root' })
export class VenuesApiService {
  private readonly http = inject(HttpClient);
  private readonly fallbackImage = 'https://placehold.co/800x500/e2e8f0/334155?text=Venue';

  private endpointForScope(scope: VenueListScope = 'dashboard'): string {
    return scope === 'admin' ? API_ENDPOINTS.adminVenues : API_ENDPOINTS.venues;
  }

  private appendOpeningHours(formData: FormData, openingHours: unknown[] | null | undefined): void {
    if (!Array.isArray(openingHours) || openingHours.length === 0) {
      return;
    }

    for (let index = 0; index < openingHours.length; index++) {
      const entry = openingHours[index];
      formData.append(`opening_hours[${index}]`, this.serializeFormDataValue(entry));
    }
  }

  private serializeFormDataValue(value: unknown): string {
    if (typeof value === 'string') {
      return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
      return String(value);
    }

    if (value === null || value === undefined) {
      return '';
    }

    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  }

  private normalizeOpeningHours(value: unknown): unknown[] | null {
    if (value === null || value === undefined) {
      return null;
    }

    if (Array.isArray(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const trimmed = value.trim();
      if (!trimmed) {
        return null;
      }

      try {
        const parsed = JSON.parse(trimmed);
        return Array.isArray(parsed) ? parsed : [parsed];
      } catch {
        return [trimmed];
      }
    }

    return [value];
  }

  index(
    page?: number,
    perPage?: number,
    filters?: FilterParams,
    scope: VenueListScope = 'dashboard'
  ): Observable<VenueListResult> {
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

    return this.http.get<VenueListEnvelope>(this.endpointForScope(scope), { params }).pipe(
      map((rawResponse) => {
        const response = this.unwrapListResponse(rawResponse);
        const items = (response.data ?? []).map((item) => this.toVenueItem(item));
        const currentPageResolved = Math.max(
          1,
          response.current_page ?? response.meta?.current_page ?? page ?? 1
        );
        const perPageResolved = Math.max(
          1,
          response.per_page ?? response.meta?.per_page ?? perPage ?? items.length ?? 1
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
            create: response.meta?.permissions?.create ?? true
          },
          allowedStatuses: sanitizeAllowedStatuses(response.meta?.allowed_statuses)
        };
      })
    );
  }

  show(id: number, scope: VenueShowScope = 'dashboard'): Observable<VenueItem> {
    return this.http
      .get<VenueShowEnvelope>(`${this.endpointForScope(scope)}/${id}`)
      .pipe(map((response) => this.toVenueItem(this.unwrapShowResponse(response))));
  }

  create(payload: VenueUpsertPayload, scope: VenueListScope = 'dashboard'): Observable<VenueItem> {
    return this.http
      .post<VenueShowEnvelope>(this.endpointForScope(scope), payload)
      .pipe(map((response) => this.toVenueItem(this.unwrapShowResponse(response))));
  }

  createWithFiles(
    payload: VenueUpsertPayload,
    files: File[],
    options?: VenueFileUploadOptions,
    scope: VenueListScope = 'dashboard'
  ): Observable<VenueItem> {
    const formData = new FormData();

    formData.set('canal_id', String(payload.canal_id));
    formData.set('village_id', String(payload.village_id));
    formData.set('name', payload.name);
    formData.set('body', payload.body);

    if (payload.street) {
      formData.set('street', payload.street);
    }
    if (payload.postcode) {
      formData.set('postcode', payload.postcode);
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.website) {
      formData.set('website', payload.website);
    }
    if (payload.email) {
      formData.set('email', payload.email);
    }
    if (payload.phone) {
      formData.set('phone', payload.phone);
    }
    if (payload.country) {
      formData.set('country', payload.country);
    }
    if (payload.latitude) {
      formData.set('latitude', payload.latitude);
    }
    if (payload.longitude) {
      formData.set('longitude', payload.longitude);
    }
    if (payload.capacity !== null && payload.capacity !== undefined) {
      formData.set('capacity', String(payload.capacity));
    }
    this.appendOpeningHours(formData, payload.opening_hours);
    if (payload.category) {
      formData.set('category', payload.category);
    }
    if (payload.status) {
      formData.set('status', payload.status);
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
      .post<VenueShowEnvelope>(this.endpointForScope(scope), formData)
      .pipe(map((response) => this.toVenueItem(this.unwrapShowResponse(response))));
  }

  update(
    id: number,
    payload: Partial<VenueUpsertPayload>,
    scope: VenueListScope = 'dashboard'
  ): Observable<VenueItem> {
    return this.http
      .put<VenueShowEnvelope>(`${this.endpointForScope(scope)}/${id}`, payload)
      .pipe(map((response) => this.toVenueItem(this.unwrapShowResponse(response))));
  }

  detect(payload: VenueDetectPayload): Observable<VenueDetectResponse> {
    return this.http.post<VenueDetectResponse>(API_ENDPOINTS.venuesDetect, payload);
  }

  updateWithFiles(
    id: number,
    payload: Partial<VenueUpsertPayload>,
    files: File[],
    options?: VenueFileUploadOptions,
    scope: VenueListScope = 'dashboard'
  ): Observable<VenueItem> {
    const formData = new FormData();
    formData.set('_method', 'PUT');

    if (payload.canal_id !== undefined) {
      formData.set('canal_id', String(payload.canal_id));
    }
    if (payload.village_id !== undefined) {
      formData.set('village_id', String(payload.village_id));
    }
    if (payload.name) {
      formData.set('name', payload.name);
    }
    if (payload.street) {
      formData.set('street', payload.street);
    }
    if (payload.postcode) {
      formData.set('postcode', payload.postcode);
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.body) {
      formData.set('body', payload.body);
    }
    if (payload.website) {
      formData.set('website', payload.website);
    }
    if (payload.email) {
      formData.set('email', payload.email);
    }
    if (payload.phone) {
      formData.set('phone', payload.phone);
    }
    if (payload.country) {
      formData.set('country', payload.country);
    }
    if (payload.latitude) {
      formData.set('latitude', payload.latitude);
    }
    if (payload.longitude) {
      formData.set('longitude', payload.longitude);
    }
    if (payload.capacity !== null && payload.capacity !== undefined) {
      formData.set('capacity', String(payload.capacity));
    }
    this.appendOpeningHours(formData, payload.opening_hours);
    if (payload.category) {
      formData.set('category', payload.category);
    }
    if (payload.status) {
      formData.set('status', payload.status);
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
      .post<VenueShowEnvelope>(`${this.endpointForScope(scope)}/${id}`, formData)
      .pipe(map((response) => this.toVenueItem(this.unwrapShowResponse(response))));
  }

  updateStatus(
    id: number,
    venue: VenueItem,
    status: ModelStatus,
    scope: VenueListScope = 'dashboard'
  ): Observable<VenueItem> {
    return this.update(id, {
      canal_id: venue.canalId,
      village_id: venue.villageId,
      name: venue.name,
      street: venue.street,
      postcode: venue.postcode,
      slug: venue.slug,
      body: venue.body,
      website: venue.website,
      country: venue.country,
      latitude: venue.latitude,
      longitude: venue.longitude,
      capacity: venue.capacity,
      opening_hours: venue.openingHours,
      category: venue.category,
      status
    }, scope);
  }

  togglePublishedStatus(venue: VenueItem, scope: VenueListScope = 'dashboard'): Observable<VenueItem> {
    return this.updateStatus(venue.id, venue, togglePublishedModelStatus(venue.status), scope);
  }

  delete(id: number, scope: VenueListScope = 'dashboard'): Observable<void> {
    return this.http.delete<void>(`${this.endpointForScope(scope)}/${id}`);
  }

  restore(id: number, scope: VenueListScope = 'dashboard'): Observable<void> {
    return this.http.post<void>(`${this.endpointForScope(scope)}/${id}/restore`, {});
  }

  deleteUploadedFile(fileId: number | string): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.files}/${fileId}`);
  }

  private toVenueItem(api: VenueApiItem): VenueItem {
    const resolvedImage = extractPrimaryImageUrl(api, this.fallbackImage) ?? this.fallbackImage;
    const uploadedFiles = extractUploadedFiles(api, resolvedImage || null);

    return {
      id: api.id,
      canalId: api.canal_id,
      villageId: api.village_id,
      name: api.name,
      street: api.street,
      postcode: api.postcode,
      slug: api.slug,
      body: api.body,
      imageUrl: resolvedImage,
      website: api.website,
      email: api.email,
      phone: api.phone,
      country: api.country,
      latitude: api.latitude,
      longitude: api.longitude,
      capacity: api.capacity,
      openingHours: this.normalizeOpeningHours(api.opening_hours),
      category: api.category,
      status: sanitizeModelStatus(api.status),
      deletedAt: api.deleted_at,
      createdAt: api.created_at,
      updatedAt: api.updated_at,
      uploadedFiles,
      permissions: this.resolvePermissions(api.permissions),
      allowedStatuses: sanitizeAllowedStatuses(api.allowed_statuses)
    };
  }

  private resolvePermissions(
    permissions: Partial<ModelPermissions> | undefined
  ): ModelPermissions {
    return {
      view: permissions?.view ?? false,
      update: permissions?.update ?? false,
      publish: permissions?.publish ?? false,
      delete: permissions?.delete ?? false,
      archive: permissions?.archive ?? false,
      restore: permissions?.restore ?? false
    };
  }

  private unwrapListResponse(response: VenueListEnvelope): VenueListApiResponse {
    return response;
  }

  private unwrapShowResponse(response: VenueShowEnvelope): VenueApiItem {
    if ('data' in response) {
      return response.data;
    }
    return response;
  }
}
