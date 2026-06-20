export interface MunicipalityOverviewMunicipality {
  id: number;
  fullname: string;
  shortname: string;
  zip: string | null;
}

export interface MunicipalityOverviewItem {
  municipalityId: number;
  municipalityName: string;
  municipalityShortname: string;
  eventsCount: number;
  thumbImage: string;
  owner: boolean;
  municipality: MunicipalityOverviewMunicipality | null;
}
