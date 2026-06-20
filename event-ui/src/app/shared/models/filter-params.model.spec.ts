import { describe, expect, it } from 'vitest';
import {
  createDefaultFilterState,
  createPerPageOptions,
  DEFAULT_FILTER_STATE,
  filterParamsToState,
  filterStateToParams
} from './filter-params.model';

describe('filter-params.model', () => {
  it('maps search query param into filter state', () => {
    expect(filterParamsToState({ search: '  letny festival  ' })).toEqual(
      expect.objectContaining({ search: 'letny festival' })
    );
  });

  it('omits empty search from params', () => {
    expect(
      filterStateToParams({
        ...DEFAULT_FILTER_STATE,
        search: ''
      })
    ).not.toHaveProperty('search');
  });

  it('includes search in params when set', () => {
    expect(
      filterStateToParams({
        ...DEFAULT_FILTER_STATE,
        search: 'letny festival'
      })
    ).toEqual(
      expect.objectContaining({
        search: 'letny festival'
      })
    );
  });

  it('uses provided default per-page when query param is missing', () => {
    expect(filterParamsToState({}, 15)).toEqual(
      expect.objectContaining({ per_page: 15 })
    );
  });

  it('omits per-page from params when it matches page default', () => {
    expect(
      filterStateToParams(
        {
          ...createDefaultFilterState(15),
          per_page: 15
        },
        15
      )
    ).not.toHaveProperty('per_page');
  });

  it('includes page default in selectable options', () => {
    expect(createPerPageOptions(15)).toContain(15);
  });
});
