import { AsyncPipe, NgFor, NgIf } from '@angular/common';
import { Component, Input, afterNextRender, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { catchError, filter, map, of, switchMap, take } from 'rxjs';
import { AuthUiService } from '../../../core/services/auth-ui.service';
import { AuthService } from '../../../core/services/auth.service';
import { CanalsApiService } from '../../../features/canals/services/canals-api.service';
import { DropdownComponent, DropdownItem } from '../../../shared/components/dropdown/dropdown.component';

export interface LayoutLink {
  label: string;
  path: string;
  exact?: boolean;
}

@Component({
  selector: 'app-layout-shell',
  imports: [NgFor, NgIf, AsyncPipe, RouterOutlet, RouterLink, DropdownComponent],
  templateUrl: './layout-shell.component.html',
  styleUrl: './layout-shell.component.css'
})
export class LayoutShellComponent {
  private readonly authUi = inject(AuthUiService);
  private readonly auth = inject(AuthService);
  private readonly canalsApi = inject(CanalsApiService);
  private readonly router = inject(Router);

  private readonly currentPath = toSignal(
    this.router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => this.normalizePath(this.router.url))
    ),
    { initialValue: this.normalizePath(this.router.url) }
  );

  @Input({ required: true }) brandText = '';
  @Input() brandSubtitle = '';
  @Input() brandMark = '';
  @Input() headerLinks: LayoutLink[] = [];
  @Input() asideLinks: LayoutLink[] = [];
  @Input() footerText = '';
  @Input() layoutClass = '';
  @Input() showLogout = false;
  @Input() hideAsideWhenEmpty = false;
  @Input() brandLink = '/';

  readonly isLoggingOut$ = this.authUi.isLoggingOut$;
  @Input() userMenuItems: DropdownItem[] = [];

  private readonly currentIdentity = toSignal(this.auth.currentIdentity$, { initialValue: null });
  private readonly currentCanalName = toSignal(
    this.auth.currentIdentity$.pipe(
      switchMap((identity) => {
        const canalId = identity?.canal_id;
        if (canalId === null || canalId === undefined) {
          return of(null);
        }

        return this.canalsApi.show(canalId).pipe(
          map((canal) => canal.canal || canal.name || null),
          catchError(() => of(null))
        );
      })
    ),
    { initialValue: null }
  );

  constructor() {
    afterNextRender(() => {
      if (this.currentIdentity() === null) {
        this.auth
          .fetchCurrentIdentity()
          .pipe(
            take(1),
            catchError(() => of(null))
          )
          .subscribe();
      }
    });
  }

  protected get userMenuLabel(): string {
    const identity = this.currentIdentity();
    if (!identity) {
      return 'Ucet';
    }

    const canalFromIdentity = identity.canal_context?.active?.name || identity.canal || null;
    return canalFromIdentity || this.currentCanalName() || 'Ucet';
  }

  logout(): void {
    this.authUi.logoutAndNavigate('/login').subscribe();
  }

  protected onUserMenuSelect(item: DropdownItem): void {
    if (item.id === 'logout') {
      this.logout();
    }
  }

  protected isAsideLinkActive(link: LayoutLink): boolean {
    const currentPath = this.currentPath();
    if (link.exact) {
      return currentPath === link.path;
    }

    return currentPath === link.path || currentPath.startsWith(`${link.path}/`);
  }

  protected isHeaderLinkActive(link: LayoutLink): boolean {
    const currentPath = this.currentPath();
    if (link.path === '/') {
      return currentPath === '/';
    }

    if (link.exact) {
      return currentPath === link.path;
    }

    return currentPath === link.path || currentPath.startsWith(`${link.path}/`);
  }

  protected onAsideLinkClick(event: MouseEvent, link: LayoutLink): void {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();

    const navigationExtras = this.isAsideLinkActive(link)
      ? {
          queryParamsHandling: 'merge' as const,
          queryParams: { asideNav: Date.now() }
        }
      : undefined;

    void this.router.navigate([link.path], navigationExtras);
  }

  protected get showAside(): boolean {
    return this.asideLinks.length > 0 || !this.hideAsideWhenEmpty;
  }

  protected get asideBrandMark(): string {
    if (this.brandMark) {
      return this.brandMark;
    }

    return this.brandText
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((word) => word[0])
      .join('')
      .toUpperCase();
  }

  private normalizePath(url: string): string {
    return url.split('?')[0].split('#')[0];
  }
}
