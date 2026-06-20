import { Component, computed } from '@angular/core';
import { DropdownItem } from '../../../shared/components/dropdown/dropdown.component';
import { LayoutLink, LayoutShellComponent } from '../layout-shell/layout-shell.component';

@Component({
  selector: 'app-admin-layout',
  imports: [LayoutShellComponent],
  templateUrl: './admin-layout.component.html',
  styleUrl: './admin-layout.component.css'
})
export class AdminLayoutComponent {
  readonly userMenuItems = computed<DropdownItem[]>(() => {
    const items: DropdownItem[] = [];
    items.push({ id: 'home', label: 'Domov', path: '/' });
    items.push({ id: 'dashboard', label: 'Dashboard', path: '/dashboard' });
    items.push({ id: 'logout', label: 'Odhlasit sa' });
    return items;
  });

  readonly brandText = 'Event';
  readonly brandSubtitle = 'Admin';
  readonly brandMark = 'A';
  readonly brandLink = '/admin';
  readonly footerText = 'Admin footer - event-ui';

  readonly headerLinks: LayoutLink[] = [{ label: 'Public', path: '/' }];

  readonly asideLinks: LayoutLink[] = [
    { label: 'Events', path: '/admin/events' },
    { label: 'Canals', path: '/admin/canals' },
    { label: 'Adresy', path: '/admin/venues' },
    { label: 'Roly', path: '/admin/users' },
    { label: 'Organizations', path: '/admin/settings' },
    { label: 'Files', path: '/admin/files' }
  ];
}
