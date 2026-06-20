export interface ModelPermissions {
  view: boolean;
  update: boolean;
  publish?: boolean;
  delete: boolean;
  archive?: boolean;
  restore: boolean;
}

export interface CollectionPermissions {
  create: boolean;
}
