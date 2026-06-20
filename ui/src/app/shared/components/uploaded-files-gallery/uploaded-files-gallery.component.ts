import { Component, HostListener, Input } from '@angular/core';
import { UploadedFileItem } from '../../models/uploaded-file.model';
import { TruncatePipe } from '../../pipes/truncate.pipe';

@Component({
  selector: 'app-uploaded-files-gallery',
  standalone: true,
  imports: [TruncatePipe],
  templateUrl: './uploaded-files-gallery.component.html',
  styleUrl: './uploaded-files-gallery.component.css'
})
export class UploadedFilesGalleryComponent {
  @Input() title = 'Galéria';
  @Input() emptyText = 'Pre tento záznam nie sú dostupné žiadne obrázky.';
  @Input() files: UploadedFileItem[] = [];

  protected lightboxIndex: number | null = null;

  protected get allFiles(): UploadedFileItem[] {
    return this.files.filter((file) => Boolean(file.url));
  }

  protected get imageFiles(): UploadedFileItem[] {
    return this.allFiles.filter((file) => this.isImage(file));
  }

  protected get activeImage(): UploadedFileItem | null {
    if (this.lightboxIndex === null) {
      return null;
    }

    const images = this.imageFiles;
    if (this.lightboxIndex < 0 || this.lightboxIndex >= images.length) {
      return null;
    }

    return images[this.lightboxIndex];
  }

  protected openLightboxForFile(file: UploadedFileItem): void {
    const images = this.imageFiles;
    const idx = images.findIndex(
      (f) => f === file || (f.id !== undefined && f.id === file.id) || (f.url && f.url === file.url)
    );
    if (idx >= 0) {
      this.openLightbox(idx);
    }
  }

  protected openLightbox(index: number): void {
    if (index < 0 || index >= this.imageFiles.length) {
      return;
    }

    this.lightboxIndex = index;
  }

  protected closeLightbox(): void {
    this.lightboxIndex = null;
  }

  protected showPreviousImage(): void {
    if (this.lightboxIndex === null || this.imageFiles.length === 0) {
      return;
    }

    this.lightboxIndex = (this.lightboxIndex - 1 + this.imageFiles.length) % this.imageFiles.length;
  }

  protected showNextImage(): void {
    if (this.lightboxIndex === null || this.imageFiles.length === 0) {
      return;
    }

    this.lightboxIndex = (this.lightboxIndex + 1) % this.imageFiles.length;
  }

  protected onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.closeLightbox();
    }
  }

  @HostListener('document:keydown', ['$event'])
  protected onDocumentKeydown(event: KeyboardEvent): void {
    if (this.lightboxIndex === null) {
      return;
    }

    if (event.key === 'Escape') {
      this.closeLightbox();
      return;
    }

    if (event.key === 'ArrowLeft') {
      this.showPreviousImage();
      return;
    }

    if (event.key === 'ArrowRight') {
      this.showNextImage();
    }
  }

  protected isImage(file: UploadedFileItem): boolean {
    return this.getFileCategory(file) === 'image';
  }

  protected getFileIconVariant(file: UploadedFileItem): 'pdf' | 'word' | 'excel' | 'default' {
    const category = this.getFileCategory(file);
    if (category === 'pdf' || category === 'word' || category === 'excel') {
      return category;
    }
    return 'default';
  }

  protected getFileIconLabel(file: UploadedFileItem): string {
    switch (this.getFileCategory(file)) {
      case 'pdf': return 'PDF';
      case 'word': return 'DOC';
      case 'excel': return 'XLS';
      case 'presentation': return 'PPT';
      case 'archive': return 'ZIP';
      case 'text': return 'TXT';
      case 'audio': return 'AUDIO';
      case 'video': return 'VIDEO';
      default: return 'FILE';
    }
  }

  private getFileCategory(file: UploadedFileItem): string {
    const mimeType = (file.mimeType ?? '').toLowerCase();
    const type = (file.type ?? '').toLowerCase();
    const effectiveMime = mimeType || (type.includes('/') ? type : '');
    const extension = this.getFileExtension(file);

    if (effectiveMime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'].includes(extension)) {
      return 'image';
    }
    if (effectiveMime === 'application/pdf' || extension === 'pdf') return 'pdf';
    if (
      ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(effectiveMime) ||
      ['word', 'document', 'doc'].includes(type) ||
      ['doc', 'docx'].includes(extension)
    ) return 'word';
    if (
      ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'].includes(effectiveMime) ||
      ['excel', 'spreadsheet', 'sheet', 'csv', 'xls'].includes(type) ||
      ['xls', 'xlsx', 'csv'].includes(extension)
    ) return 'excel';
    if (
      ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'].includes(effectiveMime) ||
      ['presentation', 'powerpoint', 'ppt'].includes(type) ||
      ['ppt', 'pptx'].includes(extension)
    ) return 'presentation';
    if (effectiveMime.startsWith('text/') || ['txt', 'md', 'json', 'xml', 'html'].includes(extension)) return 'text';
    if (effectiveMime.startsWith('audio/')) return 'audio';
    if (effectiveMime.startsWith('video/')) return 'video';
    // Fall back to type field only when extension/mimeType gave no result
    if (type === 'image' || type === 'img') return 'image';
    if (type === 'pdf') return 'pdf';
    return 'file';
  }

  private getFileExtension(file: UploadedFileItem): string {
    const fromName = this.extractExtension(file.name);
    if (fromName) return fromName;
    if (!file.url) return '';
    const sanitizedUrl = file.url.split('?')[0]?.split('#')[0] ?? '';
    const lastSegment = sanitizedUrl.split('/').pop() ?? '';
    return this.extractExtension(lastSegment);
  }

  private extractExtension(value: string): string {
    const normalizedValue = value.toLowerCase();
    const extension = normalizedValue.split('.').pop();
    return extension && extension !== normalizedValue ? extension : '';
  }
}
