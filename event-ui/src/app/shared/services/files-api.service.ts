import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../constants/api.constants';
import { UploadedFileItem } from '../models/uploaded-file.model';

export type FileableType = 'canal' | 'event' | 'venue';

export interface DashboardFileListParams {
  fileable_type: FileableType;
  fileable_id: number;
}

export interface DashboardFileUploadPayload {
  fileable_type: FileableType;
  fileable_id: number;
  type: 'image' | 'file';
  make_primary: boolean;
  files: File[];
}

export interface DashboardFileUpdatePayload {
  is_primary?: boolean;
  meta?: Record<string, unknown>;
}

type FileEnvelope = FileApiItem | { data?: FileApiItem };
type FileListEnvelope = FileApiItem[] | { data?: FileApiItem[] };

type FileApiItem = Record<string, unknown> & {
  id?: number | string;
  name?: string;
  original_name?: string;
  url?: string;
  full_url?: string;
  type?: string | null;
  mime_type?: string | null;
  disk?: string | null;
  size?: number | string | null;
  is_primary?: boolean | number;
};

@Injectable({ providedIn: 'root' })
export class FilesApiService {
  private readonly http = inject(HttpClient);

  listDashboard(params: DashboardFileListParams): Observable<UploadedFileItem[]> {
    const query = new HttpParams()
      .set('fileable_type', params.fileable_type)
      .set('fileable_id', params.fileable_id);

    return this.http.get<FileListEnvelope>(API_ENDPOINTS.dashboardFiles, { params: query }).pipe(
      map((response) => this.unwrapList(response).map((item) => this.toUploadedFileItem(item)))
    );
  }

  showDashboard(id: number | string): Observable<UploadedFileItem> {
    return this.http
      .get<FileEnvelope>(`${API_ENDPOINTS.dashboardFiles}/${id}`)
      .pipe(map((response) => this.toUploadedFileItem(this.unwrapOne(response))));
  }

  uploadDashboard(payload: DashboardFileUploadPayload): Observable<UploadedFileItem[]> {
    const formData = new FormData();
    formData.set('fileable_type', payload.fileable_type);
    formData.set('fileable_id', String(payload.fileable_id));
    formData.set('type', payload.type);
    formData.set('make_primary', payload.make_primary ? '1' : '0');

    for (const file of payload.files) {
      formData.append('files[]', file, file.name);
    }

    return this.http
      .post<FileListEnvelope>(API_ENDPOINTS.dashboardFiles, formData)
      .pipe(map((response) => this.unwrapList(response).map((item) => this.toUploadedFileItem(item))));
  }

  updateDashboard(id: number | string, payload: DashboardFileUpdatePayload): Observable<UploadedFileItem> {
    return this.http
      .put<FileEnvelope>(`${API_ENDPOINTS.dashboardFiles}/${id}`, payload)
      .pipe(map((response) => this.toUploadedFileItem(this.unwrapOne(response))));
  }

  deleteDashboard(id: number | string): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.dashboardFiles}/${id}`);
  }

  restoreDashboard(id: number | string): Observable<void> {
    return this.http.post<void>(`${API_ENDPOINTS.dashboardFiles}/${id}/restore`, {});
  }

  deleteAdmin(id: number | string): Observable<void> {
    return this.http.delete<void>(`${API_ENDPOINTS.adminFiles}/${id}`);
  }

  restoreAdmin(id: number | string): Observable<void> {
    return this.http.post<void>(`${API_ENDPOINTS.adminFiles}/${id}/restore`, {});
  }

  private unwrapList(response: FileListEnvelope): FileApiItem[] {
    if (Array.isArray(response)) {
      return response;
    }

    if (Array.isArray(response.data)) {
      return response.data;
    }

    return [];
  }

  private unwrapOne(response: FileEnvelope): FileApiItem {
    if (
      'data' in response &&
      response.data &&
      typeof response.data === 'object' &&
      !Array.isArray(response.data)
    ) {
      return response.data as FileApiItem;
    }

    return response;
  }

  private toUploadedFileItem(api: FileApiItem): UploadedFileItem {
    const rawId = api.id;
    const parsedId =
      typeof rawId === 'number'
        ? rawId
        : typeof rawId === 'string' && rawId.trim() && !Number.isNaN(Number(rawId))
          ? Number(rawId)
          : rawId;

    const sizeRaw = api.size;
    const sizeBytes =
      typeof sizeRaw === 'number'
        ? sizeRaw
        : typeof sizeRaw === 'string' && sizeRaw.trim() && !Number.isNaN(Number(sizeRaw))
          ? Number(sizeRaw)
          : null;

    return {
      id: parsedId,
      name:
        typeof api.name === 'string'
          ? api.name
          : typeof api.original_name === 'string'
            ? api.original_name
            : 'file',
      url:
        typeof api.full_url === 'string'
          ? api.full_url
          : typeof api.url === 'string'
            ? api.url
            : null,
      previewUrl:
        typeof api.full_url === 'string'
          ? api.full_url
          : typeof api.url === 'string'
            ? api.url
            : null,
      type: typeof api.type === 'string' ? api.type : null,
      disk: typeof api.disk === 'string' ? api.disk : null,
      sizeBytes,
      isPrimary: Boolean(api.is_primary),
      mimeType: typeof api.mime_type === 'string' ? api.mime_type : null
    };
  }
}
