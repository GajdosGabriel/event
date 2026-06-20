import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { VenueIndexPage } from './venue-index.page';
import { VenuesApiService } from '../services/venues-api.service';
import { VenueItem } from '../models/venue.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';

describe('VenueIndexPage', () => {
  let component: VenueIndexPage;
  let fixture: ComponentFixture<VenueIndexPage>;
  let venuesApi: {
    index: ReturnType<typeof vi.fn>;
    updateStatus: ReturnType<typeof vi.fn>;
    togglePublishedStatus: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
    restore: ReturnType<typeof vi.fn>;
  };

  const venueItem: VenueItem = {
    id: 5,
    canalId: 1,
    villageId: 1,
    name: 'Venue',
    street: 'Main',
    postcode: '90001',
    slug: 'venue',
    body: 'Body',
    website: null,
    email: null,
    phone: null,
    country: 'Slovakia',
    latitude: null,
    longitude: null,
    capacity: null,
    openingHours: null,
    imageUrl: '',
    category: null,
    status: MODEL_STATUS.Draft,
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
    venuesApi = {
      index: vi.fn().mockReturnValue(
        of({
          items: [],
          currentPage: 1,
          perPage: 6,
          totalPages: 1,
          total: 0,
          permissions: {
            create: true
          }
        })
      ),
      updateStatus: vi.fn().mockReturnValue(of({ ...venueItem, status: MODEL_STATUS.Published })),
      togglePublishedStatus: vi.fn().mockReturnValue(of({ ...venueItem, status: MODEL_STATUS.Published })),
      delete: vi.fn().mockReturnValue(of(void 0)),
      restore: vi.fn().mockReturnValue(of(void 0))
    };

    await TestBed.configureTestingModule({
      imports: [VenueIndexPage],
      providers: [
        provideRouter([]),
        {
          provide: VenuesApiService,
          useValue: venuesApi as unknown as VenuesApiService
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(VenueIndexPage);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should toggle draft venue to published', () => {
    (component as any).venues.set([venueItem]);

    (component as any).onVenueAction({ action: 'status', id: venueItem.id }, venueItem);

    expect(venuesApi.togglePublishedStatus).toHaveBeenCalledWith(venueItem);
    expect((component as any).venues()[0].status).toBe(MODEL_STATUS.Published);
  });

  it('should surface API error when venue publish toggle fails', () => {
    venuesApi.togglePublishedStatus.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            error: {
              message: 'Status miesta sa nepodarilo zmeniť.'
            }
          })
      )
    );

    (component as any).venues.set([venueItem]);
    (component as any).onVenueAction({ action: 'status', id: venueItem.id }, venueItem);

    expect((component as any).errorMessage()).toBe('Status miesta sa nepodarilo zmeniť.');
  });

  it('should delete venue after confirmation', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const loadPageSpy = vi.spyOn(component as any, 'loadPage').mockImplementation(() => undefined);

    (component as any).onVenueAction({ action: 'delete', id: venueItem.id }, venueItem);

    expect(venuesApi.delete).toHaveBeenCalledWith(venueItem.id);
    expect(loadPageSpy).toHaveBeenCalledWith((component as any).currentPage(), 6, {});
  });

  it('should not call delete when confirmation is rejected', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    (component as any).onVenueAction({ action: 'delete', id: venueItem.id }, venueItem);

    expect(venuesApi.delete).not.toHaveBeenCalled();
  });

  it('should call restore for soft-deleted venue', () => {
    const deletedVenue = { ...venueItem, deletedAt: '2030-01-01 09:00:00' };
    const loadPageSpy = vi.spyOn(component as any, 'loadPage').mockImplementation(() => undefined);

    (component as any).onVenueAction({ action: 'delete', id: deletedVenue.id }, deletedVenue);

    expect(venuesApi.restore).toHaveBeenCalledWith(deletedVenue.id);
    expect(venuesApi.delete).not.toHaveBeenCalled();
    expect(loadPageSpy).toHaveBeenCalledWith((component as any).currentPage(), 6, {});
  });
});
