import { ComponentFixture, TestBed } from '@angular/core/testing';
import { convertToParamMap } from '@angular/router';
import { ActivatedRoute, provideRouter, Router } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { LookupMunicipalityApiService } from '../../../shared/services/lookup-municipality-api.service';
import { CanalItem } from '../models/canal.model';
import { CanalsApiService } from '../services/canals-api.service';
import { CanalEditPage } from './canal-edit.page';

describe('CanalEditPage', () => {
  let fixture: ComponentFixture<CanalEditPage>;
  let component: CanalEditPage;
  let canalsApi: {
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    show: ReturnType<typeof vi.fn>;
    index: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };
  let router: {
    navigate: ReturnType<typeof vi.fn>;
  };

  const buildCanal = (id: number): CanalItem => ({
    id,
    municipalityId: 1,
    venueId: null,
    identityMode: 'personal',
    name: 'Moj kanal',
    slug: 'moj-kanal',
    titlePrefix: '',
    titleSuffix: '',
    email: 'kanal@example.com',
    emailVerifiedAt: null,
    body: 'Popis',
    imageUrl: '',
    publishedAt: null,
    status: 'draft',
    website: null,
    registrationSource: 'manual',
    deletedAt: null,
    createdAt: null,
    updatedAt: null,
    canal: 'Moj kanal',
    uploadedFiles: [],
    permissions: {
      view: true,
      update: true,
      delete: false,
      restore: false
    }
  });

  beforeEach(async () => {
    canalsApi = {
      create: vi.fn(),
      update: vi.fn(),
      show: vi.fn(),
      index: vi.fn(),
      delete: vi.fn()
    };
    canalsApi.create.mockReturnValue(of(buildCanal(11)));
    canalsApi.update.mockReturnValue(of(buildCanal(11)));

    router = {
      navigate: vi.fn().mockResolvedValue(true)
    };

    await TestBed.configureTestingModule({
      imports: [CanalEditPage],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({}))
          }
        },
        {
          provide: CanalsApiService,
          useValue: canalsApi as unknown as CanalsApiService
        },
        {
          provide: LookupMunicipalityApiService,
          useValue: {
            listMunicipalities: () => of([]),
            list: () => of([])
          }
        },
        {
          provide: Router,
          useValue: router as unknown as Router
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(CanalEditPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  const FILL_MODE_TO_ID: Record<string, number> = { personal: 1, organization: 2, pseudonymous: 3 };
  const fillRequiredForm = (identityMode: 'personal' | 'organization' | 'pseudonymous') => {
    const form = (component as any).form;
    form.patchValue({
      municipality_id: 123,
      venue_id: null,
      identity_mode: FILL_MODE_TO_ID[identityMode],
      name: 'Moj kanal',
      slug: 'moj-kanal',
      title_prefix: '',
      title_suffix: '',
      email: 'kanal@example.com',
      body: 'Popis kanala',
      status: 'draft',
      website: ''
    });
  };

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should offer all three identity mode options', () => {
    const options = (component as any).identityModeOptions;
    expect(options.map((option: { name: string }) => option.name)).toEqual(['Osobný', 'Firemný', 'Krycie meno']);
  });

  it('should send identity_mode in create payload for all options', () => {
    const modes: Array<'personal' | 'organization' | 'pseudonymous'> = [
      'personal',
      'organization',
      'pseudonymous'
    ];

    for (const mode of modes) {
      canalsApi.create.mockReset();
      canalsApi.create.mockReturnValue(of(buildCanal(11)));
      fillRequiredForm(mode);

      (component as any).onSubmit();

      expect(canalsApi.create).toHaveBeenCalledWith(
        expect.objectContaining({
          identity_mode: mode
        })
      );
    }
  });

  it('should block submit when identity mode is not selected', () => {
    fillRequiredForm('personal');
    const identityModeControl = (component as any).form.controls.identity_mode;
    identityModeControl.setValue(null);
    identityModeControl.markAsTouched();
    identityModeControl.updateValueAndValidity();

    (component as any).onSubmit();

    expect(canalsApi.create).not.toHaveBeenCalled();
    expect((component as any).form.controls.identity_mode.hasError('required')).toBe(true);
    expect((component as any).getFieldError('identity_mode')).toBe('Toto pole je povinne.');
  });
});
