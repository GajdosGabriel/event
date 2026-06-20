import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-show-shell',
  imports: [RouterLink],
  templateUrl: './show-shell.component.html',
})
export class ShowShellComponent {
  readonly loading = input.required<boolean>();
  readonly found = input.required<boolean>();
  readonly loadingText = input<string>('Načítavam...');
  readonly notFoundText = input<string>('Záznam sa nenašiel');
  readonly backLink = input.required<string>();
  readonly backLinkText = input<string>('Späť');
}
