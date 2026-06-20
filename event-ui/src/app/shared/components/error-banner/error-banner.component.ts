import { Component, computed, input } from '@angular/core';

@Component({
  selector: 'app-error-banner',
  templateUrl: './error-banner.component.html',
  styleUrl: './error-banner.component.css'
})
export class ErrorBannerComponent {
  readonly message = input<string | null | undefined>('');
  readonly messages = input<string[]>([]);

  readonly displayMessages = computed<string[]>(() => {
    const items: string[] = [];

    for (const value of this.messages() ?? []) {
      if (typeof value === 'string' && value.trim()) {
        items.push(value.trim());
      }
    }

    const single = this.message();
    if (typeof single === 'string' && single.trim()) {
      items.push(single.trim());
    }

    return [...new Set(items)];
  });
}
