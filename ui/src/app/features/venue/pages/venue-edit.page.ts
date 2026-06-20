import { Component, DestroyRef, OnInit, computed, effect, inject, signal, AfterViewInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { catchError, finalize, map, of } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { EntitySelectComponent } from '../../../shared/components/entity-select/entity-select.component';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import {
  LookupMunicipalityApiService,
  LookupOption
} from '../../../shared/services/lookup-municipality-api.service';
import { CanalsApiService } from '../../canals/services/canals-api.service';
import { CanalItem } from '../../canals/models/canal.model';
import {
  VenueDetectPayload,
  VenueDetectResponse,
  VenueFileUploadOptions,
  VenueListScope,
  VenueUpsertPayload,
  VenuesApiService
} from '../services/venues-api.service';
import {
  FilesUploadSelection,
  UploadedFilesListComponent
} from '../../../shared/components/uploaded-files-list/uploaded-files-list.component';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { ToastService } from '../../../core/services/toast.service';
import { AuthService } from '../../../core/services/auth.service';
import {
  applyServerValidationErrors,
  clearServerValidationErrors,
  ServerFieldMap
} from '../../../shared/utils/form-server-errors.utils';
import { LocationMapComponent } from '../../../shared/components/location-map/location-map.component';
import { extractUploadedFiles } from '../../../shared/utils/uploaded-files.utils';
import { MODEL_STATUS, AllowedStatusOption, MODEL_STATUS_OPTIONS, ModelStatus, sanitizeModelStatus } from '../../../shared/models/model-status';
import { PageMetaService } from '../../../shared/services/page-meta.service';

type VenueEditForm = {
  canal_id: FormControl<number | null>;
  village_id: FormControl<number | null>;
  name: FormControl<string>;
  street: FormControl<string>;
  postcode: FormControl<string>;
  slug: FormControl<string>;
  body: FormControl<string>;
  website: FormControl<string>;
  email: FormControl<string>;
  phone: FormControl<string>;
  country: FormControl<string>;
  latitude: FormControl<string>;
  longitude: FormControl<string>;
  capacity: FormControl<string>;
  opening_hours: FormControl<string>;
  category: FormControl<string>;
  status: FormControl<ModelStatus>;
};

const VENUE_FIELD_MAP: ServerFieldMap<keyof VenueEditForm> = {
  canal_id: ['canal_id'],
  village_id: ['village_id'],
  municipality_id: ['village_id'],
  name: ['name'],
  street: ['street'],
  postcode: ['postcode'],
  slug: ['slug'],
  body: ['body'],
  website: ['website'],
  email: ['email'],
  phone: ['phone'],
  country: ['country'],
  latitude: ['latitude'],
  longitude: ['longitude'],
  capacity: ['capacity'],
  opening_hours: ['opening_hours'],
  openinghours: ['opening_hours'],
  category: ['category'],
  status: ['status']
};

@Component({
  selector: 'app-venue-edit-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    EntitySelectComponent,
    PageHeadComponent,
    ErrorBannerComponent,
    UploadedFilesListComponent,
    LocationMapComponent
  ],
  templateUrl: './venue-edit.page.html',
  styleUrl: './venue-edit.page.css'
})
export class VenueEditPage implements OnInit, AfterViewInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly venuesApi = inject(VenuesApiService);
  private readonly lookupApi = inject(LookupMunicipalityApiService);
  private readonly canalsApi = inject(CanalsApiService);
  private readonly authService = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly detecting = signal(false);
  protected readonly detectCanStoreImmediately = signal<boolean | null>(null);
  protected readonly addressExpanded = signal(true);
  protected readonly infoExpanded = signal(false);
  protected readonly isEditMode = signal(false);
  private readonly mapInitialized = signal(false);
  protected readonly canUploadFiles = true;
  protected readonly errorMessage = signal('');
  protected readonly submitErrors = signal<string[]>([]);
  protected readonly filesValidationError = signal('');
  protected readonly venueId = signal<number | null>(null);
  protected readonly selectedFiles = signal<File[]>([]);
  protected readonly existingUploadedFiles = signal<UploadedFileItem[]>([]);
  protected readonly uploadedFilesResetKey = signal(0);
  protected readonly uploadOptions = signal<VenueFileUploadOptions>({
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
  protected readonly canalOptions = signal<CanalItem[]>([]);
  protected readonly statusOptions = signal<AllowedStatusOption[]>(MODEL_STATUS_OPTIONS);
  private readonly currentCanalId = signal<number | null>(null);

  protected get routePrefix(): '/dashboard' | '/admin' {
    return this.router.url.startsWith('/admin') ? '/admin' : '/dashboard';
  }

  protected get venuesScope(): VenueListScope {
    return this.routePrefix === '/admin' ? 'admin' : 'dashboard';
  }

  protected readonly hasCoordinates = computed<boolean>(() => {
    const value = this.form.getRawValue();
    const lat = (value.latitude ?? '').toString().trim();
    const lng = (value.longitude ?? '').toString().trim();
    return !!lat && !!lng && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng));
  });

  protected readonly form = new FormGroup<VenueEditForm>({
    canal_id: new FormControl<number | null>(null, { validators: [Validators.required] }),
    village_id: new FormControl<number | null>(null, { validators: [Validators.required] }),
    name: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    street: new FormControl('', { nonNullable: true }),
    postcode: new FormControl('', { nonNullable: true }),
    slug: new FormControl('', { nonNullable: true }),
    body: new FormControl('', { nonNullable: true }),
    website: new FormControl('', { nonNullable: true }),
    email: new FormControl('', { nonNullable: true }),
    phone: new FormControl('', { nonNullable: true }),
    country: new FormControl('Slovakia', { nonNullable: true }),
    latitude: new FormControl('', { nonNullable: true }),
    longitude: new FormControl('', { nonNullable: true }),
    capacity: new FormControl('', { nonNullable: true }),
    opening_hours: new FormControl('', { nonNullable: true }),
    category: new FormControl('', { nonNullable: true }),
    status: new FormControl(MODEL_STATUS.Draft, { nonNullable: true })
  });

  ngOnInit(): void {
    this.loadMunicipalityOptions();
    this.loadCanals();
    this.bindMunicipalityPostcodeAutofill();

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
        this.venueId.set(id);
        this.isEditMode.set(id !== null);
        this.resetForm();

        if (id === null) {
          this.loading.set(false);
          this.loadCreateStatusOptions();
          return;
        }

        this.loadVenue(id);
      });
  }

  private readonly syncPageMetaEffect = effect(() => {
    this.pageMeta.setPageMeta({
      title: this.isEditMode() ? 'Úprava miesta' : 'Nové miesto',
      description: this.isEditMode()
        ? 'Formulár na úpravu miesta.'
        : 'Formulár na vytvorenie nového miesta.'
    });
  });

  protected onSubmit(): void {
    clearServerValidationErrors(this.form);
    this.submitErrors.set([]);

    if (this.form.invalid) {
      this.handleInvalidSubmit();
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

    const id = this.venueId();
    const files = this.selectedFiles();
    const options = this.uploadOptions();
    const request$ =
      this.isEditMode() && id !== null
        ? files.length > 0
          ? this.venuesApi.updateWithFiles(id, payload, files, options, this.venuesScope)
          : this.venuesApi.update(id, payload, this.venuesScope)
        : files.length > 0
          ? this.venuesApi.createWithFiles(payload, files, options, this.venuesScope)
          : this.venuesApi.create(payload, this.venuesScope);

    request$
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (venue) => {
          void this.router.navigate([this.routePrefix, 'venues', venue.id]);
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  protected isFieldInvalid(controlName: keyof VenueEditForm): boolean {
    const control = this.form.controls[controlName];
    return control.invalid && (control.touched || control.dirty);
  }

  protected getFieldError(controlName: keyof VenueEditForm): string {
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
    const fileType = this.toVenueUploadType(selection.fileType) ?? 'image';

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
      this.errorMessage.set('Súbory sa uložia až po vytvorení miesta. Najprv ulož formulár.');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      this.handleInvalidSubmit('Dopln povinne polia formulara pred ulozenim suborov.');
      return;
    }

    const id = this.venueId();
    if (id === null) {
      return;
    }

    this.errorMessage.set('');
    this.saving.set(true);

    this.venuesApi
      .updateWithFiles(id, payload, files, this.uploadOptions(), this.venuesScope)
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (venue) => {
          this.existingUploadedFiles.set(venue.uploadedFiles ?? []);
          this.selectedFiles.set([]);
          this.uploadedFilesResetKey.update((value) => value + 1);
          this.toast.success('Súbory boli uložené.');
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  protected onDetectVenue(): void {
    this.submitErrors.set([]);
    this.errorMessage.set('');
    this.detectCanStoreImmediately.set(null);

    const payload = this.buildDetectPayload();
    if (!payload) {
      this.form.controls['name'].markAsTouched();
      this.form.controls['village_id'].markAsTouched();
      this.errorMessage.set('Pre analyzu vypln nazov a vyber municipality.');
      return;
    }

    this.detecting.set(true);

    this.venuesApi
      .detect(payload)
      .pipe(
        finalize(() => this.detecting.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (response) => {
          if (!response.success) {
            const message = response.error?.trim() || 'Detekcia zlyhala. Skus to znova.';
            this.submitErrors.set([message]);
            this.errorMessage.set(message);
            this.detectCanStoreImmediately.set(null);
            this.toast.error(message);
            return;
          }

          void this.applyDetectedVenue(response);
          this.detectCanStoreImmediately.set(
            typeof response.can_store_immediately === 'boolean'
              ? response.can_store_immediately
              : null
          );
          const missingFields = this.getDetectMissingRequiredFields(response);

          if (missingFields.length) {
            this.toast.info(
              `Detekcia doplnila udaje. Chybajuce povinne polia: ${missingFields.join(', ')}`
            );
            return;
          }

          this.toast.success(response.message ?? 'Udaje boli doplnene do formulara.');
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  private getDetectMissingRequiredFields(response: VenueDetectResponse): string[] {
    return response.missing_required_fields ?? [];
  }

  ngAfterViewInit(): void {
    this.form.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        if (this.hasCoordinates()) {
          this.mapInitialized.set(false);
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

  private loadMunicipalityOptions(): void {
    this.lookupApi
      .listMunicipalities()
      .pipe(
        catchError(() => of([])),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((options) => {
        this.municipalityOptions.set(options);
        this.applyMunicipalityPostcode(this.form.controls['village_id'].value);
      });
  }

  private bindMunicipalityPostcodeAutofill(): void {
    this.form.controls['village_id'].valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((municipalityId) => this.applyMunicipalityPostcode(municipalityId));
  }

  private applyMunicipalityPostcode(municipalityId: number | null): void {
    const postcodeControl = this.form.controls['postcode'];
    const municipalityControl = this.form.controls['village_id'];
    const zip = this.resolveMunicipalityZip(municipalityId);

    if (!zip) {
      return;
    }

    const currentPostcode = postcodeControl.value.trim();
    const shouldAutofill = municipalityControl.dirty || !currentPostcode;

    if (shouldAutofill && !postcodeControl.dirty && currentPostcode !== zip) {
      postcodeControl.setValue(zip, { emitEvent: false });
    }
  }

  private resolveMunicipalityZip(municipalityId: number | null): string | null {
    if (municipalityId === null) {
      return null;
    }

    return this.municipalityOptions().find((option) => option.id === municipalityId)?.zip ?? null;
  }

  private loadCreateStatusOptions(): void {
    this.venuesApi
      .index(1, 1, undefined, this.venuesScope)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((result) => {
        if (result.allowedStatuses.length) {
          this.statusOptions.set(result.allowedStatuses);
        }
      });
  }

  private loadCanals(): void {
    this.canalsApi
      .index()
      .pipe(
        catchError(() => of({ items: [] } as any)),
        map((result) => result.items ?? []),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((canals) => {
        this.canalOptions.set(canals);
      });

    this.authService.currentIdentity$
      .pipe(
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((identity) => {
        const canalId = identity?.canal_id ?? null;
        this.currentCanalId.set(canalId);

        if (canalId !== null && !this.form.controls['canal_id'].value) {
          this.form.controls['canal_id'].setValue(canalId);
        }
      });
  }

  private loadVenue(id: number): void {
    this.loading.set(true);

    this.venuesApi
      .show(id, this.venuesScope)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (venue) => {
          this.existingUploadedFiles.set(venue.uploadedFiles ?? []);
          if (venue.allowedStatuses.length) {
            this.statusOptions.set(venue.allowedStatuses);
          }
          this.form.setValue({
            canal_id: venue.canalId ?? this.currentCanalId(),
            village_id: venue.villageId,
            name: venue.name,
            street: venue.street ?? '',
            postcode: venue.postcode ?? '',
            slug: venue.slug ?? '',
            body: venue.body ?? '',
            website: venue.website ?? '',
            email: venue.email ?? '',
            phone: venue.phone ?? '',
            country: venue.country ?? 'Slovakia',
            latitude: venue.latitude ?? '',
            longitude: venue.longitude ?? '',
            capacity: venue.capacity?.toString() ?? '',
            opening_hours: this.formatOpeningHoursForForm(venue.openingHours),
            category: venue.category ?? '',
            status: venue.status
          });
          this.applyMunicipalityPostcode(venue.villageId);
        },
        error: () => {
          this.errorMessage.set('Nepodarilo sa nacitat venue na upravu.');
        }
      });
  }

  private resetForm(): void {
    this.form.reset({
      canal_id: this.currentCanalId(),
      village_id: null,
      name: '',
      street: '',
      postcode: '',
      slug: '',
      body: '',
      website: '',
      email: '',
      phone: '',
      country: 'Slovakia',
      latitude: '',
      longitude: '',
      capacity: '',
      opening_hours: '',
      category: '',
      status: MODEL_STATUS.Draft
    });

    clearServerValidationErrors(this.form);
    this.submitErrors.set([]);
    this.detectCanStoreImmediately.set(null);

    this.filesValidationError.set('');
    this.selectedFiles.set([]);
    this.existingUploadedFiles.set([]);
    this.uploadedFilesResetKey.update((value) => value + 1);
    this.uploadOptions.set({
      file_disk: 'public',
      make_primary_file: false
    });
  }

  private handleInvalidSubmit(message = 'Skontroluj prosim povinne polia formulara.'): void {
    this.form.markAllAsTouched();
    this.expandSectionsWithErrors();
    this.errorMessage.set(message);
  }

  private expandSectionsWithErrors(): void {
    if (this.hasAddressSectionErrors()) {
      this.addressExpanded.set(true);
    }

    if (this.hasInfoSectionErrors()) {
      this.infoExpanded.set(true);
    }
  }

  private hasAddressSectionErrors(): boolean {
    return this.sectionHasErrors(['street', 'postcode', 'country', 'latitude', 'longitude']);
  }

  private hasInfoSectionErrors(): boolean {
    return this.sectionHasErrors([
      'canal_id',
      'status',
      'slug',
      'capacity',
      'website',
      'email',
      'phone',
      'category',
      'body',
      'opening_hours'
    ]);
  }

  private sectionHasErrors(controlNames: Array<keyof VenueEditForm>): boolean {
    return controlNames.some((controlName) => this.form.controls[controlName].invalid);
  }

  private toVenueUploadType(value: string | null): VenueFileUploadOptions['file_type'] | undefined {
    if (value === 'image' || value === 'card' || value === 'file') {
      return value;
    }

    return undefined;
  }

  private buildDetectPayload(): VenueDetectPayload | null {
    const value = this.form.getRawValue();
    const name = value.name.trim();
    const city = this.resolveDetectCity(value.village_id);
    const country = value.country.trim();

    if (!name || !city) {
      return null;
    }

    return {
      name,
      city,
      country: country || null
    };
  }

  private resolveDetectCity(villageId: number | null): string {
    if (villageId === null) {
      return '';
    }

    const match = this.municipalityOptions().find((option) => option.id === villageId);
    return match?.name.trim() ?? '';
  }

  private async applyDetectedVenue(response: VenueDetectResponse): Promise<void> {
    const storePayload = response.venue_store_payload ?? {};
    const venuePayload = response.venue_payload ?? {};
    const current = this.form.getRawValue();

    const detectedVillageId =
      this.toNumberOrNull(storePayload.village_id) ?? this.toNumberOrNull(venuePayload.village_id);

    this.form.patchValue({
      canal_id: current.canal_id,
      village_id: detectedVillageId ?? current.village_id,
      name: this.resolveStringField([storePayload.name, venuePayload.name], current.name),
      street: this.resolveStringField([storePayload.street, venuePayload.street], current.street),
      postcode: this.resolveStringField([storePayload.postcode, venuePayload.postcode], current.postcode),
      body: this.resolveStringField([storePayload.body, venuePayload.body], current.body),
      website: this.resolveStringField([storePayload.website, venuePayload.website], current.website),
      email: this.resolveStringField([storePayload.email, venuePayload.email], current.email),
      phone: this.resolveStringField([storePayload.phone, venuePayload.phone], current.phone),
      country: this.resolveStringField([storePayload.country, venuePayload.country], current.country),
      latitude: this.resolveScalarAsString([storePayload.latitude, venuePayload.latitude], current.latitude),
      longitude: this.resolveScalarAsString([storePayload.longitude, venuePayload.longitude], current.longitude),
      capacity: this.resolveScalarAsString([storePayload.capacity, venuePayload.capacity], current.capacity),
      opening_hours: this.resolveOpeningHoursField(
        [storePayload.opening_hours, venuePayload.opening_hours],
        current.opening_hours
      ),
      category: this.resolveStringField([storePayload.category, venuePayload.category], current.category),
      status: sanitizeModelStatus(storePayload.status ?? venuePayload.status ?? current.status)
    });

    const detectedFiles = extractUploadedFiles(
      { attached_files: response.attached_files ?? [] },
      venuePayload.image_url ?? null
    );
    const allImageUrls = this.collectDetectImageUrls(venuePayload as Record<string, unknown>);
    const allImages = [...detectedFiles, ...allImageUrls];

    if (allImages.length > 0) {
      const pendingFiles = await this.toPendingFiles(allImages);
      if (pendingFiles.length > 0) {
        const merged = this.mergeSelectedFiles(this.selectedFiles(), pendingFiles);
        this.selectedFiles.set(merged);
      }
    }

    this.form.markAsDirty();
  }

  private async toPendingFiles(files: UploadedFileItem[]): Promise<File[]> {
    const result: File[] = [];

    for (const file of files) {
      if (!file.url) {
        continue;
      }

      try {
        const response = await fetch(file.url);
        if (!response.ok) {
          continue;
        }

        const blob = await response.blob();
        const mimeType = blob.type || file.mimeType || 'application/octet-stream';
        const name = this.resolveDetectedFileName(file.name, mimeType);
        result.push(new File([blob], name, { type: mimeType }));
      } catch {
        continue;
      }
    }

    return result;
  }

  private mergeSelectedFiles(current: File[], incoming: File[]): File[] {
    const merged = [...current];

    for (const file of incoming) {
      const exists = merged.some(
        (existing) =>
          existing.name === file.name &&
          existing.size === file.size &&
          existing.lastModified === file.lastModified
      );

      if (!exists) {
        merged.push(file);
      }
    }

    return merged;
  }

  private resolveDetectedFileName(name: string, mimeType: string): string {
    const normalized = name.trim() || 'detected-image';
    if (normalized.includes('.')) {
      return normalized;
    }

    const extension = this.mimeTypeToExtension(mimeType);
    return extension ? `${normalized}.${extension}` : normalized;
  }

  private mimeTypeToExtension(mimeType: string): string {
    switch (mimeType.toLowerCase()) {
      case 'image/jpeg':
        return 'jpg';
      case 'image/png':
        return 'png';
      case 'image/webp':
        return 'webp';
      case 'image/gif':
        return 'gif';
      case 'image/avif':
        return 'avif';
      case 'image/svg+xml':
        return 'svg';
      default:
        return '';
    }
  }

  private collectDetectImageUrls(venuePayload: Record<string, unknown>): UploadedFileItem[] {
    const images: UploadedFileItem[] = [];
    const urls: string[] = [];

    // Collect all individual image URLs
    const imageUrl = venuePayload['image_url'];
    if (typeof imageUrl === 'string' && imageUrl.trim()) {
      urls.push(imageUrl.trim());
    }

    const imageUrls = venuePayload['image_urls'];
    if (Array.isArray(imageUrls)) {
      for (const url of imageUrls) {
        if (typeof url === 'string' && url.trim()) {
          urls.push(url.trim());
        }
      }
    }

    const logoUrl = venuePayload['logo_url'];
    if (typeof logoUrl === 'string' && logoUrl.trim()) {
      urls.push(logoUrl.trim());
    }

    // Deduplicate URLs
    const uniqueUrls = [...new Set(urls)];

    // Convert URLs to UploadedFileItem
    for (let i = 0; i < uniqueUrls.length; i++) {
      const url = uniqueUrls[i];
      images.push({
        name: `detected-image-${i + 1}`,
        url,
        type: 'image',
        isPrimary: i === 0 // First image is primary
      });
    }

    return images;
  }

  private resolveStringField(values: Array<string | null | undefined>, fallback: string): string {
    for (const value of values) {
      if (typeof value === 'string') {
        return value;
      }
    }

    return fallback;
  }

  private resolveOpeningHoursField(values: Array<unknown>, fallback: string): string {
    for (const value of values) {
      if (value === null || value === undefined) {
        continue;
      }

      if (Array.isArray(value)) {
        try {
          return JSON.stringify(value, null, 2);
        } catch {
          continue;
        }
      }

      if (typeof value === 'string') {
        return value;
      }

      try {
        return JSON.stringify(value, null, 2);
      } catch {
        continue;
      }
    }

    return fallback;
  }

  private formatOpeningHoursForForm(value: unknown[] | string | null | undefined): string {
    if (Array.isArray(value)) {
      try {
        return JSON.stringify(value, null, 2);
      } catch {
        return '';
      }
    }

    return typeof value === 'string' ? value : '';
  }

  private parseOpeningHoursInput(value: string): unknown[] | null {
    const trimmed = value.trim();
    if (!trimmed) {
      return null;
    }

    try {
      const parsed = JSON.parse(trimmed);
      return Array.isArray(parsed) ? parsed : [parsed];
    } catch {
      return [trimmed];
    }
  }

  private resolveScalarAsString(
    values: Array<string | number | null | undefined>,
    fallback: string
  ): string {
    for (const value of values) {
      if (typeof value === 'string') {
        return value;
      }

      if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
      }
    }

    return fallback;
  }

  private toNumberOrNull(value: number | string | null | undefined): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }

    if (typeof value === 'string' && value.trim()) {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
  }

  private buildPayload(): VenueUpsertPayload | null {
    const value = this.form.getRawValue();

    if (value.canal_id === null || value.village_id === null) {
      return null;
    }

    const name = (value.name ?? '').trim();
    const body = (value.body ?? '').trim();

    if (!name) {
      return null;
    }

    const street = value.street.trim();
    const postcode = value.postcode.trim();
    const slug = value.slug.trim();
    const website = value.website.trim();
    const email = value.email.trim();
    const phone = value.phone.trim();
    const country = value.country.trim();
    const latitude = value.latitude.trim();
    const longitude = value.longitude.trim();
    const openingHours = this.parseOpeningHoursInput(value.opening_hours);
    const category = value.category.trim();
    let capacity: number | null = null;
    const capacityRaw = value.capacity.trim();
    if (capacityRaw) {
      const parsed = Number(capacityRaw);
      if (!Number.isFinite(parsed)) {
        return null;
      }
      capacity = parsed;
    }

    return {
      canal_id: value.canal_id,
      village_id: value.village_id,
      name,
      street: street || null,
      postcode: postcode || null,
      slug: slug || undefined,
      body,
      website: website || null,
      email: email || null,
      phone: phone || null,
      country: country || null,
      latitude: latitude || null,
      longitude: longitude || null,
      capacity,
      opening_hours: openingHours,
      category: category || null,
      status: value.status
    };
  }

  private applySubmitError(error: unknown): void {
    const fallback = 'Ulozenie venue zlyhalo. Skus to znova.';
    const payload = (error as HttpErrorResponse | null)?.error;
    const result = applyServerValidationErrors({
      form: this.form,
      payload,
      fieldMap: VENUE_FIELD_MAP,
      fallbackMessage: fallback
    });

    this.submitErrors.set(result.summary);
    this.errorMessage.set(result.summary[0] ?? fallback);

    if (result.mappedAny) {
      this.form.markAllAsTouched();
    }
  }
}
