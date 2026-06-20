import { Component, OnInit, inject, signal } from '@angular/core';
import { timeout } from 'rxjs';
import { getCanalIdentityModeLabel } from '../../models/canal-identity-mode';
import { CanalItem } from '../../models/canal.model';
import { CanalsApiService } from '../../services/canals-api.service';
import { PaginatorComponent } from '../../../../shared/components/paginator/paginator.component';
import { ErrorBannerComponent } from '../../../../shared/components/error-banner/error-banner.component';

@Component({
  selector: 'app-canal',
  imports: [PaginatorComponent, ErrorBannerComponent],
  templateUrl: './canal.component.html',
  styleUrl: './canal.component.css',
})
export class CanalComponent implements OnInit {
  private readonly canalsApi = inject(CanalsApiService);
  protected readonly getCanalIdentityModeLabel = getCanalIdentityModeLabel;

  protected readonly canals = signal<CanalItem[]>([]);
  protected readonly loading = signal(true);
  protected readonly pageSize = 6;
  protected readonly currentPage = signal(1);
  protected readonly totalPages = signal(1);
  protected readonly errorMessage = signal('');

  ngOnInit(): void {
    this.loadPage(this.currentPage());
  }

  protected onPageChange(page: number): void {
    this.currentPage.set(page);
    this.loadPage(page);
  }

  private loadPage(page: number): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.canalsApi
      .index(page, this.pageSize)
      .pipe(timeout(10000))
      .subscribe({
        next: (result) => {
          this.canals.set(result.items);
          this.currentPage.set(result.currentPage);
          this.totalPages.set(result.totalPages);
          this.loading.set(false);
        },
        error: () => {
          this.canals.set([]);
          this.totalPages.set(1);
          this.loading.set(false);
          this.errorMessage.set('Nepodarilo sa nacitat canals z API (timeout/chyba spojenia).');
        },
      });
  }
}
