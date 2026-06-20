import { CommonModule } from '@angular/common';
import {
  AfterViewInit,
  Component,
  ElementRef,
  forwardRef,
  input,
  ViewChild,
} from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

type EditorCommand =
  | 'bold'
  | 'italic'
  | 'underline'
  | 'insertUnorderedList'
  | 'insertOrderedList'
  | 'formatBlock'
  | 'createLink'
  | 'unlink';

@Component({
  selector: 'app-rich-text-editor',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './rich-text-editor.component.html',
  styleUrl: './rich-text-editor.component.css',
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => RichTextEditorComponent),
      multi: true,
    },
  ],
})
export class RichTextEditorComponent implements ControlValueAccessor, AfterViewInit {
  readonly placeholder = input('Začni písať popis podujatia...');

  @ViewChild('editor', { static: true })
  private readonly editorRef?: ElementRef<HTMLDivElement>;

  protected disabled = false;
  protected editorValue = '';
  protected isFocused = false;

  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  ngAfterViewInit(): void {
    this.renderEditorValue();
  }

  writeValue(value: string | null): void {
    this.editorValue = this.normalizeIncomingValue(value ?? '');
    this.renderEditorValue();
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
  }

  protected onInput(): void {
    const editor = this.editorRef?.nativeElement;
    if (!editor) {
      return;
    }

    const normalized = this.normalizeEditorHtml(editor.innerHTML);
    this.editorValue = normalized;
    this.onChange(normalized);
  }

  protected onFocus(): void {
    this.isFocused = true;
  }

  protected onBlur(): void {
    this.isFocused = false;
    this.onTouched();
    this.onInput();
  }

  protected exec(command: EditorCommand, value?: string): void {
    if (this.disabled) {
      return;
    }

    const editor = this.editorRef?.nativeElement;
    if (!editor) {
      return;
    }

    editor.focus();

    if (command === 'createLink') {
      const link = window.prompt('Zadaj URL adresu', 'https://');
      if (!link) {
        return;
      }

      document.execCommand(command, false, link.trim());
      this.onInput();
      return;
    }

    document.execCommand(command, false, value);
    this.onInput();
  }

  protected isEmpty(): boolean {
    return !this.editorValue.trim();
  }

  private renderEditorValue(): void {
    const editor = this.editorRef?.nativeElement;
    if (!editor) {
      return;
    }

    editor.innerHTML = this.editorValue;
  }

  private normalizeIncomingValue(value: string): string {
    const trimmed = value.trim();
    if (!trimmed) {
      return '';
    }

    if (/<[a-z][\s\S]*>/i.test(trimmed)) {
      return this.normalizeEditorHtml(trimmed);
    }

    return this.escapeHtml(trimmed).replace(/\n/g, '<br>');
  }

  private normalizeEditorHtml(value: string): string {
    return value
      .replace(/<(div|p)><br><\/\1>/gi, '')
      .replace(/&nbsp;/gi, ' ')
      .trim();
  }

  private escapeHtml(value: string): string {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
}
