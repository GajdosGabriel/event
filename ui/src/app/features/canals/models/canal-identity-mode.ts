export const CANAL_IDENTITY_MODES = ['personal', 'organization', 'pseudonymous'] as const;

export type CanalIdentityMode = (typeof CANAL_IDENTITY_MODES)[number];

type CanalIdentityModeOption = {
  value: CanalIdentityMode;
  label: string;
};

const CANAL_IDENTITY_MODE_LABELS: Record<CanalIdentityMode, string> = {
  personal: 'Osobný',
  organization: 'Firemný',
  pseudonymous: 'Krycie meno'
};

const CANAL_IDENTITY_MODE_VALUES = new Set<string>(CANAL_IDENTITY_MODES);

export const CANAL_IDENTITY_MODE_OPTIONS: readonly CanalIdentityModeOption[] = CANAL_IDENTITY_MODES.map(
  (value) => ({
    value,
    label: CANAL_IDENTITY_MODE_LABELS[value]
  })
);

export function sanitizeCanalIdentityMode(value: unknown): CanalIdentityMode {
  if (typeof value === 'string' && CANAL_IDENTITY_MODE_VALUES.has(value)) {
    return value as CanalIdentityMode;
  }

  return 'personal';
}

export function getCanalIdentityModeLabel(mode: unknown): string {
  return CANAL_IDENTITY_MODE_LABELS[sanitizeCanalIdentityMode(mode)];
}
