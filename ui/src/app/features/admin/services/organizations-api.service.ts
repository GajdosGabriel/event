import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { OrganizationItem, OrganizationUpsertPayload } from '../models/organization.model';

export type OrganizationsApiScope = 'dashboard' | 'admin';

type OrganizationEnvelope = OrganizationApiItem | { data?: OrganizationApiItem };
type OrganizationListEnvelope = OrganizationApiItem[] | { data?: OrganizationApiItem[] };

type OrganizationApiItem = Record<string, unknown> & {
  id?: number | string;
  name?: string;
  slug?: string | null;
  body?: string | null;
  website?: string | null;
  email?: string | null;
  phone?: string | null;
  status?: string | null;
  deleted_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

@Injectable({ providedIn: 'root' })
export class OrganizationsApiService {
  private readonly http = inject(HttpClient);

  list(scope: OrganizationsApiScope): Observable<OrganizationItem[]> {
    return this.http
      .get<OrganizationListEnvelope>(this.basePath(scope))
      .pipe(map((response) => this.unwrapList(response).map((item) => this.toOrganizationItem(item))));
  }

  show(scope: OrganizationsApiScope, id: number): Observable<OrganizationItem> {
    return this.http
      .get<OrganizationEnvelope>(`${this.basePath(scope)}/${id}`)
      .pipe(map((response) => this.toOrganizationItem(this.unwrapOne(response))));
  }

  create(scope: OrganizationsApiScope, payload: OrganizationUpsertPayload): Observable<OrganizationItem> {
    return this.http
      .post<OrganizationEnvelope>(this.basePath(scope), payload)
      .pipe(map((response) => this.toOrganizationItem(this.unwrapOne(response))));
  }

  update(
    scope: OrganizationsApiScope,
    id: number,
    payload: Partial<OrganizationUpsertPayload>
  ): Observable<OrganizationItem> {
    return this.http
      .put<OrganizationEnvelope>(`${this.basePath(scope)}/${id}`, payload)
      .pipe(map((response) => this.toOrganizationItem(this.unwrapOne(response))));
  }

  delete(scope: OrganizationsApiScope, id: number): Observable<void> {
    return this.http.delete<void>(`${this.basePath(scope)}/${id}`);
  }

  restore(scope: OrganizationsApiScope, id: number): Observable<void> {
    return this.http.post<void>(`${this.basePath(scope)}/${id}/restore`, {});
  }

  private basePath(scope: OrganizationsApiScope): string {
    return scope === 'admin'
      ? API_ENDPOINTS.adminOrganizations
      : API_ENDPOINTS.dashboardOrganizations;
  }

  private unwrapList(response: OrganizationListEnvelope): OrganizationApiItem[] {
    if (Array.isArray(response)) {
      return response;
    }

    if (Array.isArray(response.data)) {
      return response.data;
    }

    return [];
  }

  private unwrapOne(response: OrganizationEnvelope): OrganizationApiItem {
    if (
      'data' in response &&
      response.data &&
      typeof response.data === 'object' &&
      !Array.isArray(response.data)
    ) {
      return response.data as OrganizationApiItem;
    }

    return response;
  }

  private toOrganizationItem(api: OrganizationApiItem): OrganizationItem {
    const idRaw = api.id;
    const id =
      typeof idRaw === 'number'
        ? idRaw
        : typeof idRaw === 'string' && idRaw.trim() && !Number.isNaN(Number(idRaw))
          ? Number(idRaw)
          : 0;

    return {
      id,
      name: typeof api.name === 'string' ? api.name : '',
      slug: typeof api.slug === 'string' ? api.slug : null,
      body: typeof api.body === 'string' ? api.body : null,
      website: typeof api.website === 'string' ? api.website : null,
      email: typeof api.email === 'string' ? api.email : null,
      phone: typeof api.phone === 'string' ? api.phone : null,
      status: typeof api.status === 'string' ? api.status : null,
      deletedAt: typeof api.deleted_at === 'string' ? api.deleted_at : null,
      createdAt: typeof api.created_at === 'string' ? api.created_at : null,
      updatedAt: typeof api.updated_at === 'string' ? api.updated_at : null,
      raw: api
    };
  }
}
