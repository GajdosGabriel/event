import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FilterComponent } from '../filter/filter.component';
import { DEFAULT_FILTER_PER_PAGE } from '../../models/filter-params.model';

@Component({
  selector: 'app-index-shell',
  templateUrl: './index-shell.component.html',
  styleUrl: './index-shell.component.css',
  imports: [CommonModule, FilterComponent]
})
export class IndexShellComponent {
  readonly title = input.required<string>();
  readonly subtitle = input('');
  readonly defaultPerPage = input(DEFAULT_FILTER_PER_PAGE);
  readonly perPageLoading = input(false);
}
