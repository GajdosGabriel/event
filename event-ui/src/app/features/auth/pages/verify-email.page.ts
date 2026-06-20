import { NgIf } from '@angular/common';
import { Component, HostListener, OnDestroy, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { ToastService } from '../../../core/services/toast.service';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { getHttpErrorCode, getHttpStatus, resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-verify-email-page',
  imports: [RouterLink, ReactiveFormsModule, NgIf, ErrorBannerComponent],
  templateUrl: './verify-email.page.html',
  styleUrl: './verify-email.page.css'
})
export class VerifyEmailPage implements OnDestroy {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly toast = inject(ToastService);
  private readonly route = inject(ActivatedRoute);
  private readonly pageMeta = inject(PageMetaService);

  readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]]
  });

  isSubmitting = false;
  errorMessage: string | null = null;
  cooldownSeconds = 0;
  showInfoBanner = true;
  isClosingInfoBanner = false;
  isOpeningInfoBanner = false;
  private cooldownTimer: ReturnType<typeof setInterval> | null = null;
  private bannerCloseTimer: ReturnType<typeof setTimeout> | null = null;

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Overenie emailu',
      description: 'Opätovné odoslanie alebo kontrola overovacieho emailu.'
    });

    const email = this.route.snapshot.queryParamMap.get('email');
    if (email) {
      this.form.patchValue({ email });
    }

    if (typeof window !== 'undefined') {
      const dismissed = window.localStorage.getItem('verifyEmailBannerDismissed');
      if (dismissed === '1') {
        this.showInfoBanner = false;
      } else {
        this.isOpeningInfoBanner = true;
        setTimeout(() => {
          this.isOpeningInfoBanner = false;
        }, 10);
      }
    }
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    if (this.cooldownSeconds > 0) {
      return;
    }

    const { email } = this.form.getRawValue();
    this.isSubmitting = true;
    this.errorMessage = null;
    this.form.disable({ emitEvent: false });

    this.auth.resendVerificationEmail(email).subscribe({
      next: () => {
        this.isSubmitting = false;
        this.form.enable({ emitEvent: false });
        this.toast.success('Overovací email bol znovu odoslaný.');
        this.startCooldown(60);
      },
      error: (error) => {
        this.isSubmitting = false;
        this.form.enable({ emitEvent: false });
        const status = getHttpStatus(error);
        if (status === 429) {
          this.startCooldown(60);
        }
        this.errorMessage = resolveResendErrorMessage(error);
        this.toast.error(this.errorMessage);
      }
    });
  }

  ngOnDestroy(): void {
    this.clearCooldown();
    this.clearBannerCloseTimer();
  }

  dismissInfoBanner(): void {
    if (!this.showInfoBanner || this.isClosingInfoBanner) {
      return;
    }

    this.isClosingInfoBanner = true;
    this.bannerCloseTimer = setTimeout(() => {
      this.showInfoBanner = false;
      this.isClosingInfoBanner = false;
      this.bannerCloseTimer = null;
      if (typeof window !== 'undefined') {
        window.localStorage.setItem('verifyEmailBannerDismissed', '1');
      }
    }, 220);
  }

  @HostListener('document:keydown.escape')
  handleEscape(): void {
    this.dismissInfoBanner();
  }

  private startCooldown(seconds: number): void {
    this.clearCooldown();
    this.cooldownSeconds = seconds;
    this.cooldownTimer = setInterval(() => {
      this.cooldownSeconds -= 1;
      if (this.cooldownSeconds <= 0) {
        this.clearCooldown();
      }
    }, 1000);
  }

  private clearCooldown(): void {
    if (this.cooldownTimer) {
      clearInterval(this.cooldownTimer);
      this.cooldownTimer = null;
    }
    if (this.cooldownSeconds < 0) {
      this.cooldownSeconds = 0;
    }
  }

  private clearBannerCloseTimer(): void {
    if (this.bannerCloseTimer) {
      clearTimeout(this.bannerCloseTimer);
      this.bannerCloseTimer = null;
    }
  }
}

function resolveResendErrorMessage(error: unknown): string {
  const status = getHttpStatus(error);
  const code = getHttpErrorCode(error);

  if (status === 404 && code === 'pending_not_found') {
    return 'K tomuto emailu nemáme žiadnu čakajúcu registráciu.';
  }

  if (status === 409 && code === 'already_verified') {
    return 'Tento email už bol overený. Skús sa prihlásiť.';
  }

  if (status === 409 && code === 'user_exists') {
    return 'Účet už existuje. Ak sa nevieš prihlásiť, skús obnoviť heslo.';
  }

  if (status === 429) {
    return 'Príliš veľa pokusov. Skús to znova o 60 s.';
  }

  return resolveApiErrorMessage(error, 'Nepodarilo sa odoslať overovací email. Skús to ešte raz.');
}
