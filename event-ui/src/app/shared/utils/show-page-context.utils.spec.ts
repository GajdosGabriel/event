import { describe, expect, it } from 'vitest';
import { resolveShowPageContext } from './show-page-context.utils';

describe('show-page-context utils', () => {
  it('should resolve admin context', () => {
    const result = resolveShowPageContext('/admin/events/42', {
      entityPath: 'events',
      backLinkText: 'Späť na podujatia'
    });

    expect(result).toEqual({
      backLink: '/admin/events',
      backLinkText: 'Späť na podujatia',
      editBaseLink: '/admin/events',
      showEditAction: true
    });
  });

  it('should resolve dashboard context', () => {
    const result = resolveShowPageContext('/dashboard/venues/9', {
      entityPath: 'venues',
      backLinkText: 'Späť na miesta'
    });

    expect(result).toEqual({
      backLink: '/dashboard/venues',
      backLinkText: 'Späť na miesta',
      editBaseLink: '/dashboard/venues',
      showEditAction: true
    });
  });

  it('should resolve public fallback context', () => {
    const result = resolveShowPageContext('/events/42', {
      entityPath: 'events',
      backLinkText: 'Späť na podujatia'
    });

    expect(result).toEqual({
      backLink: '/',
      backLinkText: 'Späť na úvod',
      editBaseLink: '',
      showEditAction: false
    });
  });

  it('should respect custom public fallback options', () => {
    const result = resolveShowPageContext('/canals/7', {
      entityPath: 'canals',
      backLinkText: 'Späť na kanály',
      publicBackLink: '/dashboard/canals',
      publicBackLinkText: 'Späť na kanály',
      publicShowsEditAction: true
    });

    expect(result).toEqual({
      backLink: '/dashboard/canals',
      backLinkText: 'Späť na kanály',
      editBaseLink: '',
      showEditAction: true
    });
  });
});
