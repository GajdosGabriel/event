import { NgFor, NgIf } from '@angular/common';
import { Component, ElementRef, HostListener, Input, inject, output } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface DropdownItem {
  id: string;
  label: string;
  path?: string;
  exact?: boolean;
  disabled?: boolean;
}

@Component({
  selector: 'app-dropdown',
  imports: [NgFor, NgIf, RouterLink],
  templateUrl: './dropdown.component.html',
  styleUrl: './dropdown.component.css'
})
export class DropdownComponent {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  @Input() triggerLabel = 'Menu';
  @Input() triggerIcon = true;
  @Input() items: DropdownItem[] = [];
  @Input() disabled = false;

  readonly itemSelect = output<DropdownItem>();

  protected isOpen = false;

  protected toggle(): void {
    if (this.disabled) {
      return;
    }

    this.isOpen = !this.isOpen;
  }

  protected onItemClick(item: DropdownItem): void {
    if (item.disabled) {
      return;
    }

    this.itemSelect.emit(item);
    this.close();
  }

  protected hasLink(item: DropdownItem): boolean {
    return Boolean(item.path);
  }

  protected close(): void {
    this.isOpen = false;
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (!this.isOpen) {
      return;
    }

    const target = event.target;
    if (!(target instanceof Node)) {
      return;
    }

    if (!this.host.nativeElement.contains(target)) {
      this.close();
    }
  }

  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.close();
  }
}
