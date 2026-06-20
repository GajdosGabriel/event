export interface OrganizationItem {
  id: number;
  name: string;
  slug?: string | null;
  body?: string | null;
  website?: string | null;
  email?: string | null;
  phone?: string | null;
  status?: string | null;
  deletedAt?: string | null;
  createdAt?: string | null;
  updatedAt?: string | null;
  raw?: Record<string, unknown>;
}

export interface OrganizationUpsertPayload {
  name: string;
  slug?: string | null;
  body?: string | null;
  website?: string | null;
  email?: string | null;
  phone?: string | null;
  status?: string | null;
}
