import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CanalShowPage } from './canal-show.page';
import { CanalsApiService } from '../services/canals-api.service';
import { CanalItem } from '../models/canal.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';
import { VenuesApiService } from '../../venue/services/venues-api.service';
import { VenueItem } from '../../venue/models/venue.model';

describe('CanalShowPage', () => {
  let fixture: ComponentFixture<CanalShowPage>;
  let component: CanalShowPage;
  let canalsApi: {
    show: ReturnType<typeof vi.fn>;
  };
  let venuesApi: {
    show: ReturnType<typeof vi.fn>;
  };

  const canalItem: CanalItem = {
    id: 7,
    municipalityId: 1,
    venueId: 9,
    identityMode: 'personal',
    name: 'Canal',
    slug: 'canal',
    titlePrefix: null,
    titleSuffix: null,
    email: 'canal@example.com',
    emailVerifiedAt: null,
    body: 'Popis kanála',
    imageUrl: '',
    publishedAt: null,
    status: MODEL_STATUS.Published,
    website: 'https://canal.example.com',
    registrationSource: 'manual',
    deletedAt: null,
    createdAt: null,
    updatedAt: null,
    canal: 'Canal',
    uploadedFiles: [
      { id: 1, name: 'brochure.pdf', url: 'https://example.com/brochure.pdf', mimeType: 'application/pdf' }
    ],
    permissions: {
      view: true,
      update: true,
      delete: false,
      restore: false
    }
  };

  const venueItem: VenueItem = {
    id: 9,
    canalId: 1,
    villageId: 1,
    name: 'Dom kultury',
    street: 'Hlavná 1',
    postcode: '90001',
    slug: 'dom-kultury',
    body: 'Venue body',
    website: null,
    email: null,
    phone: null,
    country: 'Slovensko',
    latitude: '48.1',
    longitude: '17.1',
    capacity: 100,
    openingHours: [],
    imageUrl: '',
    category: 'Kultúra',
    status: MODEL_STATUS.Published,
    deletedAt: null,
    createdAt: null,
    updatedAt: null,
    uploadedFiles: [],
    permissions: {
      view: true,
      update: true,
      delete: false,
      restore: false
    }
  };

  beforeEach(async () => {
    canalsApi = {
      show: vi.fn().mockReturnValue(of(canalItem))
    };
    venuesApi = {
      show: vi.fn().mockReturnValue(of(venueItem))
    };

    await TestBed.configureTestingModule({
      imports: [CanalShowPage],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ id: String(canalItem.id) }))
          }
        },
        {
          provide: CanalsApiService,
          useValue: canalsApi as unknown as CanalsApiService
        },
        {
          provide: VenuesApiService,
          useValue: venuesApi as unknown as VenuesApiService
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(CanalShowPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  });

  it('should load venue details for map rendering', () => {
    expect(component).toBeTruthy();
    expect(canalsApi.show).toHaveBeenCalledWith(canalItem.id);
    expect(venuesApi.show).toHaveBeenCalledWith(canalItem.venueId);
    expect((component as any).vm().venue?.name).toBe('Dom kultury');
  });

  it('should render canal gallery and map section', () => {
    expect(fixture.nativeElement.textContent).toContain('Fotogaleria kanálu');
    expect(fixture.nativeElement.textContent).toContain('Miesto');
    expect(fixture.nativeElement.querySelector('app-location-map')).toBeTruthy();
  });
});
