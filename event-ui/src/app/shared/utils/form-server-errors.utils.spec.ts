import { FormControl, FormGroup, Validators } from '@angular/forms';
import { applyServerValidationErrors, clearServerValidationErrors, ServerFieldMap } from './form-server-errors.utils';

type TestControls = 'name' | 'start_date' | 'start_time';

function buildForm(): FormGroup {
  return new FormGroup({
    name: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    start_date: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    start_time: new FormControl('', { nonNullable: true, validators: [Validators.required] })
  });
}

describe('form-server-errors utils', () => {
  it('should map API field errors to matching form controls', () => {
    const form = buildForm();
    const fieldMap: ServerFieldMap<TestControls> = {
      name: ['name'],
      start_at: ['start_date', 'start_time']
    };

    const result = applyServerValidationErrors({
      form,
      payload: {
        message: 'Validation failed',
        errors: {
          start_at: ['Pole start at musi byt datum v buducnosti.']
        }
      },
      fieldMap,
      fallbackMessage: 'Fallback error'
    });

    expect(result.mappedAny).toBe(true);
    expect(result.summary).toEqual(['Validation failed']);
    expect(form.controls['start_date'].hasError('server')).toBe(true);
    expect(form.controls['start_time'].hasError('server')).toBe(true);
    expect(form.controls['start_date'].touched).toBe(true);
  });

  it('should normalize API field names before mapping', () => {
    const form = buildForm();
    const fieldMap: ServerFieldMap<TestControls> = {
      start_at: ['start_date', 'start_time']
    };

    const result = applyServerValidationErrors({
      form,
      payload: {
        errors: {
          'start at': ['Pole start at musi byt datum v buducnosti.']
        }
      },
      fieldMap,
      fallbackMessage: 'Fallback error'
    });

    expect(result.mappedAny).toBe(true);
    expect(result.summary).toEqual(['Fallback error']);
    expect(form.controls['start_date'].getError('server')).toBe('Pole start at musi byt datum v buducnosti.');
    expect(form.controls['start_time'].getError('server')).toBe('Pole start at musi byt datum v buducnosti.');
  });

  it('should add unmapped field error message to summary', () => {
    const form = buildForm();

    const result = applyServerValidationErrors({
      form,
      payload: {
        errors: {
          unknown_field: ['Unknown field error']
        }
      },
      fieldMap: {},
      fallbackMessage: 'Fallback error'
    });

    expect(result.mappedAny).toBe(false);
    expect(result.summary).toEqual(['Unknown field error']);
  });

  it('should use fallback summary when payload has no usable messages', () => {
    const form = buildForm();

    const result = applyServerValidationErrors({
      form,
      payload: {
        errors: {
          start_at: [null]
        }
      },
      fieldMap: {
        start_at: ['start_date', 'start_time']
      },
      fallbackMessage: 'Fallback error'
    });

    expect(result.mappedAny).toBe(false);
    expect(result.summary).toEqual(['Fallback error']);
  });

  it('should clear only server errors and keep client-side validation errors', () => {
    const form = buildForm();
    const nameControl = form.controls['name'];

    nameControl.markAsTouched();
    nameControl.updateValueAndValidity();
    nameControl.setErrors({
      ...(nameControl.errors ?? {}),
      server: 'Server error message'
    });

    clearServerValidationErrors(form);

    expect(nameControl.getError('server')).toBeUndefined();
    expect(nameControl.hasError('required')).toBe(true);
  });
});
