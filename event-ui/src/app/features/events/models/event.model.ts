import { UploadedFileItem } from '../../../shared/models/uploaded-file.model';
import { AllowedStatusOption, ModelStatus } from '../../../shared/models/model-status';
import { ModelPermissions } from '../../../shared/models/model-permissions';

export interface EventApiMunicipality {
  id: number;
  fullname: string;
  shortname: string;
  zip: string | null;
  district_id: number | null;
  region_id: number | null;
  use: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EventMunicipality {
  id: number;
  fullname: string;
  shortname: string;
  zip: string | null;
  districtId: number | null;
  regionId: number | null;
}

export interface EventApiItem {
  id: number;
  canal_id: number;
  user_id: number;
  municipality_id: number;
  venue_id: number | null;
  name: string;
  slug: string;
  body: string;
  body_ai: string | null;
  start_at: string;
  end_at: string;
  date_range_label?: string | null;
  date_range_days?: {
    start?: string | null;
    end?: string | null;
  } | null;
  registration_deadline_at: string | null;
  published_at: string | null;
  status: string | null;
  website: string | null;
  location_name: string | null;
  street: string | null;
  postcode: string | null;
  country: string | null;
  latitude: string | null;
  longitude: string | null;
  orginal_source: string | null;
  meta: unknown;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
  owner: boolean;
  primary_image?: unknown;
  files?: unknown;
  media?: unknown;
  attachments?: unknown;
  images?: unknown;
  permissions?: Partial<ModelPermissions>;
  allowed_statuses?: unknown[];
  municipality?: EventApiMunicipality | null;
  canal?: {
    id: number;
    name: string;
    street: string;
    latitude: number | null;
    longitude: number | null;
  } | null;
  venue?: {
    id: number;
    name: string;
    street: string;
    latitude: number | null;
    longitude: number | null;
  } | null;
}

export interface EventItem {
  id: number;
  canalId: number;
  canalName: string;
  municipalityId: number;
  venueId: number | null;
  name: string;
  slug: string;
  body: string;
  body_ai: string | null;
  status: ModelStatus;
  startAt: string | null;
  endAt: string | null;
  dateRangeLabel: string | null;
  dateRangeDays: {
    start: string | null;
    end: string | null;
  };
  registrationDeadlineAt: string | null;
  publishedAt: string | null;
  deletedAt: string | null;
  website: string | null;
  locationName: string | null;
  street: string | null;
  postcode: string | null;
  country: string | null;
  latitude: string | null;
  longitude: string | null;
  imageUrl: string;
  uploadedFiles: UploadedFileItem[];
  permissions: ModelPermissions;
  allowedStatuses: AllowedStatusOption[];
  municipality: EventMunicipality;
  canal: {
    id: number;
    name: string;
    street: string;
    latitude: number | null;
    longitude: number | null;
  };
  venue: {
    id: number;
    name: string;
    street: string;
    latitude: number | null;
    longitude: number | null;
  };
}
