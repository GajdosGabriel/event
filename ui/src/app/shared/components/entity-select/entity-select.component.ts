import {
  Component,
  computed,
  ElementRef,
  effect,
  forwardRef,
  inject,
  input,
  signal
} from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

export type EntitySelectValue = string | number;

export interface EntitySelectOption {
  id: EntitySelectValue;
  name: string;
}

@Component({
  selector: 'app-entity-select',
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => EntitySelectComponent),
      multi: true
    }
  ],
  templateUrl: './entity-select.component.html',
  styleUrl: './entity-select.component.css'
})
export class EntitySelectComponent implements ControlValueAccessor {
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly instanceId = `entity-select-${Math.random().toString(36).slice(2, 10)}`;

  readonly label = input.required<string>();
  readonly options = input<EntitySelectOption[]>([]);
  readonly placeholder = input('Vyhľadaj podľa názvu');
  readonly required = input(false);
  readonly invalid = input(false);
  readonly errorText = input('');

  protected readonly query = signal('');
  protected readonly isOpen = signal(false);
  protected readonly selectedValue = signal<EntitySelectValue | null>(null);
  protected readonly activeIndex = signal(-1);
  protected readonly selectedOption = computed(() => {
    const selectedValue = this.selectedValue();
    if (selectedValue === null) {
      return null;
    }

    return this.options().find((option) => option.id === selectedValue) ?? null;
  });
  protected readonly searchTerm = computed(() => {
    const term = this.query().trim().toLowerCase();
    const selectedOption = this.selectedOption();
    const selectedLabel = selectedOption?.name.trim().toLowerCase() ?? '';

    if (!term || (selectedOption !== null && term === selectedLabel)) {
      return '';
    }

    return term;
  });
  protected readonly filteredOptions = computed(() => {
    const term = this.searchTerm();
    const options = this.options();

    if (!term) {
      return options.slice(0, 40);
    }

    return options
      .filter((option) => option.name.toLowerCase().includes(term))
      .slice(0, 40);
  });
  protected readonly inputId = `${this.instanceId}-input`;
  protected readonly labelId = `${this.instanceId}-label`;
  protected readonly listboxId = `${this.instanceId}-listbox`;
  protected readonly errorId = `${this.instanceId}-error`;

  protected disabled = false;

  private onChange: (value: EntitySelectValue | null) => void = () => {};
  private onTouched: () => void = () => {};

  constructor() {
    effect(() => {
      if (!this.isOpen()) {
        this.activeIndex.set(-1);
        return;
      }

      const options = this.filteredOptions();
      if (options.length === 0) {
        this.activeIndex.set(-1);
        return;
      }

      const currentIndex = this.activeIndex();
      if (currentIndex >= 0 && currentIndex < options.length) {
        return;
      }

      const selectedIndex = options.findIndex((option) => option.id === this.selectedValue());
      this.activeIndex.set(selectedIndex >= 0 ? selectedIndex : 0);
    });

    effect(() => {
      if (!this.isOpen()) {
        return;
      }

      const activeIndex = this.activeIndex();
      const options = this.filteredOptions();
      if (activeIndex < 0 || activeIndex >= options.length) {
        return;
      }

      setTimeout(() => {
        const hostElement = this.host.nativeElement as HTMLElement;
        const activeOption = hostElement.querySelector<HTMLElement>(
          `[data-option-index="${activeIndex}"]`
        );
        activeOption?.scrollIntoView({ block: 'nearest' });
      });
    });

    effect(() => {
      if (this.isOpen()) {
        return;
      }

      const selectedValue = this.selectedValue();
      if (selectedValue === null) {
        this.query.set('');
        return;
      }

      const selectedOption = this.options().find((option) => option.id === selectedValue);
      this.query.set(selectedOption?.name ?? '');
    });
  }

  writeValue(value: EntitySelectValue | null): void {
    if (this.isSelectableValue(value)) {
      this.selectedValue.set(value);
      return;
    }

    this.selectedValue.set(null);
  }

  registerOnChange(fn: (value: EntitySelectValue | null) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
  }

  protected onInput(value: string): void {
    this.query.set(value);
    this.isOpen.set(true);

    if (this.selectedValue() !== null) {
      this.setValue(null);
    }

    const options = this.filteredOptions();
    this.activeIndex.set(options.length > 0 ? 0 : -1);
  }

  protected openDropdown(): void {
    if (!this.disabled) {
      this.isOpen.set(true);
    }
  }

  protected closeDropdown(): void {
    setTimeout(() => {
      this.closeDropdownImmediately();
    }, 120);
  }

  protected onKeydown(event: KeyboardEvent): void {
    if (this.disabled) {
      return;
    }

    const options = this.filteredOptions();

    switch (event.key) {
      case 'ArrowDown': {
        event.preventDefault();
        if (!this.isOpen()) {
          this.openDropdown();
        }
        this.moveActiveIndex(1, options.length);
        break;
      }
      case 'ArrowUp': {
        event.preventDefault();
        if (!this.isOpen()) {
          this.openDropdown();
        }
        this.moveActiveIndex(-1, options.length);
        break;
      }
      case 'Enter': {
        if (!this.isOpen()) {
          return;
        }

        const activeOption = options[this.activeIndex()] ?? null;
        if (activeOption !== null) {
          event.preventDefault();
          this.selectOption(activeOption);
        }
        break;
      }
      case 'Tab': {
        if (!this.isOpen()) {
          return;
        }

        const activeOption = options[this.activeIndex()] ?? null;
        if (activeOption !== null) {
          this.selectOption(activeOption);
        } else {
          this.closeDropdownImmediately();
        }
        break;
      }
      case 'Escape': {
        if (!this.isOpen()) {
          return;
        }

        event.preventDefault();
        this.closeDropdownImmediately();
        break;
      }
      case 'Home': {
        if (!this.isOpen() || options.length === 0) {
          return;
        }

        event.preventDefault();
        this.activeIndex.set(0);
        break;
      }
      case 'End': {
        if (!this.isOpen() || options.length === 0) {
          return;
        }

        event.preventDefault();
        this.activeIndex.set(options.length - 1);
        break;
      }
    }
  }

  protected setActiveIndex(index: number): void {
    this.activeIndex.set(index);
  }

  protected getOptionId(index: number): string {
    return `${this.instanceId}-option-${index}`;
  }

  protected getActiveDescendant(): string | null {
    const activeIndex = this.activeIndex();
    const options = this.filteredOptions();

    if (!this.isOpen() || activeIndex < 0 || activeIndex >= options.length) {
      return null;
    }

    return this.getOptionId(activeIndex);
  }

  protected getOptionLabelParts(label: string): {
    before: string;
    match: string;
    after: string;
  } {
    const term = this.searchTerm();
    if (!term) {
      return {
        before: label,
        match: '',
        after: ''
      };
    }

    const normalizedLabel = label.toLowerCase();
    const matchIndex = normalizedLabel.indexOf(term);
    if (matchIndex === -1) {
      return {
        before: label,
        match: '',
        after: ''
      };
    }

    return {
      before: label.slice(0, matchIndex),
      match: label.slice(matchIndex, matchIndex + term.length),
      after: label.slice(matchIndex + term.length)
    };
  }

  protected selectOption(option: EntitySelectOption): void {
    if (this.disabled) {
      return;
    }

    this.query.set(option.name);
    this.setValue(option.id);
    this.isOpen.set(false);
    this.onTouched();
  }

  protected clearSelection(): void {
    if (this.disabled || this.required()) {
      return;
    }

    this.query.set('');
    this.setValue(null);
    this.isOpen.set(false);
    this.onTouched();
  }

  private closeDropdownImmediately(): void {
    this.isOpen.set(false);
    this.onTouched();
  }

  private moveActiveIndex(step: number, total: number): void {
    if (total === 0) {
      this.activeIndex.set(-1);
      return;
    }

    const currentIndex = this.activeIndex();
    if (currentIndex < 0 || currentIndex >= total) {
      this.activeIndex.set(step > 0 ? 0 : total - 1);
      return;
    }

    this.activeIndex.set((currentIndex + step + total) % total);
  }

  private setValue(value: EntitySelectValue | null): void {
    if (this.selectedValue() === value) {
      return;
    }

    this.selectedValue.set(value);
    this.onChange(value);
  }

  private isSelectableValue(value: EntitySelectValue | null): value is EntitySelectValue {
    if (typeof value === 'number') {
      return Number.isFinite(value);
    }

    return typeof value === 'string' && value.length > 0;
  }
}
