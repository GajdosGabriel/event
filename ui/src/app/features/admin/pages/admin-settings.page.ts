import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize } from 'rxjs';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import {
  OrganizationsApiScope,
  OrganizationsApiService
} from '../services/organizations-api.service';
import {
  OrganizationItem,
  OrganizationUpsertPayload
} from '../models/organization.model';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { ToastService } from '../../../core/services/toast.service';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-admin-settings-page',
  imports: [IndexShellComponent, FormsModule],
  templateUrl: './admin-settings.page.html',
  styleUrl: './admin-settings.page.css'
})
export class AdminSettingsPage implements OnInit {
  private readonly organizationsApi = inject(OrganizationsApiService);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly scope = signal<OrganizationsApiScope>('admin');
  protected readonly organizations = signal<OrganizationItem[]>([]);
  protected readonly loading = signal(false);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal('');

  protected selectedOrganizationId: number | null = null;
  protected form: OrganizationUpsertPayload = {
    name: '',
    slug: '',
    body: '',
    website: '',
    email: '',
    phone: '',
    status: ''
  };

  ngOnInit(): void {
    this.pageMeta.setPageMeta({
      title: 'Admin nastavenia',
      description: 'Správa organizácií a systémových nastavení.'
    });

    this.loadOrganizations();
  }

  protected onScopeChange(nextScope: OrganizationsApiScope): void {
    this.scope.set(nextScope);
    this.resetForm();
    this.loadOrganizations();
  }

  protected loadOrganizations(): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.organizationsApi
      .list(this.scope())
      .pipe(finalize(() => this.loading.set(false)))
      .subscribe({
        next: (items) => {
          this.organizations.set(items);
        },
        error: (error) => {
          this.organizations.set([]);
          this.errorMessage.set(resolveApiErrorMessage(error, 'Nepodarilo sa načítať organizations.'));
        }
      });
  }

  protected editOrganization(item: OrganizationItem): void {
    this.selectedOrganizationId = item.id;
    this.form = {
      name: item.name,
      slug: item.slug ?? '',
      body: item.body ?? '',
      website: item.website ?? '',
      email: item.email ?? '',
      phone: item.phone ?? '',
      status: item.status ?? ''
    };
  }

  protected saveOrganization(): void {
    const payload = this.toPayload(this.form);
    if (!payload.name.trim()) {
      this.errorMessage.set('Názov organization je povinný.');
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set('');

    const request$ = this.selectedOrganizationId
      ? this.organizationsApi.update(this.scope(), this.selectedOrganizationId, payload)
      : this.organizationsApi.create(this.scope(), payload);

    request$.pipe(finalize(() => this.submitting.set(false))).subscribe({
      next: () => {
        this.toast.success(this.selectedOrganizationId ? 'Organization bola upravená.' : 'Organization bola vytvorená.');
        this.resetForm();
        this.loadOrganizations();
      },
      error: (error) => {
        this.errorMessage.set(resolveApiErrorMessage(error, 'Uloženie organization zlyhalo.'));
      }
    });
  }

  protected deleteOrganization(item: OrganizationItem): void {
    if (!window.confirm(`Naozaj chcete zmazať organization ${item.name}?`)) {
      return;
    }

    this.organizationsApi.delete(this.scope(), item.id).subscribe({
      next: () => {
        this.toast.info('Organization bola zmazaná.');
        this.loadOrganizations();
      },
      error: (error) => {
        this.errorMessage.set(resolveApiErrorMessage(error, 'Mazanie organization zlyhalo.'));
      }
    });
  }

  protected restoreOrganization(item: OrganizationItem): void {
    this.organizationsApi.restore(this.scope(), item.id).subscribe({
      next: () => {
        this.toast.success('Organization bola obnovená.');
        this.loadOrganizations();
      },
      error: (error) => {
        this.errorMessage.set(resolveApiErrorMessage(error, 'Obnovenie organization zlyhalo.'));
      }
    });
  }

  protected resetForm(): void {
    this.selectedOrganizationId = null;
    this.form = {
      name: '',
      slug: '',
      body: '',
      website: '',
      email: '',
      phone: '',
      status: ''
    };
  }

  private toPayload(source: OrganizationUpsertPayload): OrganizationUpsertPayload {
    return {
      name: source.name.trim(),
      slug: source.slug?.trim() || null,
      body: source.body?.trim() || null,
      website: source.website?.trim() || null,
      email: source.email?.trim() || null,
      phone: source.phone?.trim() || null,
      status: source.status?.trim() || null
    };
  }
}
