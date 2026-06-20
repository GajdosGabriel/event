export interface FilterParams {
  published?: boolean | string;
  unpublished?: boolean | string;
  blocked?: boolean | string;
  status?: string;
  deleted?: boolean | string;
  search?: string;
  per_page?: number | string;
  municipality?: number | string;
}

export interface FilterState {
  published: boolean;
  unpublished: boolean;
  blocked: boolean;
  status: string | null;
  deleted: boolean;
  search: string;
  per_page: number;
}

export const DEFAULT_FILTER_PER_PAGE = 20;

export const DEFAULT_FILTER_STATE: FilterState = {
  published: false,
  unpublished: false,
  blocked: false,
  status: null,
  deleted: false,
  search: '',
  per_page: DEFAULT_FILTER_PER_PAGE
};

export function createDefaultFilterState(perPage: number = DEFAULT_FILTER_PER_PAGE): FilterState {
  return {
    ...DEFAULT_FILTER_STATE,
    per_page: toPerPage(perPage, DEFAULT_FILTER_PER_PAGE)
  };
}

export function createPerPageOptions(defaultPerPage: number): number[] {
  return Array.from(new Set([10, 20, 50, 100, toPerPage(defaultPerPage, DEFAULT_FILTER_PER_PAGE)])).sort(
    (left, right) => left - right
  );
}

function toSearch(value: unknown): string {
  if (typeof value !== 'string') {
    return '';
  }

  return value.trim();
}

function toBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    return normalized === 'true' || normalized === '1' || normalized === 'yes';
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  return false;
}

function toPerPage(value: unknown, fallback: number = DEFAULT_FILTER_PER_PAGE): number {
  const parsed = Number.parseInt(String(value ?? ''), 10);

  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }

  return parsed;
}

export function filterParamsToState(
  params: FilterParams,
  defaultPerPage: number = DEFAULT_FILTER_PER_PAGE
): Partial<FilterState> {
  return {
    published: toBoolean(params.published),
    unpublished: toBoolean(params.unpublished),
    blocked: toBoolean(params.blocked),
    status: params.status || null,
    deleted: toBoolean(params.deleted),
    search: toSearch(params.search),
    per_page: toPerPage(params.per_page, defaultPerPage)
  };
}

export function filterStateToParams(
  state: FilterState,
  defaultPerPage: number = DEFAULT_FILTER_PER_PAGE
): FilterParams {
  const params: FilterParams = {};

  if (state.published) params.published = true;
  if (state.unpublished) params.unpublished = true;
  if (state.blocked) params.blocked = true;
  if (state.status) params.status = state.status;
  if (state.deleted) params.deleted = true;
  if (state.search) params.search = state.search;
  if (state.per_page !== defaultPerPage) params.per_page = state.per_page;

  return params;
}
