import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { BehaviorSubject, of, throwError } from 'rxjs';
import { HttpErrorResponse } from '@angular/common/http';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CanalIndexPage } from './canal-index.page';
import { CanalsApiService } from '../services/canals-api.service';
import { CanalItem } from '../models/canal.model';
import { MODEL_STATUS } from '../../../shared/models/model-status';
import { AuthService } from '../../../core/services/auth.service';

describe('CanalIndexPage', () => {
  let component: CanalIndexPage;
  let fixture: ComponentFixture<CanalIndexPage>;
  let canalsApi: {
    index: ReturnType<typeof vi.fn>;
    updateStatus: ReturnType<typeof vi.fn>;
    togglePublishedStatus: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
    restore: ReturnType<typeof vi.fn>;
  };
  let authService: {
    currentIdentity$: BehaviorSubject<{ id: number; canal_id: number | null; canal: string } | null>;
    fetchCurrentIdentity: ReturnType<typeof vi.fn>;
    setActiveCanal: ReturnType<typeof vi.fn>;
  };

  const canalItem: CanalItem = {
    id: 7,
    municipalityId: 1,
    venueId: null,
    identityMode: 'personal',
    name: 'Canal',
    slug: 'canal',
    titlePrefix: null,
    titleSuffix: null,
    email: 'canal@example.com',
    emailVerifiedAt: null,
    body: 'Body',
    imageUrl: '',
    publishedAt: null,
    status: MODEL_STATUS.Draft,
    website: null,
    deletedAt: null,
    createdAt: null,
    updatedAt: null,
    canal: 'Canal',
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
      updateStatus: vi.fn().mockReturnValue(
        of({ ...canalItem, status: MODEL_STATUS.Published, publishedAt: '2030-01-01 09:00:00' })
      ),
      togglePublishedStatus: vi.fn().mockReturnValue(
        of({ ...canalItem, status: MODEL_STATUS.Published, publishedAt: '2030-01-01 09:00:00' })
      ),
      delete: vi.fn().mockReturnValue(of(void 0)),
      restore: vi.fn().mockReturnValue(of(void 0))
    };
    authService = {
      currentIdentity$: new BehaviorSubject<{ id: number; canal_id: number | null; canal: string } | null>(null),
      fetchCurrentIdentity: vi.fn().mockReturnValue(of(null)),
      setActiveCanal: vi.fn().mockReturnValue(of(null))
    };

    await TestBed.configureTestingModule({
      imports: [CanalIndexPage],
      providers: [
        provideRouter([]),
        {
          provide: CanalsApiService,
          useValue: canalsApi as unknown as CanalsApiService
        },
        {
          provide: AuthService,
          useValue: authService as unknown as AuthService
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(CanalIndexPage);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should toggle draft canal to published', () => {
    (component as any).canals.set([canalItem]);

    (component as any).onCanalAction({ action: 'status', id: canalItem.id }, canalItem);

    expect(canalsApi.togglePublishedStatus).toHaveBeenCalledWith(canalItem);
    expect((component as any).canals()[0].status).toBe(MODEL_STATUS.Published);
  });

  it('should surface API error when canal publish toggle fails', () => {
    canalsApi.togglePublishedStatus.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            error: {
              message: 'Status kanálu sa nepodarilo zmeniť.'
            }
          })
      )
    );

    (component as any).canals.set([canalItem]);
    (component as any).onCanalAction({ action: 'status', id: canalItem.id }, canalItem);

    expect((component as any).errorMessage()).toBe('Status kanálu sa nepodarilo zmeniť.');
  });

  it('should send canal id when switching active canal', () => {
    (component as any).onCanalAction({ action: 'switch', id: canalItem.id }, canalItem);

    expect(authService.setActiveCanal).toHaveBeenCalledWith(canalItem.id);
  });

  it('should delete canal after confirmation', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const loadPageSpy = vi.spyOn(component as any, 'loadPage').mockImplementation(() => undefined);

    (component as any).onCanalAction({ action: 'delete', id: canalItem.id }, canalItem);

    expect(canalsApi.delete).toHaveBeenCalledWith(canalItem.id);
    expect(loadPageSpy).toHaveBeenCalledWith((component as any).currentPage(), 6, {});
  });

  it('should not call delete when confirmation is rejected', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    (component as any).onCanalAction({ action: 'delete', id: canalItem.id }, canalItem);

    expect(canalsApi.delete).not.toHaveBeenCalled();
  });

  it('should call restore for soft-deleted canal', () => {
    const deletedCanal = { ...canalItem, deletedAt: '2030-01-01 09:00:00' };
    const loadPageSpy = vi.spyOn(component as any, 'loadPage').mockImplementation(() => undefined);

    (component as any).onCanalAction({ action: 'delete', id: deletedCanal.id }, deletedCanal);

    expect(canalsApi.restore).toHaveBeenCalledWith(deletedCanal.id);
    expect(canalsApi.delete).not.toHaveBeenCalled();
    expect(loadPageSpy).toHaveBeenCalledWith((component as any).currentPage(), 6, {});
  });

  it('should surface API error when switching active canal fails', () => {
    authService.setActiveCanal.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            error: {
              message: 'Aktívny kanál sa nepodarilo prepnúť.'
            }
          })
      )
    );

    (component as any).onCanalAction({ action: 'switch', id: canalItem.id }, canalItem);

    expect((component as any).errorMessage()).toBe('Aktívny kanál sa nepodarilo prepnúť.');
  });

  it('should highlight the active canal row', () => {
    authService.currentIdentity$.next({ id: 1, canal_id: canalItem.id, canal: canalItem.canal });
    (component as any).canals.set([canalItem, { ...canalItem, id: 8, canal: 'Canal 2', name: 'Canal 2', slug: 'canal-2' }]);

    fixture.detectChanges();

    const row = fixture.debugElement.query(By.css('app-index-item app-index-row'));
    expect(row.nativeElement.classList.contains('active-canal-row')).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Aktívny kanál');
    expect(row.nativeElement.textContent).not.toContain('Prepnúť kanál');
  });

  it('should hide switch button when only one canal exists', () => {
    (component as any).canals.set([canalItem]);

    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).not.toContain('Prepnúť kanál');
  });

  it('should hide delete action for primary canal', () => {
    authService.currentIdentity$.next({ id: 1, canal_id: canalItem.id, canal: canalItem.canal });
    (component as any).canals.set([
      canalItem,
      {
        ...canalItem,
        id: 8,
        canal: 'Canal 2',
        name: 'Canal 2',
        slug: 'canal-2',
        permissions: {
          ...canalItem.permissions,
          delete: true
        }
      }
    ]);

    fixture.detectChanges();

    const canalRows = fixture.debugElement.queryAll(By.css('app-index-item'));
    const primaryTrigger = canalRows[0].nativeElement.querySelector('.dropdown-trigger') as HTMLButtonElement;
    const secondaryTrigger = canalRows[1].nativeElement.querySelector('.dropdown-trigger') as HTMLButtonElement;

    primaryTrigger.click();
    fixture.detectChanges();
    const primaryDangerActionText = canalRows[0].nativeElement.textContent ?? '';

    secondaryTrigger.click();
    fixture.detectChanges();
    const secondaryDangerActionText = canalRows[1].nativeElement.textContent ?? '';

    expect(primaryDangerActionText).not.toContain('Zmazať');
    expect(secondaryDangerActionText).toContain('Zmazať');
  });

  it('should treat the only loaded canal as active when identity canal is unavailable', () => {
    (component as any).canals.set([canalItem]);

    fixture.detectChanges();

    const row = fixture.debugElement.query(By.css('app-index-item app-index-row'));
    expect(row.nativeElement.classList.contains('active-canal-row')).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Aktívny kanál');
    expect(fixture.nativeElement.textContent).not.toContain('Prepnúť kanál');
  });
});
