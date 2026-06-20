import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthUiService } from '../../../core/services/auth-ui.service';
import { AuthService } from '../../../core/services/auth.service';
import { CanalsApiService } from '../../../features/canals/services/canals-api.service';
import { LayoutShellComponent } from './layout-shell.component';

describe('LayoutShellComponent', () => {
  let fixture: ComponentFixture<LayoutShellComponent>;
  let component: LayoutShellComponent;
  let router: Router;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LayoutShellComponent],
      providers: [
        provideRouter([]),
        {
          provide: AuthUiService,
          useValue: {
            isLoggingOut$: of(false),
            logoutAndNavigate: vi.fn().mockReturnValue(of(void 0))
          }
        },
        {
          provide: AuthService,
          useValue: {
            currentIdentity$: of(null),
            fetchCurrentIdentity: vi.fn().mockReturnValue(of(null))
          }
        },
        {
          provide: CanalsApiService,
          useValue: {
            show: vi.fn().mockReturnValue(of({ canal: null, name: null }))
          }
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(LayoutShellComponent);
    component = fixture.componentInstance;
    component.brandText = 'event-ui';
    component.asideLinks = [{ label: 'Akcie', path: '/dashboard/events' }];
    router = TestBed.inject(Router);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  it('adds a reload query param when clicking the active aside link', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    vi.spyOn(Date, 'now').mockReturnValue(123456);
    (component as any).currentPath = () => '/dashboard/events';

    (component as any).onAsideLinkClick(new MouseEvent('click', { button: 0 }), component.asideLinks[0]);

    expect(navigateSpy).toHaveBeenCalledWith(['/dashboard/events'], {
      queryParamsHandling: 'merge',
      queryParams: { asideNav: 123456 }
    });
  });

  it('navigates normally when clicking a different aside section', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    (component as any).currentPath = () => '/dashboard/canals';

    (component as any).onAsideLinkClick(new MouseEvent('click', { button: 0 }), component.asideLinks[0]);

    expect(navigateSpy).toHaveBeenCalledWith(['/dashboard/events'], undefined);
  });
});
