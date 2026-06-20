import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NgIf } from '@angular/common';
import { AuthService } from '../../../core/services/auth.service';
import { ToastService } from '../../../core/services/toast.service';
import { ErrorBannerComponent } from '../../../shared/components/error-banner/error-banner.component';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-register-page',
  imports: [RouterLink, ReactiveFormsModule, NgIf, ErrorBannerComponent],
  templateUrl: './register.page.html',
  styleUrl: './register.page.css'
})
export class RegisterPage {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly pageMeta = inject(PageMetaService);

  readonly form = this.fb.nonNullable.group({
    display_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    passwordConfirmation: ['', Validators.required]
  });

  isSubmitting = false;
  errorMessage: string | null = null;

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Registrácia',
      description: 'Vytvorenie nového používateľského účtu.'
    });

    const email = this.route.snapshot.queryParamMap.get('email');
    if (email) {
      this.form.patchValue({ email });
    }
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { display_name, email, password, passwordConfirmation } = this.form.getRawValue();

    if (password !== passwordConfirmation) {
      this.errorMessage = 'Heslá sa nezhodujú.';
      this.toast.error(this.errorMessage);
      return;
    }

    this.isSubmitting = true;
    this.errorMessage = null;
    this.form.disable({ emitEvent: false });

    this.auth
      .register({
        display_name,
        email,
        password,
        password_confirmation: passwordConfirmation
      })
      .subscribe({
        next: () => {
          this.isSubmitting = false;
          this.form.enable({ emitEvent: false });
          this.toast.success('Registrácia prebehla úspešne. Skontroluj si email.');
          void this.router.navigate(['/verify-email'], { queryParams: { email } });
        },
        error: (error) => {
          this.isSubmitting = false;
          this.form.enable({ emitEvent: false });
          this.errorMessage = resolveApiErrorMessage(error, 'Registrácia zlyhala. Skús to ešte raz.');
          this.toast.error(this.errorMessage);
        }
      });
  }

  registerWithGoogle(): void {
    this.auth.startSocialLogin('google');
  }

  registerWithFacebook(): void {
    this.auth.startSocialLogin('facebook');
  }
}
