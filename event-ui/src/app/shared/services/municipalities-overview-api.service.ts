import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../constants/api.constants';
import { extractPrimaryImageUrl } from '../utils/uploaded-files.utils';
import {
  MunicipalityOverviewItem,
  MunicipalityOverviewMunicipality
} from '../models/municipality-overview.model';

export type MunicipalityOverviewScope = 'public' | 'dashboard' | 'admin';
export type MunicipalityOverviewResource = 'events' | 'canals' | 'venues';

type MunicipalityOverviewApiItem = {
  municipality_id?: unknown;
  municipality_name?: unknown;
  municipality_shortname?: unknown;
  events_count?: unknown;
  primary_image?: unknown;
  owner?: unknown;
  municipality?: unknown;
};

type MunicipalityOverviewApiEnvelope = {
  data?: unknown;
};

@Injectable({ providedIn: 'root' })
export class MunicipalitiesOverviewApiService {
  private readonly http = inject(HttpClient);
  private readonly fallbackImage = 'https://placehold.co/400x240/e2e8f0/334155?text=Municipality';

  private readonly endpointByResourceAndScope: Record<MunicipalityOverviewResource, Partial<Record<MunicipalityOverviewScope, string>>> = {
    events: {
      public: API_ENDPOINTS.eventsMunicipalitiesOverviewPublic,
      dashboard: API_ENDPOINTS.eventsMunicipalitiesOverviewDashboard,
      admin: API_ENDPOINTS.eventsMunicipalitiesOverviewAdmin
    },
    canals: {
      dashboard: API_ENDPOINTS.canalsMunicipalitiesOverviewDashboard,
      admin: API_ENDPOINTS.canalsMunicipalitiesOverviewAdmin
    },
    venues: {
      dashboard: API_ENDPOINTS.venuesMunicipalitiesOverviewDashboard,
      admin: API_ENDPOINTS.venuesMunicipalitiesOverviewAdmin
    }
  };

  list(scope: MunicipalityOverviewScope, resource: MunicipalityOverviewResource = 'events'): Observable<MunicipalityOverviewItem[]> {
    const endpoint = this.endpointByResourceAndScope[resource][scope] ?? API_ENDPOINTS.eventsMunicipalitiesOverviewPublic;

    return this.http
      .get<MunicipalityOverviewApiEnvelope>(endpoint)
      .pipe(map((response) => this.normalizeResponse(response)));
  }

  private normalizeResponse(response: MunicipalityOverviewApiEnvelope): MunicipalityOverviewItem[] {
    const rows = Array.isArray(response?.data) ? response.data : [];

    return rows
      .map((row) => this.toItem(row))
      .filter((item): item is MunicipalityOverviewItem => item !== null);
  }

  private toItem(raw: unknown): MunicipalityOverviewItem | null {
    const item = this.toRecord(raw);

    if (!item) {
      return null;
    }

    const municipalityId = this.toNumber(item['municipality_id']);
    const municipalityName = this.toString(item['municipality_name']);

    if (municipalityId === null || !municipalityName) {
      return null;
    }

    return {
      municipalityId,
      municipalityName,
      municipalityShortname: this.toString(item['municipality_shortname']) || municipalityName,
      eventsCount: Math.max(0, this.toNumber(item['events_count']) ?? 0),
      thumbImage: extractPrimaryImageUrl(item, this.fallbackImage) ?? this.fallbackImage,
      owner: Boolean(item['owner']),
      municipality: this.toMunicipality(item['municipality'])
    };
  }

  private toMunicipality(raw: unknown): MunicipalityOverviewMunicipality | null {
    const record = this.toRecord(raw);

    if (!record) {
      return null;
    }

    const id = this.toNumber(record['id']);
    const fullname = this.toString(record['fullname']);

    if (id === null || !fullname) {
      return null;
    }

    return {
      id,
      fullname,
      shortname: this.toString(record['shortname']) || fullname,
      zip: this.toString(record['zip']) || null
    };
  }

  private toRecord(value: unknown): Record<string, unknown> | null {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }

    return null;
  }

  private toString(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
  }

  private toNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string' && value.trim()) {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
  }
}

