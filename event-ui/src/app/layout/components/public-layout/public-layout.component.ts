import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';
import { DropdownItem } from '../../../shared/components/dropdown/dropdown.component';
import { LayoutLink, LayoutShellComponent } from '../layout-shell/layout-shell.component';

@Component({
  selector: 'app-public-layout',
  imports: [LayoutShellComponent],
  templateUrl: './public-layout.component.html',
  styleUrl: './public-layout.component.css'
})
export class PublicLayoutComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly currentIdentity = toSignal(this.auth.currentIdentity$, { initialValue: null });

  private readonly currentPath = toSignal(
    this.router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => this.router.url.split('?')[0])
    ),
    { initialValue: this.router.url.split('?')[0] }
  );

  readonly userMenuItems = computed<DropdownItem[]>(() => {
    const identity = this.currentIdentity();
    const isSuperAdmin = identity?.roles?.includes('super-admin') ?? false;
    const items: DropdownItem[] = [
      { id: 'dashboard', label: 'Dashboard', path: '/dashboard' },
    ];
    if (isSuperAdmin) {
      items.push({ id: 'admin', label: 'Admin', path: '/admin' });
    }
    items.push({ id: 'logout', label: 'Odhlasit sa' });
    return items;
  });

  readonly brandText = 'event-ui';
  readonly brandLink = '/';
  readonly footerText = '© 2026 event-ui';
  readonly asideLinks: LayoutLink[] = [];

  private get isAuthenticated(): boolean {
    return this.currentIdentity() !== null || this.auth.isAuthenticated();
  }

  protected get showLogout(): boolean {
    return this.isAuthenticated;
  }

  protected get headerLinks(): LayoutLink[] {
    if (this.isAuthenticated) {
      return [
        // { label: 'Home', path: '/', exact: true },
        // { label: 'Dashboard', path: '/dashboard' }
      ];
    }

    return [
      { label: 'Home', path: '/', exact: true },
      { label: 'Login', path: '/login' },
      { label: 'Register', path: '/register' },
      // { label: 'Dashboard', path: '/dashboard' }
    ];
  }
}
