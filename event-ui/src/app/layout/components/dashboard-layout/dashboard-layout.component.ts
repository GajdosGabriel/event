import { Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';
import { DropdownItem } from '../../../shared/components/dropdown/dropdown.component';
import { LayoutLink, LayoutShellComponent } from '../layout-shell/layout-shell.component';

@Component({
  selector: 'app-dashboard-layout',
  imports: [LayoutShellComponent],
  templateUrl: './dashboard-layout.component.html',
  styleUrl: './dashboard-layout.component.css'
})
export class DashboardLayoutComponent {
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
    const path = this.currentPath();
    const items: DropdownItem[] = [];
    items.push({ id: 'home', label: 'Domov', path: '/' });
    if (isSuperAdmin && !path.startsWith('/admin')) {
      items.push({ id: 'admin', label: 'Admin', path: '/admin' });
    }
    items.push({ id: 'logout', label: 'Odhlasit sa' });
    return items;
  });

  readonly brandText = 'User Dashboard';
  readonly brandSubtitle = 'Dashboard';
  readonly brandMark = 'D';
  readonly brandLink = '/dashboard';
  readonly footerText = 'Dashboard footer - event-ui';

  readonly headerLinks: LayoutLink[] = [{ label: 'Public', path: '/' }];

  private readonly baseAsideLinks: LayoutLink[] = [
    { label: 'Kanály', path: '/dashboard/canals' },
    { label: 'Akcie', path: '/dashboard/events' },
    { label: 'Adresy', path: '/dashboard/venues' }
  ];

  get asideLinks(): LayoutLink[] {
    const links = [...this.baseAsideLinks];
    if (this.currentIdentity()?.roles?.includes('super-admin')) {
      links.push({ label: 'Admin', path: '/admin' });
    }

    return links;
  }
}
