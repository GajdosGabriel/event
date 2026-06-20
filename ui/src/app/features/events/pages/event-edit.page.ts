import { Component, DestroyRef, computed, effect, inject, OnInit, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { catchError, finalize, forkJoin, map, of } from 'rxjs';
import { API_ENDPOINTS } from '../../../constants/api.constants';
import { EntitySelectComponent } from '../../../shared/components/entity-select/entity-select.component';
import { PageHeadComponent } from '../../../shared/components/page-head/page-head.component';
import {
  LookupMunicipalityApiService,
  LookupOption
} from '../../../shared/services/lookup-municipality-api.service';
import {
  EventFileUploadOptions,
  EventUpsertPayload,
  EventsApiService
} from '../services/events-api.service';
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
import { AuthService } from '../../../core/services/auth.service';
import { MODEL_STATUS, AllowedStatusOption, MODEL_STATUS_OPTIONS, ModelStatus } from '../../../shared/models/model-status';
import { PageMetaService } from '../../../shared/services/page-meta.service';
import { VenuesApiService, VenueUpsertPayload } from '../../venue/services/venues-api.service';
import { RichTextEditorComponent } from '../../../shared/components/rich-text-editor/rich-text-editor.component';

type EventEditForm = {
  canal_id: FormControl<number | null>;
  venue_id: FormControl<number | null>;
  name: FormControl<string>;
  body: FormControl<string>;
  start_date: FormControl<string>;
  start_time: FormControl<string>;
  end_date: FormControl<string>;
  end_time: FormControl<string>;
  registration_deadline_date: FormControl<string>;
  registration_deadline_time: FormControl<string>;
  status: FormControl<ModelStatus>;
  website: FormControl<string>;
  municipality_id: FormControl<number | null>;
  location_name: FormControl<string>;
  street: FormControl<string>;
  postcode: FormControl<string>;
  latitude: FormControl<string>;
  longitude: FormControl<string>;
};

type EventUploadType = NonNullable<EventFileUploadOptions['file_type']>;

type NewVenueForm = {
  name: FormControl<string>;
  village_id: FormControl<number | null>;
  street: FormControl<string>;
  postcode: FormControl<string>;
};

const EVENT_FIELD_MAP: ServerFieldMap<keyof EventEditForm> = {
  canal_id: ['canal_id'],
  venue_id: ['venue_id'],
  name: ['name'],
  body: ['body'],
  start_at: ['start_date', 'start_time'],
  start_date: ['start_date'],
  start_time: ['start_time'],
  startat: ['start_date', 'start_time'],
  end_at: ['end_date', 'end_time'],
  end_date: ['end_date'],
  end_time: ['end_time'],
  endat: ['end_date', 'end_time'],
  registration_deadline_at: ['registration_deadline_date', 'registration_deadline_time'],
  status: ['status'],
  website: ['website'],
  municipality_id: ['municipality_id'],
  municipality: ['municipality_id'],
  location_name: ['location_name'],
  street: ['street'],
  postcode: ['postcode'],
  latitude: ['latitude'],
  longitude: ['longitude']
};

@Component({
  selector: 'app-event-edit-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    EntitySelectComponent,
    PageHeadComponent,
    ErrorBannerComponent,
    UploadedFilesListComponent,
    RichTextEditorComponent
  ],
  templateUrl: './event-edit.page.html',
  styleUrl: './event-edit.page.css'
})
export class EventEditPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly eventsApi = inject(EventsApiService);
  private readonly venuesApi = inject(VenuesApiService);
  private readonly lookupApi = inject(LookupMunicipalityApiService);
  private readonly authService = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly addressExpanded = signal(false);
  protected readonly isEditMode = signal(false);
  protected readonly newVenueDialogOpen = signal(false);
  protected readonly newVenueSaving = signal(false);
  private readonly currentIdentity = toSignal(this.authService.currentIdentity$, { initialValue: null });
  protected readonly isSuperAdmin = computed(() => this.currentIdentity()?.roles?.includes('super-admin') ?? false);
  protected readonly canUploadFiles = true;
  protected readonly errorMessage = signal('');
  protected readonly submitErrors = signal<string[]>([]);
  protected readonly filesValidationError = signal('');
  protected readonly eventId = signal<number | null>(null);
  protected readonly selectedFiles = signal<File[]>([]);
  protected readonly existingUploadedFiles = signal<UploadedFileItem[]>([]);
  protected readonly uploadedFilesResetKey = signal(0);
  protected readonly uploadOptions = signal<EventFileUploadOptions>({
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

  protected readonly statusOptions = signal<AllowedStatusOption[]>(MODEL_STATUS_OPTIONS);

  protected readonly canalOptions = signal<LookupOption[]>([]);
  protected readonly venueOptions = signal<LookupOption[]>([]);
  protected readonly municipalityOptions = signal<LookupOption[]>([]);
  private readonly currentCanalId = signal<number | null>(null);

  protected readonly form = new FormGroup<EventEditForm>({
    canal_id: new FormControl<number | null>(null),
    venue_id: new FormControl<number | null>(null),
    name: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    body: new FormControl('', { nonNullable: true }),
    start_date: new FormControl('', { nonNullable: true }),
    start_time: new FormControl('', { nonNullable: true }),
    end_date: new FormControl('', { nonNullable: true }),
    end_time: new FormControl('', { nonNullable: true }),
    registration_deadline_date: new FormControl('', { nonNullable: true }),
    registration_deadline_time: new FormControl('', { nonNullable: true }),
    status: new FormControl(MODEL_STATUS.Draft, { nonNullable: true }),
    website: new FormControl('', { nonNullable: true }),
    municipality_id: new FormControl<number | null>(null),
    location_name: new FormControl('', { nonNullable: true }),
    street: new FormControl('', { nonNullable: true }),
    postcode: new FormControl('', { nonNullable: true }),
    latitude: new FormControl('', { nonNullable: true }),
    longitude: new FormControl('', { nonNullable: true })
  });

  protected readonly newVenueForm = new FormGroup<NewVenueForm>({
    name: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    village_id: new FormControl<number | null>(null, { validators: [Validators.required] }),
    street: new FormControl('', { nonNullable: true }),
    postcode: new FormControl('', { nonNullable: true })
  });

  ngOnInit(): void {
    this.loadLookupOptions();
    this.prefillCanalFromIdentity();
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
        this.eventId.set(id);
        this.isEditMode.set(id !== null);
        this.resetForm();

        if (id === null) {
          this.loading.set(false);
          this.loadCreateStatusOptions();
          const canalId = this.currentCanalId();
          if (canalId !== null) {
            this.form.controls['canal_id'].setValue(canalId);
            this.form.controls['canal_id'].disable();
          }
          return;
        }

        this.loadEvent(id);
      });
  }

  private readonly syncPageMetaEffect = effect(() => {
    this.pageMeta.setPageMeta({
      title: this.isEditMode() ? 'Úprava podujatia' : 'Nové podujatie',
      description: this.isEditMode()
        ? 'Formulár na úpravu podujatia.'
        : 'Formulár na vytvorenie nového podujatia.'
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

    const id = this.eventId();
    const files = this.selectedFiles();
    const options = this.uploadOptions();
    const request$ = this.isEditMode() && id !== null
      ? files.length > 0
        ? this.eventsApi.updateWithFiles(id, payload, files, options)
        : this.eventsApi.update(id, payload)
      : files.length > 0
        ? this.eventsApi.createWithFiles(payload, files, options)
        : this.eventsApi.create(payload);

    request$
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: () => {
          void this.router.navigate(this.resolveSuccessRoute());
        },
        error: (error) => {
          this.applySubmitError(error);
        }
      });
  }

  protected isFieldInvalid(controlName: keyof EventEditForm): boolean {
    const control = this.form.controls[controlName];
    return control.invalid && (control.touched || control.dirty);
  }

  protected getFieldError(controlName: keyof EventEditForm): string {
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
    const fileType = this.toEventUploadType(selection.fileType) ?? 'image';

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
      this.errorMessage.set('Súbory sa uložia až po vytvorení podujatia. Najprv ulož formulár.');
      return;
    }

    const payload = this.buildPayload();
    if (!payload) {
      this.form.markAllAsTouched();
      this.errorMessage.set('Dopln povinne polia formulara pred ulozenim suborov.');
      return;
    }

    const id = this.eventId();
    if (id === null) {
      return;
    }

    this.errorMessage.set('');
    this.saving.set(true);

    this.eventsApi
      .updateWithFiles(id, payload, files, this.uploadOptions())
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (event) => {
          this.existingUploadedFiles.set(event.uploadedFiles ?? []);
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

  private toEventUploadType(value: string | null): EventUploadType | undefined {
    if (value === 'image' || value === 'card' || value === 'file') {
      return value;
    }

    return undefined;
  }

  protected openNewVenueDialog(): void {
    this.newVenueForm.reset({ name: '', village_id: null, street: '', postcode: '' });
    this.newVenueDialogOpen.set(true);
  }

  protected closeNewVenueDialog(): void {
    this.newVenueDialogOpen.set(false);
  }

  protected onSaveNewVenue(): void {
    if (this.newVenueForm.invalid) {
      this.newVenueForm.markAllAsTouched();
      return;
    }

    const value = this.newVenueForm.getRawValue();
    const canalId = this.currentCanalId();
    if (value.village_id === null || canalId === null) {
      return;
    }

    const payload: VenueUpsertPayload = {
      canal_id: canalId,
      village_id: value.village_id,
      name: value.name.trim(),
      body: '',
      street: value.street.trim() || null,
      postcode: value.postcode.trim() || null
    };

    this.newVenueSaving.set(true);

    this.venuesApi
      .create(payload)
      .pipe(
        finalize(() => this.newVenueSaving.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (venue) => {
          const newOption = { id: venue.id, name: venue.name };
          this.venueOptions.update((opts) => [newOption, ...opts]);
          this.form.controls['venue_id'].setValue(venue.id);
          this.newVenueDialogOpen.set(false);
          this.toast.success('Miesto konania bolo vytvorené.');
        },
        error: () => {
          this.toast.error('Nepodarilo sa vytvoriť miesto konania.');
        }
      });
  }

  protected isNewVenueFieldInvalid(controlName: keyof NewVenueForm): boolean {
    const control = this.newVenueForm.controls[controlName];
    return control.invalid && (control.touched || control.dirty);
  }

  private loadCreateStatusOptions(): void {
    this.eventsApi
      .index(1, 1)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((result) => {
        if (result.allowedStatuses.length) {
          this.statusOptions.set(result.allowedStatuses);
        }
      });
  }

  private loadLookupOptions(): void {
    forkJoin({
      canals: this.lookupApi.list(API_ENDPOINTS.canals).pipe(catchError(() => of([]))),
      venues: this.lookupApi.list(API_ENDPOINTS.venues).pipe(catchError(() => of([]))),
      municipalities: this.lookupApi.listMunicipalities().pipe(catchError(() => of([])))
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(({ canals, venues, municipalities }) => {
        this.canalOptions.set(canals);
        this.venueOptions.set(venues);
        this.municipalityOptions.set(municipalities);
        this.applyMunicipalityPostcode(this.form.controls['municipality_id'].value, this.form.controls['postcode']);
        this.applyMunicipalityPostcode(this.newVenueForm.controls['village_id'].value, this.newVenueForm.controls['postcode']);
      });
  }

  private bindMunicipalityPostcodeAutofill(): void {
    this.form.controls['municipality_id'].valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((municipalityId) =>
        this.applyMunicipalityPostcode(municipalityId, this.form.controls['postcode'])
      );

    this.newVenueForm.controls['village_id'].valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((municipalityId) =>
        this.applyMunicipalityPostcode(municipalityId, this.newVenueForm.controls['postcode'])
      );
  }

  private applyMunicipalityPostcode(
    municipalityId: number | null,
    postcodeControl: FormControl<string>
  ): void {
    const zip = this.resolveMunicipalityZip(municipalityId);

    if (!zip) {
      return;
    }

    const currentPostcode = postcodeControl.value.trim();
    if (!postcodeControl.dirty && currentPostcode !== zip) {
      postcodeControl.setValue(zip, { emitEvent: false });
    }
  }

  private resolveMunicipalityZip(municipalityId: number | null): string | null {
    if (municipalityId === null) {
      return null;
    }

    return this.municipalityOptions().find((option) => option.id === municipalityId)?.zip ?? null;
  }

  private prefillCanalFromIdentity(): void {
    this.authService.currentIdentity$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((identity) => {
        const canalId = identity?.canal_id ?? null;
        this.currentCanalId.set(canalId);

        if (canalId !== null && !this.form.controls['canal_id'].value) {
          this.form.controls['canal_id'].setValue(canalId);
          this.form.controls['canal_id'].disable();
        }
      });
  }

  private loadEvent(id: number): void {
    this.loading.set(true);

    this.eventsApi
      .show(id)
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (event) => {
          this.existingUploadedFiles.set(event.uploadedFiles ?? []);
          if (event.allowedStatuses.length) {
            this.statusOptions.set(event.allowedStatuses);
          }
          const canalId = Number(event.canalId);
          const venueRaw = Number(event.venueId);
          const venueId = event.venueId === null || !Number.isFinite(venueRaw) ? null : venueRaw;

          const start = this.splitApiDateTime(event.startAt ?? '');
          const end = this.splitApiDateTime(event.endAt ?? '');
          const deadline = event.registrationDeadlineAt
            ? this.splitApiDateTime(event.registrationDeadlineAt)
            : { date: '', time: '' };

          this.form.setValue({
            canal_id: Number.isFinite(canalId) ? canalId : null,
            venue_id: venueId,
            name: event.name,
            body: event.body,
            start_date: start.date,
            start_time: start.time,
            end_date: end.date,
            end_time: end.time,
            registration_deadline_date: deadline.date,
            registration_deadline_time: deadline.time,
            status: event.status,
            website: event.website ?? '',
            municipality_id: event.municipalityId ?? null,
            location_name: event.locationName ?? '',
            street: event.street ?? '',
            postcode: event.postcode ?? '',
            latitude: event.latitude ?? '',
            longitude: event.longitude ?? ''
          });
          this.applyMunicipalityPostcode(
            event.municipalityId ?? null,
            this.form.controls['postcode']
          );
        },
        error: () => {
          this.errorMessage.set('Nepodarilo sa nacitat event na upravu.');
        }
      });
  }

  private resetForm(): void {
    this.form.reset({
      canal_id: null,
      venue_id: null,
      name: '',
      body: '',
      start_date: '',
      start_time: '',
      end_date: '',
      end_time: '',
      registration_deadline_date: '',
      registration_deadline_time: '',
      status: MODEL_STATUS.Draft,
      website: '',
      municipality_id: null,
      location_name: '',
      street: '',
      postcode: '',
      latitude: '',
      longitude: ''
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

  private buildPayload(): EventUpsertPayload | null {
    const value = this.form.getRawValue();
    const canalId = value.canal_id ?? this.currentCanalId();

    const name = value.name.trim();
    const body = value.body.trim();
    const startAt = this.toApiDateTime(value.start_date, value.start_time);
    const endAt = this.toApiDateTime(value.end_date, value.end_time);

    if (!name) {
      return null;
    }

    const website = value.website.trim();
    const locationName = value.location_name.trim();
    const street = value.street.trim();
    const postcode = value.postcode.trim();
    const latitude = value.latitude.trim();
    const longitude = value.longitude.trim();
    const deadlineAt = this.toApiDateTime(
      value.registration_deadline_date,
      value.registration_deadline_time
    ) || null;

    return {
      ...(canalId !== null ? { canal_id: canalId } : {}),
      ...(value.venue_id !== null ? { venue_id: value.venue_id } : {}),
      ...(value.municipality_id !== null ? { municipality_id: value.municipality_id } : {}),
      name,
      ...(body ? { body } : {}),
      ...(startAt ? { start_at: startAt } : {}),
      ...(endAt ? { end_at: endAt } : {}),
      ...(deadlineAt ? { registration_deadline_at: deadlineAt } : {}),
      status: value.status,
      website: website || null,
      location_name: locationName || null,
      street: street || null,
      postcode: postcode || null,
      latitude: latitude || null,
      longitude: longitude || null
    };
  }

  private splitApiDateTime(value: string): { date: string; time: string } {
    const dateTimeLocal = this.toDateTimeLocal(value);
    if (!dateTimeLocal) {
      return { date: '', time: '' };
    }

    const [date, time] = dateTimeLocal.split('T');
    return {
      date,
      time: (time ?? '').slice(0, 5)
    };
  }

  private toDateTimeLocal(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    const shifted = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return shifted.toISOString().slice(0, 16);
  }

  private toApiDateTime(dateValue: string, timeValue: string): string {
    if (!dateValue || !timeValue) {
      return '';
    }

    const date = new Date(`${dateValue}T${timeValue}`);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    return date.toISOString();
  }

  private resolveSuccessRoute(): string[] {
    return [this.router.url.startsWith('/admin/') ? '/admin/events' : '/dashboard/events'];
  }

  private applySubmitError(error: unknown): void {
    const fallback = 'Uloženie eventu zlyhalo. Skús to znova.';
    const payload = (error as HttpErrorResponse | null)?.error;
    const result = applyServerValidationErrors({
      form: this.form,
      payload,
      fieldMap: EVENT_FIELD_MAP,
      fallbackMessage: fallback
    });

    this.submitErrors.set(result.summary);
    this.errorMessage.set(result.summary[0] ?? fallback);

    if (result.mappedAny) {
      this.form.markAllAsTouched();
    }
  }
}
