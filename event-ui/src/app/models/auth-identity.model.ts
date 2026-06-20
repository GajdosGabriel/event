export interface AuthCanalContextActive {
  id: number;
  name: string;
}

export interface AuthCanalContext {
  active: AuthCanalContextActive | null;
  is_owner: boolean;
}

export interface AuthIdentity {
  id: number;
  canal_id: number | null;
  canal: string;
  roles?: string[];
  canal_context?: AuthCanalContext | null;
  permissions?: Record<string, boolean>;
  [key: string]: unknown;
}
