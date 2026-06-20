import { Component, input } from '@angular/core';

@Component({
  selector: 'app-page-head',
  templateUrl: './page-head.component.html',
  styleUrl: './page-head.component.css'
})
export class PageHeadComponent {
  readonly title = input.required<string>();
  readonly subtitle = input('');
}
