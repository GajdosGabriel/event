import { DatePipe, NgClass } from '@angular/common';
import { Component, computed, inject, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ToastService } from '../../../core/services/toast.service';
import { ModelPermissions } from '../../models/model-permissions';
import {
  MODEL_STATUS,
  ModelStatus,
  getModelStatusLabel,
  isPublishedModelStatus
} from '../../models/model-status';
import { TruncatePipe } from '../../pipes/truncate.pipe';
import { StripHtmlPipe } from '../../pipes/strip-html.pipe';
import { IndexRowComponent } from '../index-row/index-row.component';
import {
  PermissionActionsDropdownComponent,
  PermissionDropdownActionEvent
} from '../permission-actions-dropdown/permission-actions-dropdown.component';

export type IndexItemActionType = 'delete' | 'status' | 'edit' | 'switch' | 'archive';

function normalizeNullableDateTime(value: string | null | undefined): string | null {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = value.trim().toLowerCase();
  if (!normalized || normalized === 'null' || normalized === 'undefined') {
    return null;
  }

  return value;
}

export interface IndexItemAction {
  action: IndexItemActionType;
  id: number | null;
}

@Component({
  selector: 'app-index-item',
  imports: [
    RouterLink,
    NgClass,
    DatePipe,
    TruncatePipe,
    StripHtmlPipe,
    IndexRowComponent,
    PermissionActionsDropdownComponent
  ],
  templateUrl: './index-item.component.html',
  styleUrl: './index-item.component.css'
})
export class IndexItemComponent {
  readonly itemId = input<number | null>(null);
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly descriptionFallback = input('Bez popisu položky.');
  readonly status = input<ModelStatus>(MODEL_STATUS.Draft);
  readonly metaItems = input<readonly string[] | null | undefined>(undefined);
  readonly formatFirstMetaItemAsDate = input(false);
  readonly thumbImage = input('');
  readonly link = input.required<string>();
  readonly editLink = input<string | null>(null);
  readonly deletedAt = input<string | null, string | null | undefined>(null, {
    transform: normalizeNullableDateTime
  });
  readonly permissions = input<ModelPermissions | null>(null);
  readonly showViewAction = input(true);
  readonly showStatusAction = input(false);
  readonly showSwitchAction = input(false);
  readonly switchActionLabel = input('Prepnúť kanál');
  readonly rowClass = input<string | string[] | Record<string, boolean> | null>(null);
  readonly isHighlighted = input(false);
  readonly highlightLabel = input<string | null>(null);
  readonly hideDeleteAction = input(false);
  readonly detailAriaLabel = input('Zobraziť detail položky');
  readonly deletedItemMessage = input('Položka je zmazaná. Obnovte ju pre zobrazenie detailu.');

  readonly actionClicked = output<IndexItemAction>();

  private readonly toastService = inject(ToastService);

  protected readonly published = computed(() => isPublishedModelStatus(this.status()));
  protected readonly archived = computed(() => this.status() === MODEL_STATUS.Archived);

  protected readonly statusLabel = computed(() => getModelStatusLabel(this.status()));

  protected readonly resolvedMetaItems = computed(() => {
    const configuredItems = this.metaItems();
    if (configuredItems === undefined || configuredItems === null) {
      return [];
    }

    return configuredItems.filter((item): item is string => Boolean(item && item.trim()));
  });

  protected readonly isDeleted = computed(() => Boolean(this.deletedAt()));
  protected readonly canView = computed(() => this.permissions()?.view ?? true);

  protected isValidDateMetaItem(value: string): boolean {
    return Number.isFinite(new Date(value).getTime());
  }

  protected onActionClick(action: IndexItemActionType, event: MouseEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.actionClicked.emit({ action, id: this.itemId() });
  }

  protected onDropdownAction(event: PermissionDropdownActionEvent): void {
    if (event.action === 'status' || event.action === 'switch' || event.action === 'delete' || event.action === 'restore' || event.action === 'archive') {
      const action: IndexItemActionType = event.action === 'restore' ? 'delete' : event.action;
      this.actionClicked.emit({ action, id: this.itemId() });
    }
  }

  protected onDeletedItemClick(event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    this.toastService.show(this.deletedItemMessage(), 'info');
  }
}
