import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { AccessPermission, AccessRole, UserRolesPayload } from '../models/access-control.model';

type ListEnvelope<T> = T[] | { data?: T[] };

@Injectable({ providedIn: 'root' })
export class AccessControlApiService {
  private readonly http = inject(HttpClient);

  getRoles(): Observable<AccessRole[]> {
    return this.http.get<ListEnvelope<AccessRole>>(API_ENDPOINTS.dashboardRoles).pipe(
      map((response) => this.unwrapList(response))
    );
  }

  getPermissions(): Observable<AccessPermission[]> {
    return this.http
      .get<ListEnvelope<AccessPermission>>(API_ENDPOINTS.dashboardPermissions)
      .pipe(map((response) => this.unwrapList(response)));
  }

  updateUserRoles(userId: number, payload: UserRolesPayload): Observable<void> {
    return this.http.put<void>(`${API_ENDPOINTS.dashboardUsers}/${userId}/roles`, payload);
  }

  private unwrapList<T>(response: ListEnvelope<T>): T[] {
    if (Array.isArray(response)) {
      return response;
    }

    if (Array.isArray(response.data)) {
      return response.data;
    }

    return [];
  }
}
