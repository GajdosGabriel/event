import { Component, computed, input } from '@angular/core';

@Component({
  selector: 'app-completion-indicator',
  templateUrl: './completion-indicator.component.html',
  styleUrl: './completion-indicator.component.css'
})
export class CompletionIndicatorComponent {
  readonly filled = input.required<number>();
  readonly total = input.required<number>();
  readonly label = input<string>('Vyplnenie');

  protected readonly percent = computed(() => {
    const t = this.total();
    if (t <= 0) return 0;
    return Math.min(100, Math.round((this.filled() / t) * 100));
  });
}
