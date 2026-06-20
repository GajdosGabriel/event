import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export type ToastType = 'success' | 'error' | 'info';

export interface ToastMessage {
  id: number;
  message: string;
  type: ToastType;
  durationMs: number;
  state: 'active' | 'leaving';
  remainingMs: number;
  paused: boolean;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  private readonly toastsSubject = new BehaviorSubject<ToastMessage[]>([]);
  private nextId = 1;
  private readonly timers = new Map<number, { timeoutId: ReturnType<typeof setTimeout>; startedAt: number; remainingMs: number }>();
  private maxToasts: number | null = 3;
  private readonly defaultDurations: Record<ToastType, number> = {
    success: 2500,
    info: 3000,
    error: 4500
  };

  readonly toasts$ = this.toastsSubject.asObservable();

  show(message: string, type: ToastType = 'info', durationMs?: number): void {
    const current = this.toastsSubject.value;
    if (this.maxToasts !== null && current.length >= this.maxToasts) {
      const oldest = current[0];
      this.removeToast(oldest.id);
    }

    const resolvedDuration = durationMs ?? this.defaultDurations[type] ?? 3000;
    const toast: ToastMessage = {
      id: this.nextId++,
      message,
      type,
      durationMs: resolvedDuration,
      state: 'active',
      remainingMs: resolvedDuration,
      paused: false
    };

    this.toastsSubject.next([...this.toastsSubject.value, toast]);

    this.startTimer(toast.id, resolvedDuration);
  }

  success(message: string, durationMs?: number): void {
    this.show(message, 'success', durationMs);
  }

  error(message: string, durationMs?: number): void {
    this.show(message, 'error', durationMs);
  }

  info(message: string, durationMs?: number): void {
    this.show(message, 'info', durationMs);
  }

  sticky(message: string, type: ToastType = 'info'): void {
    this.show(message, type, 0);
  }

  stickySuccess(message: string): void {
    this.sticky(message, 'success');
  }

  stickyError(message: string): void {
    this.sticky(message, 'error');
  }

  stickyInfo(message: string): void {
    this.sticky(message, 'info');
  }

  setMaxToasts(limit: number | null): void {
    if (limit !== null && limit < 1) {
      this.maxToasts = null;
      return;
    }
    this.maxToasts = limit;
  }

  dismiss(id: number): void {
    const current = this.toastsSubject.value;
    const target = current.find((toast) => toast.id === id);
    if (!target || target.state === 'leaving') {
      return;
    }

    this.clearTimer(id);

    const updated: ToastMessage[] = current.map((toast) =>
      toast.id === id ? { ...toast, state: 'leaving' as const } : toast
    );

    this.toastsSubject.next(updated);

    setTimeout(() => {
      this.removeToast(id);
    }, 200);
  }

  clear(): void {
    this.toastsSubject.next([]);
  }

  pause(id: number): void {
    const entry = this.timers.get(id);
    const current = this.toastsSubject.value;
    const target = current.find((toast) => toast.id === id);

    if (!entry || !target || target.state === 'leaving' || target.paused) {
      return;
    }

    const elapsed = Date.now() - entry.startedAt;
    const remainingMs = Math.max(0, entry.remainingMs - elapsed);

    clearTimeout(entry.timeoutId);
    this.timers.set(id, {
      timeoutId: entry.timeoutId,
      startedAt: entry.startedAt,
      remainingMs
    });

    this.toastsSubject.next(
      current.map((toast) =>
        toast.id === id ? { ...toast, remainingMs, paused: true } : toast
      )
    );
  }

  resume(id: number): void {
    const entry = this.timers.get(id);
    const current = this.toastsSubject.value;
    const target = current.find((toast) => toast.id === id);

    if (!entry || !target || target.state === 'leaving' || !target.paused) {
      return;
    }

    const remainingMs = Math.max(0, entry.remainingMs);
    this.startTimer(id, remainingMs);

    this.toastsSubject.next(
      current.map((toast) =>
        toast.id === id ? { ...toast, remainingMs, paused: false } : toast
      )
    );
  }

  private startTimer(id: number, durationMs: number): void {
    if (durationMs <= 0) {
      return;
    }

    const timeoutId = setTimeout(() => this.dismiss(id), durationMs);
    this.timers.set(id, {
      timeoutId,
      startedAt: Date.now(),
      remainingMs: durationMs
    });

    const current = this.toastsSubject.value;
    this.toastsSubject.next(
      current.map((toast) =>
        toast.id === id ? { ...toast, remainingMs: durationMs, paused: false } : toast
      )
    );
  }

  private clearTimer(id: number): void {
    const entry = this.timers.get(id);
    if (entry) {
      clearTimeout(entry.timeoutId);
      this.timers.delete(id);
    }
  }

  private removeToast(id: number): void {
    this.clearTimer(id);
    this.toastsSubject.next(this.toastsSubject.value.filter((toast) => toast.id !== id));
  }
}
