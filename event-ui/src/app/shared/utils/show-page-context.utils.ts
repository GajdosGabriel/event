export interface ShowPageContext {
  backLink: string;
  backLinkText: string;
  editBaseLink: string;
  showEditAction: boolean;
}

export interface ShowPageContextOptions {
  entityPath: string;
  backLinkText: string;
  publicBackLink?: string;
  publicBackLinkText?: string;
  publicShowsEditAction?: boolean;
}

export function resolveShowPageContext(
  url: string,
  options: ShowPageContextOptions
): ShowPageContext {
  const {
    entityPath,
    backLinkText,
    publicBackLink = '/',
    publicBackLinkText = 'Späť na úvod',
    publicShowsEditAction = false
  } = options;

  if (url.startsWith('/admin/')) {
    return {
      backLink: `/admin/${entityPath}`,
      backLinkText,
      editBaseLink: `/admin/${entityPath}`,
      showEditAction: true
    };
  }

  if (url.startsWith('/dashboard/')) {
    return {
      backLink: `/dashboard/${entityPath}`,
      backLinkText,
      editBaseLink: `/dashboard/${entityPath}`,
      showEditAction: true
    };
  }

  return {
    backLink: publicBackLink,
    backLinkText: publicBackLinkText,
    editBaseLink: '',
    showEditAction: publicShowsEditAction
  };
}
