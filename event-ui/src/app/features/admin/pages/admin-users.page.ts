import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize, forkJoin } from 'rxjs';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { AccessControlApiService } from '../services/access-control-api.service';
import { AccessPermission, AccessRole } from '../models/access-control.model';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { ToastService } from '../../../core/services/toast.service';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-admin-users-page',
  imports: [IndexShellComponent, FormsModule],
  templateUrl: './admin-users.page.html',
  styleUrl: './admin-users.page.css'
})
export class AdminUsersPage implements OnInit {
  private readonly accessControlApi = inject(AccessControlApiService);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly loading = signal(false);
  protected readonly assigning = signal(false);
  protected readonly errorMessage = signal('');
  protected readonly roles = signal<AccessRole[]>([]);
  protected readonly permissions = signal<AccessPermission[]>([]);

  protected userId: number | null = null;
  protected selectedRole = '';

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: 'Admin používatelia',
      description: 'Správa rolí a oprávnení používateľov.'
    });

    this.reloadAccessControlData();
  }

  protected reloadAccessControlData(): void {
    this.loading.set(true);
    this.errorMessage.set('');

    forkJoin({
      roles: this.accessControlApi.getRoles(),
      permissions: this.accessControlApi.getPermissions()
    })
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: ({ roles, permissions }) => {
          this.roles.set(roles);
          this.permissions.set(permissions);
          if (!this.selectedRole && roles.length > 0) {
            this.selectedRole = roles[0]?.name ?? '';
          }
        },
        error: (error) => {
          this.roles.set([]);
          this.permissions.set([]);
          this.errorMessage.set(
            resolveApiErrorMessage(error, 'Nepodarilo sa načítať roles a permissions.')
          );
        }
      });
  }

  protected assignRole(): void {
    if (!this.userId || this.userId <= 0) {
      this.errorMessage.set('Zadajte validné ID používateľa.');
      return;
    }

    if (!this.selectedRole.trim()) {
      this.errorMessage.set('Vyberte rolu, ktorú chcete priradiť.');
      return;
    }

    this.assigning.set(true);
    this.errorMessage.set('');

    this.accessControlApi
      .updateUserRoles(this.userId, { roles: [this.selectedRole] })
      .pipe(finalize(() => this.assigning.set(false)))
      .subscribe({
        next: () => {
          this.toast.success('Rola bola úspešne priradená.');
        },
        error: (error) => {
          this.errorMessage.set(resolveApiErrorMessage(error, 'Priradenie roly zlyhalo.'));
        }
      });
  }
}
