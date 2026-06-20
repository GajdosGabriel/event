import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize } from 'rxjs';
import { IndexShellComponent } from '../../../shared/components/index-shell/index-shell.component';
import { ToastService } from '../../../core/services/toast.service';
import { resolveApiErrorMessage } from '../../../shared/utils/api-error.utils';
import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { FileableType, FilesApiService } from '../../../shared/services/files-api.service';
import { PageMetaService } from '../../../shared/services/page-meta.service';

@Component({
  selector: 'app-admin-files-page',
  imports: [IndexShellComponent, FormsModule],
  templateUrl: './admin-files.page.html',
  styleUrl: './admin-files.page.css'
})
export class AdminFilesPage {
  private readonly filesApi = inject(FilesApiService);
  private readonly toast = inject(ToastService);
  private readonly pageMeta = inject(PageMetaService);

  protected readonly loadingList = signal(false);
  protected readonly loadingDetail = signal(false);
  protected readonly actionBusy = signal(false);
  protected readonly errorMessage = signal('');

  protected readonly files = signal<UploadedFileItem[]>([]);
  protected readonly selectedFile = signal<UploadedFileItem | null>(null);
  protected readonly currentPage = signal(1);
  protected readonly pageSize = 8;
  protected readonly filterQuery = signal('');
  protected readonly filterType = signal<'all' | 'image' | 'file'>('all');
  protected readonly filterPrimary = signal<'all' | 'yes' | 'no'>('all');
  protected readonly filteredFiles = computed(() => {
    const query = this.filterQuery().trim().toLowerCase();
    const typeFilter = this.filterType();
    const primaryFilter = this.filterPrimary();

    return this.files().filter((file) => {
      const normalizedName = (file.name ?? '').toLowerCase();
      const normalizedId = String(file.id ?? '').toLowerCase();
      const normalizedType = (file.type ?? '').toLowerCase();

      const matchesQuery = !query || normalizedName.includes(query) || normalizedId.includes(query);
      const matchesType = typeFilter === 'all' || normalizedType === typeFilter;
      const matchesPrimary =
        primaryFilter === 'all' ||
        (primaryFilter === 'yes' ? Boolean(file.isPrimary) : !Boolean(file.isPrimary));

      return matchesQuery && matchesType && matchesPrimary;
    });
  });
  protected readonly totalPages = computed(() => {
    const total = this.filteredFiles().length;
    return Math.max(1, Math.ceil(total / this.pageSize));
  });
  protected readonly pagedFiles = computed(() => {
    const page = Math.min(this.currentPage(), this.totalPages());
    const start = (page - 1) * this.pageSize;
    return this.filteredFiles().slice(start, start + this.pageSize);
  });

  protected fileId: number | null = null;
  protected searchFileableType: FileableType = 'event';
  protected searchFileableId: number | null = null;

  constructor() {
    this.pageMeta.setPageMeta({
      title: 'Admin súbory',
      description: 'Vyhľadávanie a správa nahraných súborov.'
    });
  }

  protected loadFilesByEntity(): void {
    if (!this.searchFileableId || this.searchFileableId <= 0) {
      this.errorMessage.set('Zadajte validné fileable_id.');
      return;
    }

    this.loadingList.set(true);
    this.errorMessage.set('');

    this.filesApi
      .listDashboard({
        fileable_type: this.searchFileableType,
        fileable_id: this.searchFileableId
      })
      .pipe(finalize(() => this.loadingList.set(false)))
      .subscribe({
        next: (items) => {
          this.files.set(items);
          this.selectedFile.set(null);
          this.currentPage.set(1);
          if (items.length === 0) {
            this.toast.info('Pre zvolenú entitu sa nenašli žiadne súbory.');
          }
        },
        error: (error) => {
          this.files.set([]);
          this.errorMessage.set(resolveApiErrorMessage(error, 'Nepodarilo sa načítať súbory.'));
        }
      });
  }

  protected showFileDetail(): void {
    if (!this.fileId || this.fileId <= 0) {
      this.errorMessage.set('Zadajte validné file ID.');
      return;
    }

    this.loadingDetail.set(true);
    this.errorMessage.set('');

    this.filesApi
      .showDashboard(this.fileId)
      .pipe(finalize(() => this.loadingDetail.set(false)))
      .subscribe({
        next: (file) => {
          this.selectedFile.set(file);
        },
        error: (error) => {
          this.selectedFile.set(null);
          this.errorMessage.set(resolveApiErrorMessage(error, 'Nepodarilo sa načítať detail súboru.'));
        }
      });
  }

  protected useFileFromList(file: UploadedFileItem): void {
    const id = typeof file.id === 'number' ? file.id : Number(file.id);
    if (!Number.isFinite(id)) {
      this.errorMessage.set('Súbor nemá validné numerické ID.');
      return;
    }

    this.fileId = id;
    this.selectedFile.set(file);
  }

  protected setFilterQuery(value: string): void {
    this.filterQuery.set(value);
    this.currentPage.set(1);
  }

  protected setFilterType(value: 'all' | 'image' | 'file'): void {
    this.filterType.set(value);
    this.currentPage.set(1);
  }

  protected setFilterPrimary(value: 'all' | 'yes' | 'no'): void {
    this.filterPrimary.set(value);
    this.currentPage.set(1);
  }

  protected goToPrevPage(): void {
    this.currentPage.update((value) => Math.max(1, value - 1));
  }

  protected goToNextPage(): void {
    this.currentPage.update((value) => Math.min(this.totalPages(), value + 1));
  }

  protected deleteViaAdmin(): void {
    if (!this.fileId || this.fileId <= 0) {
      this.errorMessage.set('Zadajte validné file ID.');
      return;
    }

    if (!window.confirm(`Naozaj chcete zmazať file #${this.fileId}?`)) {
      return;
    }

    this.actionBusy.set(true);
    this.errorMessage.set('');

    this.filesApi
      .deleteAdmin(this.fileId)
      .pipe(finalize(() => this.actionBusy.set(false)))
      .subscribe({
        next: () => {
          this.toast.success('Súbor bol zmazaný cez admin endpoint.');
          this.removeFromLocalList(this.fileId as number);
        },
        error: (error) => {
          this.errorMessage.set(resolveApiErrorMessage(error, 'Admin delete súboru zlyhal.'));
        }
      });
  }

  protected restoreViaAdmin(): void {
    if (!this.fileId || this.fileId <= 0) {
      this.errorMessage.set('Zadajte validné file ID.');
      return;
    }

    this.actionBusy.set(true);
    this.errorMessage.set('');

    this.filesApi
      .restoreAdmin(this.fileId)
      .pipe(finalize(() => this.actionBusy.set(false)))
      .subscribe({
        next: () => {
          this.toast.success('Súbor bol obnovený cez admin endpoint.');
          this.showFileDetail();
        },
        error: (error) => {
          this.errorMessage.set(resolveApiErrorMessage(error, 'Admin restore súboru zlyhal.'));
        }
      });
  }

  private removeFromLocalList(fileId: number): void {
    this.files.set(this.files().filter((item) => Number(item.id) !== fileId));
    if (this.selectedFile() && Number(this.selectedFile()?.id) === fileId) {
      this.selectedFile.set(null);
    }
  }
}
