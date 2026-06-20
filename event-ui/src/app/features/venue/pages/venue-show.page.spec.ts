import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { VenueShowPage } from './venue-show.page';
import { VenuesApiService } from '../services/venues-api.service';
import { VenueItem } from '../models/venue.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';

describe('VenueShowPage', () => {
  let fixture: ComponentFixture<VenueShowPage>;
  let component: VenueShowPage;
  let venuesApi: {
    show: ReturnType<typeof vi.fn>;
  };
  let router: {
    url: string;
  };

  const venueItem: VenueItem = {
    id: 9,
    canalId: 1,
    villageId: 1,
    name: 'Dom kultury',
    street: 'Hlavná 1',
    postcode: '90001',
    slug: 'dom-kultury',
    body: 'Popis miesta',
    website: 'https://venue.example.com',
    email: null,
    phone: null,
    country: 'Slovensko',
    latitude: '48.1',
    longitude: '17.1',
    capacity: 150,
    openingHours: [{ po: '08:00 - 18:00' }],
    imageUrl: '',
    category: 'Kultúra',
    status: MODEL_STATUS.Published,
    deletedAt: null,
    createdAt: null,
    updatedAt: null,
    uploadedFiles: [
      { id: 1, name: 'venue.jpg', url: 'https://example.com/venue.jpg', mimeType: 'image/jpeg' }
    ],
    permissions: {
      view: true,
      update: true,
      delete: false,
      restore: false
    }
  };

  const createComponent = async (url: string) => {
    router.url = url;

    await TestBed.configureTestingModule({
      imports: [VenueShowPage],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ id: String(venueItem.id) }))
          }
        },
        {
          provide: VenuesApiService,
          useValue: venuesApi as unknown as VenuesApiService
        },
        {
          provide: Router,
          useValue: router as unknown as Router
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(VenueShowPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  };

  beforeEach(() => {
    TestBed.resetTestingModule();
    venuesApi = {
      show: vi.fn().mockReturnValue(of(venueItem))
    };
    router = {
      url: '/dashboard/venues/9'
    };
  });

  it('should expose dashboard venue links for dashboard route', async () => {
    await createComponent('/dashboard/venues/9');

    expect((component as any).pageContext().backLink).toBe('/dashboard/venues');
    expect(fixture.nativeElement.textContent).toContain('Fotogaleria miesta');
    expect(fixture.nativeElement.textContent).toContain('Upraviť miesto');
  });

  it('should switch venue links to admin route context', async () => {
    await createComponent('/admin/venues/9');

    expect((component as any).pageContext().backLink).toBe('/admin/venues');
    expect((component as any).pageContext().editBaseLink).toBe('/admin/venues');
    expect(venuesApi.show).toHaveBeenCalledWith(venueItem.id);
  });
});
