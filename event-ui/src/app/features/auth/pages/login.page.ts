import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NgIf } from '@angular/common';
import { AuthService } from '../../../core/services/auth.service';
import { ToastService } from '../../../core/services/toast.service';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { getHttpErrorCode, getHttpStatus, resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-login-page',
  imports: [RouterLink, ReactiveFormsModule, NgIf, ErrorBannerComponent],
  templateUrl: './login.page.html',
  styleUrl: './login.page.css'
})
export class LoginPage {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly pageMeta = inject(PageMetaService);

  readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  isSubmitting = false;
  errorMessage: string | null = null;

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Prihlásenie',
      description: 'Prihlásenie do používateľského účtu.'
    });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;
    this.form.disable({ emitEvent: false });

    const payload = this.form.getRawValue();

    this.auth.login(payload).subscribe({
      next: () => {
        this.isSubmitting = false;
        this.form.enable({ emitEvent: false });
        this.toast.success('Prihlásenie prebehlo úspešne.');
        const redirectTarget = this.route.snapshot.queryParamMap.get('redirect') ?? '/dashboard';
        void this.router.navigateByUrl(redirectTarget);
      },
      error: (error) => {
        this.isSubmitting = false;
        this.form.enable({ emitEvent: false });
        const status = getHttpStatus(error);
        const code = getHttpErrorCode(error);

        if (status === 409 && code === 'email_not_verified') {
          void this.router.navigate(['/verify-email'], { queryParams: { email: payload.email } });
          return;
        }

        if (status === 401) {
          this.errorMessage = 'Nesprávny email alebo heslo.';
        } else {
          this.errorMessage = resolveApiErrorMessage(error, 'Prihlásenie zlyhalo. Skús to ešte raz.');
        }

        this.toast.error(this.errorMessage);
      }
    });
  }

  loginWithGoogle(): void {
    this.auth.startSocialLogin('google');
  }

  loginWithFacebook(): void {
    this.auth.startSocialLogin('facebook');
  }
}
