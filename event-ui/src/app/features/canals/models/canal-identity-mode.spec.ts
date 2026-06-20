import {
  CANAL_IDENTITY_MODE_OPTIONS,
  getCanalIdentityModeLabel,
  sanitizeCanalIdentityMode
} from './canal-identity-mode';

describe('canal-identity-mode helpers', () => {
  it('should expose all identity mode options', () => {
    expect(CANAL_IDENTITY_MODE_OPTIONS.map((option) => option.value)).toEqual([
      'personal',
      'organization',
      'pseudonymous'
    ]);
  });

  it('should map known value to readable label', () => {
    expect(getCanalIdentityModeLabel('personal')).toBe('Osobný');
    expect(getCanalIdentityModeLabel('organization')).toBe('Firemný');
    expect(getCanalIdentityModeLabel('pseudonymous')).toBe('Krycie meno');
  });

  it('should fallback to personal for null, missing or unknown values', () => {
    expect(sanitizeCanalIdentityMode(null)).toBe('personal');
    expect(sanitizeCanalIdentityMode(undefined)).toBe('personal');
    expect(sanitizeCanalIdentityMode('unknown')).toBe('personal');
    expect(getCanalIdentityModeLabel('unknown')).toBe('Osobný');
  });
});
