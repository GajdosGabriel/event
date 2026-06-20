import { Component, computed, input, output } from '@angular/core';
import { DropdownComponent, DropdownItem } from '../dropdown/dropdown.component';
import { ModelPermissions } from '../../models/model-permissions';

export type PermissionDropdownAction = 'status' | 'delete' | 'restore' | 'switch' | 'archive';

export interface PermissionDropdownActionEvent {
  action: PermissionDropdownAction;
}

@Component({
  selector: 'app-permission-actions-dropdown',
  imports: [DropdownComponent],
  templateUrl: './permission-actions-dropdown.component.html',
  styleUrl: './permission-actions-dropdown.component.css'
})
export class PermissionActionsDropdownComponent {
  readonly permissions = input<ModelPermissions | null>(null);
  readonly isDeleted = input(false);
  readonly isPublished = input(false);
  readonly isArchived = input(false);

  readonly detailLink = input<string | null>(null);
  readonly editLink = input<string | null>(null);

  readonly showView = input(true);
  readonly showStatus = input(true);
  readonly showSwitch = input(false);
  readonly hideDelete = input(false);

  readonly triggerLabel = input('⋯');

  readonly actionSelect = output<PermissionDropdownActionEvent>();

  protected readonly canView = computed(() => this.permissions()?.view ?? true);
  protected readonly canUpdate = computed(() => this.permissions()?.update ?? true);
  protected readonly canPublish = computed(() => this.permissions()?.publish ?? false);
  protected readonly canDelete = computed(() => this.permissions()?.delete ?? true);
  protected readonly canArchive = computed(() => this.permissions()?.archive ?? false);
  protected readonly canRestore = computed(() => this.permissions()?.restore ?? true);

  protected readonly items = computed<DropdownItem[]>(() => {
    const items: DropdownItem[] = [];
    const isDeleted = this.isDeleted();

    if (this.showView() && this.detailLink()) {
      if (this.canView()) {
        items.push({ id: 'view', label: 'Detail', path: this.detailLink()! });
      } else {
        items.push({ id: 'view', label: 'Detail', disabled: true });
      }
    }

    if (!isDeleted && this.canUpdate() && this.editLink()) {
      items.push({ id: 'edit', label: 'Upraviť', path: this.editLink()! });
    }

    if (!isDeleted && this.showStatus() && this.canUpdate() && this.canPublish()) {
      items.push({
        id: 'status',
        label: this.isPublished() ? 'Nepublikovať' : 'Publikovať'
      });
    }

    if (this.showSwitch() && !isDeleted) {
      items.push({ id: 'switch', label: 'Prepnúť kanál' });
    }

    if (!isDeleted && !this.isArchived() && this.canArchive()) {
      items.push({ id: 'archive', label: 'Archivovať' });
    }

    if (isDeleted && this.canRestore()) {
      items.push({ id: 'restore', label: 'Obnoviť' });
    }

    if (!isDeleted && !this.hideDelete() && this.canDelete()) {
      items.push({ id: 'delete', label: 'Zmazať' });
    }

    return items;
  });

  protected readonly disabled = computed(() => this.items().length === 0);

  protected onItemSelect(item: DropdownItem): void {
    if (item.path) {
      return;
    }

    if (item.id === 'status' || item.id === 'delete' || item.id === 'restore' || item.id === 'switch' || item.id === 'archive') {
      this.actionSelect.emit({ action: item.id });
    }
  }
}
