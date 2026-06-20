import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { AllowedStatusOption, ModelStatus } from '../../../shared/models/model-status';
import { ModelPermissions } from '../../../shared/models/model-permissions';

export interface VenueApiItem {
  id: number;
  canal_id: number;
  village_id: number;
  name: string;
  street: string | null;
  postcode: string | null;
  slug: string;
  body: string;
  website: string | null;
  email: string | null;
  phone: string | null;
  country: string | null;
  latitude: string | null;
  longitude: string | null;
  capacity: number | null;
  opening_hours: unknown[] | string | null;
  category: string | null;
  primary_image?: unknown;
  status: string | null;
  files?: unknown;
  media?: unknown;
  attachments?: unknown;
  images?: unknown;
  permissions?: Partial<ModelPermissions>;
  allowed_statuses?: unknown[];
  deleted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface VenueItem {
  id: number;
  canalId: number;
  villageId: number;
  name: string;
  street: string | null;
  postcode: string | null;
  slug: string;
  body: string;
  website: string | null;
  email: string | null;
  phone: string | null;
  country: string | null;
  latitude: string | null;
  longitude: string | null;
  capacity: number | null;
  openingHours: unknown[] | null;
  imageUrl: string;
  category: string | null;
  status: ModelStatus;
  deletedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  uploadedFiles: UploadedFileItem[];
  permissions: ModelPermissions;
  allowedStatuses: AllowedStatusOption[];
}
