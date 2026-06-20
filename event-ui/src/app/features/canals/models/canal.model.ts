
import { CanalIdentityMode } from './canal-identity-mode';
import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { AllowedStatusOption, ModelStatus } from '../../../shared/models/model-status';
import { ModelPermissions } from '../../../shared/models/model-permissions';

export interface CanalApiItem {
  id: number;
  municipality_id: number;
  venue_id: number | null;
  identity_mode?: CanalIdentityMode | null;
  name: string;
  slug: string;
  title_prefix?: string | null;
  title_suffix?: string | null;
  email: string;
  email_verified_at?: string | null;
  body: string;
  primary_image?: unknown;
  files?: unknown;
  media?: unknown;
  attachments?: unknown;
  images?: unknown;
  published_at?: string | null;
  status?: string | null;
  website?: string | null;
  registration_source?: string;
  permissions?: Partial<ModelPermissions>;
  allowed_statuses?: unknown[];
  deleted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface CanalItem {
  id: number;
  municipalityId: number;
  venueId: number | null;
  identityMode: CanalIdentityMode;
  name: string;
  slug: string;
  titlePrefix?: string | null;
  titleSuffix?: string | null;
  email: string;
  emailVerifiedAt?: string | null;
  body: string;
  imageUrl: string;
  publishedAt?: string | null;
  status: ModelStatus;
  website?: string | null;
  registrationSource?: string;
  deletedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  canal: string;
  startAt?: string | null;
  endAt?: string | null;
  uploadedFiles: UploadedFileItem[];
  permissions: ModelPermissions;
  allowedStatuses: AllowedStatusOption[];
}
