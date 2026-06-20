import { Component, DestroyRef, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { finalize } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { UploadedFileItem } from '../../models/uploaded-file.model';
import { FilesApiService } from '../../services/files-api.service';
import { TruncatePipe } from '../../pipes/truncate.pipe';

export interface FilesUploadSelection {
  files: File[];
  fileType: string | null;
  fileDisk: string;
  makePrimaryFile: boolean;
}

type FileCategory =
  | 'image'
  | 'pdf'
  | 'word'
  | 'excel'
  | 'presentation'
  | 'archive'
  | 'text'
  | 'audio'
  | 'video'
  | 'file';

@Component({
  selector: 'app-uploaded-files-list',
  imports: [DecimalPipe, TruncatePipe],
  templateUrl: './uploaded-files-list.component.html',
  styleUrl: './uploaded-files-list.component.css'
})
export class UploadedFilesListComponent {
  private readonly filesApi = inject(FilesApiService);
  private readonly destroyRef = inject(DestroyRef);

  @Input() title = 'Súbory';
  @Input() emptyText = 'Zatiaľ nie sú dostupné žiadne súbory.';
  @Input() files: UploadedFileItem[] = [];
  @Input() showRemoveAction = false;
  @Input() removeLabel = 'Odstrániť';
  @Input() showUploadInput = false;
  @Input() maxFileSizeKb = 10240;
  @Input() defaultFileType = 'image';
  @Input() savePendingLabel = 'Uložiť';
  @Input() savePendingDisabled = false;
  @Input() canSavePending = true;
  @Input() pendingSaveUnavailableText = 'Súbory sa uložia až po vytvorení záznamu.';
  @Input() pendingResetKey = 0;
  @Input() prefilledPendingFiles: File[] = [];

  @Output() readonly fileRemoved = new EventEmitter<UploadedFileItem>();
  @Output() readonly filePrimarySet = new EventEmitter<UploadedFileItem>();
  @Output() readonly selectionChange = new EventEmitter<FilesUploadSelection>();
  @Output() readonly validationError = new EventEmitter<string | null>();
  @Output() readonly savePendingRequested = new EventEmitter<void>();

  protected readonly removingIds = signal<Array<number | string>>([]);
  protected readonly settingPrimaryIds = signal<Array<number | string>>([]);
  protected readonly pendingFiles = signal<File[]>([]);
  protected readonly validationErrorMsg = signal<string | null>(null);

  private readonly blobUrlCache = new Map<File, string>();

  protected isImage(file: UploadedFileItem | File): boolean {
    return this.getFileCategory(file) === 'image';
  }

  protected getFilePreviewClass(file: UploadedFileItem | File): string {
    return `preview-${this.getFileCategory(file)}`;
  }

  protected getFileIconVariant(file: UploadedFileItem | File): 'pdf' | 'word' | 'excel' | 'default' {
    const category = this.getFileCategory(file);

    if (category === 'pdf' || category === 'word' || category === 'excel') {
      return category;
    }

    return 'default';
  }

  protected getFileIconLabel(file: UploadedFileItem | File): string {
    switch (this.getFileCategory(file)) {
      case 'pdf':
        return 'PDF';
      case 'word':
        return 'DOC';
      case 'excel':
        return 'XLS';
      case 'presentation':
        return 'PPT';
      case 'archive':
        return 'ZIP';
      case 'text':
        return 'TXT';
      case 'audio':
        return 'AUDIO';
      case 'video':
        return 'VIDEO';
      default:
        return 'FILE';
    }
  }

  protected getFileTypeLabel(file: UploadedFileItem): string {
    return file.type || file.mimeType || this.getFileIconLabel(file);
  }

  protected formatSize(sizeBytes: number | null | undefined): string {
    if (sizeBytes === null || sizeBytes === undefined || !Number.isFinite(sizeBytes)) {
      return '';
    }

    if (sizeBytes < 1024) {
      return `${sizeBytes} B`;
    }

    const kb = sizeBytes / 1024;
    if (kb < 1024) {
      return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(2)} MB`;
  }

  protected isRemoving(file: UploadedFileItem): boolean {
    if (file.id === undefined || file.id === null) {
      return false;
    }

    return this.removingIds().includes(file.id);
  }

  protected canSetPrimary(file: UploadedFileItem): boolean {
    return this.isImage(file) && !file.isPrimary && file.id !== undefined && file.id !== null;
  }

  protected isSettingPrimary(file: UploadedFileItem): boolean {
    if (file.id === undefined || file.id === null) {
      return false;
    }

    return this.settingPrimaryIds().includes(file.id);
  }

  protected onSetPrimaryClicked(file: UploadedFileItem): void {
    if (!this.canSetPrimary(file) || this.isSettingPrimary(file) || file.id === undefined || file.id === null) {
      return;
    }

    this.settingPrimaryIds.set([...this.settingPrimaryIds(), file.id]);

    this.filesApi
      .updateDashboard(file.id, { is_primary: true })
      .pipe(
        finalize(() => {
          this.settingPrimaryIds.set(this.settingPrimaryIds().filter((id) => id !== file.id));
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: (updatedFile) => {
          this.filePrimarySet.emit(updatedFile);
        },
        error: () => {
          alert('Nastavenie primárneho obrázka zlyhalo. Skús to znova.');
        }
      });
  }

  protected onRemoveClicked(file: UploadedFileItem): void {
    const confirmed = window.confirm(`Naozaj chceš odstrániť súbor "${file.name}"?`);
    if (!confirmed) {
      return;
    }

    if (file.id === undefined || file.id === null) {
      alert('Tento súbor nemá ID z API, preto ho nie je možné zmazať cez endpoint.');
      return;
    }

    this.removingIds.set([...this.removingIds(), file.id]);

    this.filesApi
      .deleteDashboard(file.id)
      .pipe(
        finalize(() => {
          this.removingIds.set(this.removingIds().filter((id) => id !== file.id));
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe({
        next: () => {
          this.fileRemoved.emit(file);
        },
        error: () => {
          alert('Odstranenie súboru zlyhalo. Skús to znova.');
        }
      });
  }

  protected onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const nextFiles = Array.from(input?.files ?? []);

    if (nextFiles.length === 0) {
      return;
    }

    const maxBytes = this.maxFileSizeKb * 1024;
    const oversized = nextFiles.filter((file) => file.size > maxBytes);
    const validFiles = nextFiles.filter((file) => file.size <= maxBytes);

    const mergedFiles = [...this.pendingFiles()];
    for (const file of validFiles) {
      if (!mergedFiles.some((current) => this.isSameFile(current, file))) {
        mergedFiles.push(file);
      }
    }

    this.pendingFiles.set(mergedFiles);

    if (oversized.length > 0) {
      const names = oversized.map((file) => file.name).join(', ');
      this.validationErrorMsg.set(`Súbor presahuje limit ${this.maxFileSizeKb / 1024} MB: ${names}`);
    } else {
      this.validationErrorMsg.set(null);
    }

    this.emitSelection();

    if (input) {
      input.value = '';
    }
  }

  protected removePendingFile(index: number): void {
    const removed = this.pendingFiles()[index];
    this.pendingFiles.set(this.pendingFiles().filter((_, i) => i !== index));
    this.revokeBlobUrl(removed);
    this.validationErrorMsg.set(null);
    this.emitSelection();
  }

  protected onSavePendingClicked(): void {
    if (!this.canSavePending) {
      return;
    }

    this.savePendingRequested.emit();
  }

  ngOnChanges(changes: SimpleChanges): void {
    const prefilledChange = changes['prefilledPendingFiles'];
    if (prefilledChange) {
      this.syncPendingFilesFromInput(this.prefilledPendingFiles);
    }

    const resetKeyChange = changes['pendingResetKey'];
    if (resetKeyChange && !resetKeyChange.firstChange) {
      this.clearPendingState();
    }
  }

  private emitSelection(): void {
    this.selectionChange.emit({
      files: [...this.pendingFiles()],
      fileType: this.defaultFileType,
      fileDisk: 'public',
      makePrimaryFile: false
    });
    this.validationError.emit(this.validationErrorMsg());
  }

  private clearPendingState(): void {
    this.blobUrlCache.forEach((url) => URL.revokeObjectURL(url));
    this.blobUrlCache.clear();
    this.pendingFiles.set([]);
    this.validationErrorMsg.set(null);
    this.emitSelection();
  }

  private syncPendingFilesFromInput(files: File[]): void {
    const previous = this.pendingFiles();
    const next = [...files];

    for (const file of previous) {
      const stillPresent = next.some((candidate) => this.isSameFile(candidate, file));
      if (!stillPresent) {
        this.revokeBlobUrl(file);
      }
    }

    this.pendingFiles.set(next);
  }

  private revokeBlobUrl(file: File): void {
    const url = this.blobUrlCache.get(file);
    if (url) {
      URL.revokeObjectURL(url);
      this.blobUrlCache.delete(file);
    }
  }

  protected getImageUrl(file: File): string {
    let url = this.blobUrlCache.get(file);
    if (!url) {
      url = URL.createObjectURL(file);
      this.blobUrlCache.set(file, url);
    }
    return url;
  }

  private getFileCategory(file: UploadedFileItem | File): FileCategory {
    const mimeType = this.getMimeType(file);
    const extension = this.getFileExtension(file);
    const type = file instanceof File ? '' : file.type?.toLowerCase() ?? '';

    if (mimeType.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'].includes(extension)) {
      return 'image';
    }

    if (mimeType === 'application/pdf' || type === 'pdf' || extension === 'pdf') {
      return 'pdf';
    }

    if (
      [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
      ].includes(mimeType) ||
      ['word', 'document', 'doc'].includes(type) ||
      ['doc', 'docx'].includes(extension)
    ) {
      return 'word';
    }

    if (
      [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv'
      ].includes(mimeType) ||
      ['excel', 'spreadsheet', 'sheet', 'csv', 'xls'].includes(type) ||
      ['xls', 'xlsx', 'csv'].includes(extension)
    ) {
      return 'excel';
    }

    if (
      [
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
      ].includes(mimeType) ||
      ['presentation', 'powerpoint', 'ppt'].includes(type) ||
      ['ppt', 'pptx'].includes(extension)
    ) {
      return 'presentation';
    }

    if (
      [
        'application/zip',
        'application/x-zip-compressed',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        'application/gzip'
      ].includes(mimeType) ||
      ['archive', 'zip', 'compressed'].includes(type) ||
      ['zip', 'rar', '7z', 'gz', 'tar'].includes(extension)
    ) {
      return 'archive';
    }

    if (mimeType.startsWith('text/') || ['txt', 'md', 'json', 'xml', 'html'].includes(extension)) {
      return 'text';
    }

    if (mimeType.startsWith('audio/')) {
      return 'audio';
    }

    if (mimeType.startsWith('video/')) {
      return 'video';
    }

    // Fall back to type field only when extension/mimeType gave no result
    if (type === 'image' || type === 'img') {
      return 'image';
    }

    return 'file';
  }

  private getMimeType(file: UploadedFileItem | File): string {
    if (file instanceof File) {
      return file.type.toLowerCase();
    }

    const mimeType = file.mimeType?.toLowerCase() ?? '';
    const normalizedType = file.type?.toLowerCase() ?? '';

    if (mimeType) {
      return mimeType;
    }

    return normalizedType.includes('/') ? normalizedType : '';
  }

  private getFileExtension(file: UploadedFileItem | File): string {
    const fromName = this.extractExtension(file.name);

    if (fromName) {
      return fromName;
    }

    if (file instanceof File || !file.url) {
      return '';
    }

    const sanitizedUrl = file.url.split('?')[0]?.split('#')[0] ?? '';
    const lastSegment = sanitizedUrl.split('/').pop() ?? '';
    return this.extractExtension(lastSegment);
  }

  private extractExtension(value: string): string {
    const normalizedValue = value.toLowerCase();
    const extension = normalizedValue.split('.').pop();
    return extension && extension !== normalizedValue ? extension : '';
  }

  private isSameFile(first: File, second: File): boolean {
    return (
      first.name === second.name &&
      first.size === second.size &&
      first.lastModified === second.lastModified
    );
  }
}
