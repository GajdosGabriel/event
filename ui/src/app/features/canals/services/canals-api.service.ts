
import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { CanalIdentityMode, sanitizeCanalIdentityMode } from '../models/canal-identity-mode';
import { CanalApiItem, CanalItem } from '../models/canal.model';
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

export interface CanalUpsertPayload {
  municipality_id: number;
  venue_id?: number | null;
  identity_mode: CanalIdentityMode;
  name: string;
  slug?: string;
  title_prefix?: string;
  title_suffix?: string;
  email: string;
  body: string;
  status?: ModelStatus;
  published_at?: string | null;
  website?: string | null;
}

export interface CanalFileUploadOptions {
  file_type?: 'image' | 'card' | 'file';
  file_disk?: string;
  make_primary_file?: boolean;
}

export interface CanalListResult {
  items: CanalItem[];
  currentPage: number;
  perPage: number;
  totalPages: number;
  total: number;
  permissions: CollectionPermissions;
  allowedStatuses: AllowedStatusOption[];
}

export type CanalListScope = 'dashboard' | 'admin';
export type CanalShowScope = CanalListScope;

interface CanalListApiResponse {
  current_page: number;
  data: CanalApiItem[];
  per_page?: number;
  last_page?: number;
  total?: number;
  meta?: {
    permissions?: {
      create?: boolean;
    };
    allowed_statuses?: unknown[];
  };
}

type CanalListEnvelope = CanalListApiResponse;
type CanalShowEnvelope = CanalApiItem | { data: CanalApiItem };

@Injectable({ providedIn: 'root' })
export class CanalsApiService {
  private readonly http = inject(HttpClient);
  private readonly fallbackImage = 'https://placehold.co/800x500/e2e8f0/334155?text=Canal';

  private endpointForScope(scope: CanalListScope = 'dashboard'): string {
    return scope === 'admin' ? API_ENDPOINTS.adminCanals : API_ENDPOINTS.canals;
  }

  index(
    page?: number,
    perPage?: number,
    filters?: FilterParams,
    scope: CanalListScope = 'dashboard'
  ): Observable<CanalListResult> {
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

    return this.http.get<CanalListEnvelope>(this.endpointForScope(scope), { params }).pipe(
      map((rawResponse) => {
        const response = this.unwrapListResponse(rawResponse);
        const items = (response.data ?? []).map((item) => this.toCanalItem(item));
        const perPageResolved = Math.max(1, response.per_page ?? perPage ?? items.length ?? 1);
        const totalResolved = response.total ?? items.length;
        const totalPagesResolved =
          response.last_page ?? Math.max(1, Math.ceil(totalResolved / perPageResolved));

        return {
          items,
          currentPage: response.current_page ?? 1,
          perPage: perPageResolved,
          totalPages: totalPagesResolved,
          total: totalResolved,
          permissions: {
            create: response.meta?.permissions?.create ?? true
          },
          allowedStatuses: sanitizeAllowedStatuses(response.meta?.allowed_statuses)
        };
      }),
    );
  }

  show(id: number, scope: CanalShowScope = 'dashboard'): Observable<CanalItem> {
    return this.http
      .get<CanalShowEnvelope>(`${this.endpointForScope(scope)}/${id}`)
      .pipe(map((response) => this.toCanalItem(this.unwrapShowResponse(response))));
  }

  create(payload: CanalUpsertPayload, scope: CanalListScope = 'dashboard'): Observable<CanalItem> {
    return this.http
      .post<CanalShowEnvelope>(this.endpointForScope(scope), payload)
      .pipe(map((response) => this.toCanalItem(this.unwrapShowResponse(response))));
  }

  createWithFiles(
    payload: CanalUpsertPayload,
    files: File[],
    options?: CanalFileUploadOptions,
    scope: CanalListScope = 'dashboard'
  ): Observable<CanalItem> {
    const formData = new FormData();

    formData.set('municipality_id', String(payload.municipality_id));
    formData.set('identity_mode', payload.identity_mode);
    formData.set('name', payload.name);
    formData.set('email', payload.email);
    formData.set('body', payload.body);

    if (payload.venue_id !== null && payload.venue_id !== undefined) {
      formData.set('venue_id', String(payload.venue_id));
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.title_prefix) {
      formData.set('title_prefix', payload.title_prefix);
    }
    if (payload.title_suffix) {
      formData.set('title_suffix', payload.title_suffix);
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
      .post<CanalShowEnvelope>(this.endpointForScope(scope), formData)
      .pipe(map((response) => this.toCanalItem(this.unwrapShowResponse(response))));
  }

  update(
    id: number,
    payload: Partial<CanalUpsertPayload>,
    scope: CanalListScope = 'dashboard'
  ): Observable<CanalItem> {
    return this.http
      .put<CanalShowEnvelope>(`${this.endpointForScope(scope)}/${id}`, payload)
      .pipe(map((response) => this.toCanalItem(this.unwrapShowResponse(response))));
  }

  updateWithFiles(
    id: number,
    payload: Partial<CanalUpsertPayload>,
    files: File[],
    options?: CanalFileUploadOptions,
    scope: CanalListScope = 'dashboard'
  ): Observable<CanalItem> {
    const formData = new FormData();
    formData.set('_method', 'PUT');

    if (payload.municipality_id !== undefined) {
      formData.set('municipality_id', String(payload.municipality_id));
    }
    if (payload.venue_id !== null && payload.venue_id !== undefined) {
      formData.set('venue_id', String(payload.venue_id));
    }
    if (payload.identity_mode) {
      formData.set('identity_mode', payload.identity_mode);
    }
    if (payload.name) {
      formData.set('name', payload.name);
    }
    if (payload.slug) {
      formData.set('slug', payload.slug);
    }
    if (payload.title_prefix) {
      formData.set('title_prefix', payload.title_prefix);
    }
    if (payload.title_suffix) {
      formData.set('title_suffix', payload.title_suffix);
    }
    if (payload.email) {
      formData.set('email', payload.email);
    }
    if (payload.body) {
      formData.set('body', payload.body);
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
      .post<CanalShowEnvelope>(`${this.endpointForScope(scope)}/${id}`, formData)
      .pipe(map((response) => this.toCanalItem(this.unwrapShowResponse(response))));
  }

  updateStatus(
    id: number,
    canal: CanalItem,
    status: ModelStatus,
    publishedAt: string | null,
    scope: CanalListScope = 'dashboard'
  ): Observable<CanalItem> {
    return this.update(id, {
      municipality_id: canal.municipalityId,
      venue_id: canal.venueId,
      identity_mode: canal.identityMode,
      name: canal.name,
      slug: canal.slug,
      title_prefix: canal.titlePrefix ?? undefined,
      title_suffix: canal.titleSuffix ?? undefined,
      email: canal.email,
      body: canal.body,
      status,
      published_at: publishedAt,
      website: canal.website
    }, scope);
  }

  togglePublishedStatus(canal: CanalItem, scope: CanalListScope = 'dashboard'): Observable<CanalItem> {
    const nextStatus = togglePublishedModelStatus(canal.status);
    const nextPublishedAt =
      nextStatus === MODEL_STATUS.Published ? canal.publishedAt ?? this.getCurrentDateTime() : null;

    return this.updateStatus(canal.id, canal, nextStatus, nextPublishedAt, scope);
  }

  delete(id: number, scope: CanalListScope = 'dashboard'): Observable<void> {
    return this.http.delete<void>(`${this.endpointForScope(scope)}/${id}`);
  }

  restore(id: number, scope: CanalListScope = 'dashboard'): Observable<void> {
    return this.http.post<void>(`${this.endpointForScope(scope)}/${id}/restore`, {});
  }

  deleteUploadedFile(fileId: number | string): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.files}/${fileId}`);
  }

  private toCanalItem(api: CanalApiItem): CanalItem {
    const resolvedImage = extractPrimaryImageUrl(api, this.fallbackImage) ?? this.fallbackImage;
    const uploadedFiles = extractUploadedFiles(api, resolvedImage || null);

    return {
      id: api.id,
      municipalityId: api.municipality_id,
      venueId: api.venue_id,
      identityMode: sanitizeCanalIdentityMode(api.identity_mode),
      name: api.name,
      slug: api.slug,
      titlePrefix: api.title_prefix,
      titleSuffix: api.title_suffix,
      email: api.email,
      emailVerifiedAt: api.email_verified_at,
      body: api.body,
      imageUrl: resolvedImage,
      status: sanitizeModelStatus(api.status),
      publishedAt: api.published_at,
      website: api.website,
      createdAt: api.created_at,
      updatedAt: api.updated_at,
      deletedAt: api.deleted_at,
      registrationSource: api.registration_source,
      canal: `${api.title_prefix ?? ''}${api.name}${api.title_suffix ?? ''}`,
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

  private unwrapListResponse(response: CanalListEnvelope): CanalListApiResponse {
    return response;
  }

  private unwrapShowResponse(response: CanalShowEnvelope): CanalApiItem {
    if ('data' in response) {
      return response.data;
    }
    return response;
  }

  private getCurrentDateTime(): string {
    const date = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
  }
}
