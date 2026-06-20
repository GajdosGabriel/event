import { NgFor, NgIf } from '@angular/common';
import { Component, HostListener, inject } from '@angular/core';
import { AsyncPipe } from '@angular/common';
import { ToastService } from '../../../core/services/toast.service';

@Component({
  selector: 'app-toast-container',
  imports: [NgFor, NgIf, AsyncPipe],
  templateUrl: './toast-container.component.html',
  styleUrl: './toast-container.component.css'
})
export class ToastContainerComponent {
  private readonly toastService = inject(ToastService);

  readonly toasts$ = this.toastService.toasts$;

  dismiss(id: number): void {
    this.toastService.dismiss(id);
  }

  clear(): void {
    this.toastService.clear();
  }

  @HostListener('window:keydown.escape')
  onEscape(): void {
    this.clear();
  }

  dismissIfClickable(id: number, event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (target && target.closest('.toast-close')) {
      return;
    }
    this.dismiss(id);
  }

  pause(id: number): void {
    this.toastService.pause(id);
  }

  resume(id: number): void {
    this.toastService.resume(id);
  }
}
