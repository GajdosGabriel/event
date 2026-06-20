import { Component, DestroyRef, OnInit, computed, effect, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, finalize, forkJoin, map, of } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { EntitySelectComponent } from '../../../shared/components/entity-select/entity-select.component';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import {
  LookupMunicipalityApiService,
  LookupOption
} from '../../../shared/services/lookup-municipality-api.service';
import { CanalIdentityMode, sanitizeCanalIdentityMode } from '../models/canal-identity-mode';
import { MODEL_STATUS, AllowedStatusOption, MODEL_STATUS_OPTIONS, ModelStatus } from '../../../shared/models/model-status';
import {
  CanalFileUploadOptions,
  CanalListScope,
  CanalUpsertPayload,
  CanalsApiService
} from '../services/canals-api.service';
import {
  FilesUploadSelection,
  UploadedFilesListComponent
} from '../../../shared/components/uploaded-files-list/uploaded-files-list.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { ToastService } from '../../../core/services/toast.service';
import {
  applyServerValidationErrors,
  clearServerValidationErrors,
  ServerFieldMap
} from '../../../shared/utils/form-server-errors.utils';
import { PageMetaService } from '../../../shared/services/page-meta.service';

const IDENTITY_MODE_OPTIONS: LookupOption[] = [
  { id: 1, name: 'Osobný' },
  { id: 2, name: 'Firemný' },
  { id: 3, name: 'Krycie meno' }
];

const IDENTITY_MODE_TO_ID: Record<CanalIdentityMode, number> = {
  personal: 1,
  organization: 2,
  pseudonymous: 3
};

const IDENTITY_ID_TO_MODE: Record<number, CanalIdentityMode> = {
  1: 'personal',
  2: 'organization',
  3: 'pseudonymous'
};

type CanalEditForm = {
  municipality_id: FormControl<number | null>;
  venue_id: FormControl<number | null>;
  identity_mode: FormControl<number | null>;
  name: FormControl<string>;
  slug: FormControl<string>;
  title_prefix: FormControl<string>;
  title_suffix: FormControl<string>;
  email: FormControl<string>;
  body: FormControl<string>;
  status: FormControl<ModelStatus>;
  website: FormControl<string>;
};

const CANAL_FIELD_MAP: ServerFieldMap<keyof CanalEditForm> = {
  municipality_id: ['municipality_id'],
  village_id: ['municipality_id'],
  venue_id: ['venue_id'],
  identity_mode: ['identity_mode'],
  name: ['name'],
  slug: ['slug'],
  title_prefix: ['title_prefix'],
  title_suffix: ['title_suffix'],
  email: ['email'],
  body: ['body'],
  status: ['status'],
  website: ['website']
};

@Component({
  selector: 'app-canal-edit-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    EntitySelectComponent,
    PageHeadComponent,
    ErrorBannerComponent,
    UploadedFilesListComponent
  ],
  templateUrl: './canal-edit.page.html',
  styleUrl: './canal-edit.page.css'
})
export class CanalEditPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly canalsApi = inject(CanalsApiService);
  private readonly lookupApi = inject(LookupMunicipalityApiService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly isEditMode = signal(false);
  protected readonly canUploadFiles = true;
  protected readonly errorMessage = signal('');
  protected readonly submitErrors = signal<string[]>([]);
  protected readonly filesValidationError = signal('');
  protected readonly canalId = signal<number | null>(null);
  protected readonly selectedFiles = signal<File[]>([]);
  protected readonly existingUploadedFiles = signal<UploadedFileItem[]>([]);
  protected readonly uploadedFilesResetKey = signal(0);
  protected readonly uploadOptions = signal<CanalFileUploadOptions>({
    file_disk: 'public',
    make_primary_file: false
  });
  protected readonly selectedFilePreviews = computed<UploadedFileItem[]>(() =>
    this.selectedFiles().map((file) => ({
      name: file.name,
      sizeBytes: file.size,
      mimeType: file.type,
      type: file.type.startsWith('image/') ? 'image' : 'file'
    }))
  );

  protected readonly displayFiles = computed<UploadedFileItem[]>(() => [
    ...this.existingUploadedFiles(),
    ...this.selectedFilePreviews()
  ]);

  protected readonly municipalityOptions = signal<LookupOption[]>([]);
  protected readonly venueOptions = signal<LookupOption[]>([]);
  protected readonly identityModeOptions = IDENTITY_MODE_OPTIONS;
  protected readonly statusOptions = signal<AllowedStatusOption[]>(MODEL_STATUS_OPTIONS);

  protected get routePrefix(): '/dashboard' | '/admin' {
    return this.router.url.startsWith('/admin') ? '/admin' : '/dashboard';
  }

  protected get canalsScope(): CanalListScope {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  protected readonly form = new FormGroup<CanalEditForm>({
    municipality_id: new FormControl<number | null>(null, { validators: [Validators.required] }),
    venue_id: new FormControl<number | null>(null),
    identity_mode: new FormControl<number | null>(1, { validators: [Validators.required] }),
    name: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    slug: new FormControl('', { nonNullable: true }),
    title_prefix: new FormControl('', { nonNullable: true }),
    title_suffix: new FormControl('', { nonNullable: true }),
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
    body: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    status: new FormControl(MODEL_STATUS.Draft, { nonNullable: true }),
    website: new FormControl('', { nonNullable: true })
  });

  ngOnInit(): void {
    this.loadLookupOptions();

    this.route.paramMap
      .pipe(
        map((params) => {
          const idParam = params.get('id');
          if (!idParam) {
            return null;
          }

          const id = Number(idParam);
          return Number.isFinite(id) && id > 0 ? id : null;
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((id) => {
        this.errorMessage.set('');
        this.canalId.set(id);
        this.isEditMode.set(id !== null);
        this.resetForm();

        if (id === null) {
          this.loading.set(false);
          this.loadCreateStatusOptions();
          return;
        }

        this.loadCanal(id);
      });
  }

  private readonly syncPageMetaEffect = effect(() => {
    this.pageMeta.setPageMeta({
      title: this.isEditMode() ? 'Úprava kanálu' : 'Nový kanál',
      description: this.isEditMode()
        ? 'Formulár na úpravu kanála.'
        : 'Formulár na vytvorenie nového kanála.'
    });
  });

  protected onSubmit(): void {
    clearServerValidationErrors(this.form);
    this.submitErrors.set([]);

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMessage.set('');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      this.errorMessage.set('Skontroluj prosim povinne polia formulara.');
      return;
    }

    if (this.filesValidationError()) {
      this.errorMessage.set(this.filesValidationError());
      return;
    }

    this.errorMessage.set('');
    this.saving.set(true);

    const id = this.canalId();
    const files = this.selectedFiles();
    const options = this.uploadOptions();
    const request$ =
      this.isEditMode() && id !== null
        ? files.length > 0
          ? this.canalsApi.updateWithFiles(id, payload, files, options, this.canalsScope)
          : this.canalsApi.update(id, payload, this.canalsScope)
        : files.length > 0
          ? this.canalsApi.createWithFiles(payload, files, options, this.canalsScope)
          : this.canalsApi.create(payload, this.canalsScope);

    request$
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (canal) => {
          void this.router.navigate([this.routePrefix, 'canals', canal.id]);
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  protected isFieldInvalid(controlName: keyof CanalEditForm): boolean {
    const control = this.form.controls[controlName];
    return control.invalid && (control.touched || control.dirty);
  }

  protected getFieldError(controlName: keyof CanalEditForm): string {
    const errors = this.form.controls[controlName].errors;
    if (!errors) {
      return '';
    }

    if (typeof errors['server'] === 'string') {
      return errors['server'];
    }

    if (errors['required']) {
      return 'Toto pole je povinne.';
    }

    if (errors['email']) {
      return 'Zadaj platny email.';
    }

    return 'Neplatna hodnota.';
  }

  protected onFilesSelectionChange(selection: FilesUploadSelection): void {
    const fileType = this.toCanalUploadType(selection.fileType) ?? 'image';

    this.selectedFiles.set(selection.files);
    this.uploadOptions.set({
      file_type: fileType,
      file_disk: selection.fileDisk.trim() || undefined,
      make_primary_file: selection.makePrimaryFile
    });
  }

  protected onSavePendingFiles(): void {
    if (this.filesValidationError()) {
      this.errorMessage.set(this.filesValidationError());
      return;
    }

    const files = this.selectedFiles();
    if (files.length === 0) {
      return;
    }

    if (!this.isEditMode()) {
      this.errorMessage.set('Súbory sa uložia až po vytvorení kanála. Najprv ulož formulár.');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      this.form.markAllAsTouched();
      this.errorMessage.set('Dopln povinne polia formulara pred ulozenim suborov.');
      return;
    }

    const id = this.canalId();
    if (id === null) {
      return;
    }

    this.errorMessage.set('');
    this.saving.set(true);

    this.canalsApi
      .updateWithFiles(id, payload, files, this.uploadOptions(), this.canalsScope)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (canal) => {
          this.existingUploadedFiles.set(canal.uploadedFiles ?? []);
          this.selectedFiles.set([]);
          this.uploadedFilesResetKey.update((value) => value + 1);
          this.toast.success('Súbory boli uložené.');
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  protected onFilesValidationError(message: string | null): void {
    this.filesValidationError.set(message ?? '');
  }

  protected onRemoveUploadedFile(file: UploadedFileItem): void {
    this.existingUploadedFiles.set(
      this.existingUploadedFiles().filter((item) => item.id !== file.id)
    );
  }

  protected onPrimaryUploadedFile(file: UploadedFileItem): void {
    this.existingUploadedFiles.set(
      this.existingUploadedFiles().map((item) =>
        item.id === file.id
          ? { ...item, ...file, isPrimary: true }
          : { ...item, isPrimary: false }
      )
    );
  }

  private loadCreateStatusOptions(): void {
    this.canalsApi
      .index(1, 1, undefined, this.canalsScope)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((result) => {
        if (result.allowedStatuses.length) {
          this.statusOptions.set(result.allowedStatuses);
        }
      });
  }

  private loadLookupOptions(): void {
    forkJoin({
      municipalities: this.lookupApi.listMunicipalities().pipe(catchError(() => of([]))),
      venues: this.lookupApi
        .list(this.canalsScope === 'admin' ? API_ENDPOINTS.adminVenues : API_ENDPOINTS.venues)
        .pipe(catchError(() => of([])))
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ municipalities, venues }) => {
        this.municipalityOptions.set(municipalities);
        this.venueOptions.set(venues);
      });
  }

  private loadCanal(id: number): void {
    this.loading.set(true);

    this.canalsApi
      .show(id, this.canalsScope)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (canal) => {
          this.existingUploadedFiles.set(canal.uploadedFiles ?? []);
          if (canal.allowedStatuses.length) {
            this.statusOptions.set(canal.allowedStatuses);
          }
          this.form.setValue({
            municipality_id: canal.municipalityId ?? null,
            venue_id: canal.venueId ?? null,
            identity_mode: IDENTITY_MODE_TO_ID[sanitizeCanalIdentityMode(canal.identityMode)],
            name: canal.name,
            slug: canal.slug ?? '',
            title_prefix: canal.titlePrefix ?? '',
            title_suffix: canal.titleSuffix ?? '',
            email: canal.email,
            body: canal.body,
            status: canal.status,
            website: canal.website ?? ''
          });
        },
        error: () => {
          this.errorMessage.set('Nepodarilo sa nacitat canal na upravu.');
        }
      });
  }

  private resetForm(): void {
    this.form.reset({
      municipality_id: null,
      venue_id: null,
      identity_mode: 1,
      name: '',
      slug: '',
      title_prefix: '',
      title_suffix: '',
      email: '',
      body: '',
      status: MODEL_STATUS.Draft,
      website: ''
    });

    clearServerValidationErrors(this.form);
    this.submitErrors.set([]);

    this.filesValidationError.set('');
    this.selectedFiles.set([]);
    this.existingUploadedFiles.set([]);
    this.uploadedFilesResetKey.update((value) => value + 1);
    this.uploadOptions.set({
      file_disk: 'public',
      make_primary_file: false
    });
  }

  private toCanalUploadType(value: string | null): CanalFileUploadOptions['file_type'] | undefined {
    if (value === 'image' || value === 'card' || value === 'file') {
      return value;
    }

    return undefined;
  }

  private buildPayload(): CanalUpsertPayload | null {
    const value = this.form.getRawValue();

    if (value.municipality_id === null) {
      return null;
    }

    const name = value.name.trim();
    const email = value.email.trim();
    const body = value.body.trim();

    if (!name || !email || !body) {
      return null;
    }

    const slug = value.slug.trim();
    const titlePrefix = value.title_prefix.trim();
    const titleSuffix = value.title_suffix.trim();
    const website = value.website.trim();
    const identityMode: CanalIdentityMode = IDENTITY_ID_TO_MODE[value.identity_mode ?? 0] ?? 'personal';

    return {
      municipality_id: value.municipality_id,
      venue_id: value.venue_id,
      identity_mode: identityMode,
      name,
      slug: slug || undefined,
      title_prefix: titlePrefix || undefined,
      title_suffix: titleSuffix || undefined,
      email,
      body,
      status: value.status,
      website: website || null
    };
  }

  private applySubmitError(error: unknown): void {
    const fallback = 'Uloženie kanálu zlyhalo. Skús to znova.';
    const payload = (error as HttpErrorResponse | null)?.error;
    const result = applyServerValidationErrors({
      form: this.form,
      payload,
      fieldMap: CANAL_FIELD_MAP,
      fallbackMessage: fallback
    });

    this.submitErrors.set(result.summary);
    this.errorMessage.set(result.summary[0] ?? fallback);

    if (result.mappedAny) {
      this.form.markAllAsTouched();
    }
  }
}
