import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-paginator',
  templateUrl: './paginator.component.html',
  styleUrl: './paginator.component.css'
})
export class PaginatorComponent {
  readonly currentPage = input.required<number>();
  readonly totalPages = input.required<number>();
  readonly pageChange = output<number>();

  protected get pages(): number[] {
    return Array.from({ length: this.totalPages() }, (_, index) => index + 1);
  }

  protected prev(): void {
    const prevPage = this.currentPage() - 1;
    if (prevPage >= 1) {
      this.pageChange.emit(prevPage);
    }
  }

  protected next(): void {
    const nextPage = this.currentPage() + 1;
    if (nextPage <= this.totalPages()) {
      this.pageChange.emit(nextPage);
    }
  }

  protected goTo(page: number): void {
    if (page >= 1 && page <= this.totalPages() && page !== this.currentPage()) {
      this.pageChange.emit(page);
    }
  }
}
