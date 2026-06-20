export interface AccessRole {
  id?: number | string;
  name: string;
  label?: string | null;
  permissions?: string[];
}

export interface AccessPermission {
  id?: number | string;
  name: string;
  label?: string | null;
  description?: string | null;
}

export interface UserRolesPayload {
  roles: string[];
}
