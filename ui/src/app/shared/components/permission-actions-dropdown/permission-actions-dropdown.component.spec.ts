import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { beforeEach, describe, expect, it } from 'vitest';
import { PermissionActionsDropdownComponent } from './permission-actions-dropdown.component';

describe('PermissionActionsDropdownComponent', () => {
  let component: PermissionActionsDropdownComponent;
  let fixture: ComponentFixture<PermissionActionsDropdownComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PermissionActionsDropdownComponent],
      providers: [provideRouter([])]
    }).compileComponents();

    fixture = TestBed.createComponent(PermissionActionsDropdownComponent);
    component = fixture.componentInstance;
  });

  it('should hide status action when publish permission is false', () => {
    fixture.componentRef.setInput('permissions', {
      view: true,
      update: true,
      publish: false,
      delete: false,
      restore: false
    });
    fixture.componentRef.setInput('showStatus', true);
    fixture.detectChanges();

    const items = (component as any).items();

    expect(items.some((item: { id: string }) => item.id === 'status')).toBe(false);
  });

  it('should show status action when publish permission is true', () => {
    fixture.componentRef.setInput('permissions', {
      view: true,
      update: true,
      publish: true,
      delete: false,
      restore: false
    });
    fixture.componentRef.setInput('showStatus', true);
    fixture.detectChanges();

    const items = (component as any).items();

    expect(items.some((item: { id: string }) => item.id === 'status')).toBe(true);
  });
});
