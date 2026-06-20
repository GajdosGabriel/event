import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { catchError, map, Observable, throwError } from 'rxjs';
import { API_BASE_URL, API_ENDPOINTS, API_ORIGIN } from '../../constants/api.constants';

export interface LookupOption {
  id: number;
  name: string;
  zip?: string | null;
}

type LookupApiItem = {
  id?: unknown;
  municipality_id?: unknown;
  village_id?: unknown;
  city_id?: unknown;
  name?: unknown;
  fullname?: unknown;
  shortname?: unknown;
  municipality?: unknown;
  village?: unknown;
  city?: unknown;
  title?: unknown;
  label?: unknown;
  zip?: unknown;
  postcode?: unknown;
  postal_code?: unknown;
};

type LookupEnvelope =
  | LookupApiItem[]
  | {
      data?: LookupApiItem[];
      items?: LookupApiItem[];
      ['public-index']?: { data?: LookupApiItem[] } | LookupApiItem[];
    };

@Injectable({ providedIn: 'root' })
export class LookupMunicipalityApiService {
  private readonly http = inject(HttpClient);

  listMunicipalities(): Observable<LookupOption[]> {
    const fallbackEndpoint = `${API_BASE_URL}/dashboard/municipalities`;

    return this.list(API_ENDPOINTS.municipalities).pipe(
      catchError((error: unknown) => {
        if (error instanceof HttpErrorResponse && (error.status === 403 || error.status === 404)) {
          return this.list(fallbackEndpoint);
        }

        return throwError(() => error);
      })
    );
  }

  list(endpoint: string): Observable<LookupOption[]> {
    return this.http
      .get<LookupEnvelope>(endpoint, {
        withCredentials: endpoint.startsWith(API_BASE_URL),
        headers: this.buildAuthHeaders(endpoint)
      })
      .pipe(
        map((response) => this.normalizeResponse(response)),
        map((items) =>
          items
            .map((item): LookupOption | null => {
              const id = this.toId(item);
              const name = this.toName(item);

              if (id === null || !name) {
                return null;
              }

              return { id, name, zip: this.toZip(item) } satisfies LookupOption;
            })
            .filter((item): item is LookupOption => item !== null)
        )
      );
  }

  private buildAuthHeaders(endpoint: string): HttpHeaders | undefined {
    if (!endpoint.startsWith(API_BASE_URL)) {
      return undefined;
    }

    let headers = new HttpHeaders({
      'X-Requested-With': 'XMLHttpRequest'
    });

    if (typeof window !== 'undefined') {
      const token = localStorage.getItem('auth_token');
      if (token) {
        headers = headers.set('Authorization', `Bearer ${token}`);
      }
    }

    return headers;
  }

  private normalizeResponse(response: LookupEnvelope): LookupApiItem[] {
    if (Array.isArray(response)) {
      return response;
    }

    const publicIndex = response['public-index'];
    if (Array.isArray(publicIndex)) {
      return publicIndex;
    }

    if (publicIndex && Array.isArray(publicIndex.data)) {
      return publicIndex.data;
    }

    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (Array.isArray(response.items)) {
      return response.items;
    }

    return [];
  }

  private toNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      if (Number.isFinite(parsed)) {
        return parsed;
      }
    }

    return null;
  }

  private toId(item: LookupApiItem): number | null {
    return (
      this.toNumber(item.id) ??
      this.toNumber(item.municipality_id) ??
      this.toNumber(item.village_id) ??
      this.toNumber(item.city_id)
    );
  }

  private toName(item: LookupApiItem): string {
    if (typeof item.name === 'string') {
      return item.name.trim();
    }

    if (typeof item.fullname === 'string') {
      return item.fullname.trim();
    }

    if (typeof item.shortname === 'string') {
      return item.shortname.trim();
    }

    if (typeof item.municipality === 'string') {
      return item.municipality.trim();
    }

    if (typeof item.village === 'string') {
      return item.village.trim();
    }

    if (typeof item.city === 'string') {
      return item.city.trim();
    }

    if (typeof item.title === 'string') {
      return item.title.trim();
    }

    if (typeof item.label === 'string') {
      return item.label.trim();
    }

    return '';
  }

  private toZip(item: LookupApiItem): string | null {
    const candidates = [item.zip, item.postcode, item.postal_code];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim()) {
        return candidate.trim();
      }

      if (typeof candidate === 'number' && Number.isFinite(candidate)) {
        return String(candidate);
      }
    }

    return null;
  }
}
