import { HttpParams } from '@angular/common/http';
import { FilterState } from '../models/filter-params.model';

export function filterStateToHttpParams(filterState: FilterState): HttpParams {
  let params = new HttpParams();

  if (filterState.published) {
    params = params.set('published', 'true');
  }

  if (filterState.unpublished) {
    params = params.set('unpublished', 'true');
  }

  if (filterState.blocked) {
    params = params.set('blocked', 'true');
  }

  if (filterState.status) {
    params = params.set('status', filterState.status);
  }

  if (filterState.deleted) {
    params = params.set('deleted', 'true');
  }

  params = params.set('per_page', filterState.per_page.toString());

  return params;
}
