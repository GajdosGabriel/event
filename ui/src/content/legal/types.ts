/** Tvar právneho dokumentu. Oddelene od `index.ts`, aby jazykové súbory
 *  nemuseli importovať modul, ktorý ich sám importuje. */
export type LegalSection = {
  heading: string
  paragraphs: string[]
}

export type LegalDocument = {
  title: string
  /** Krátke uvedenie nad prvým článkom — čo dokument upravuje a od kedy platí. */
  perex: string
  sections: LegalSection[]
}

export type LegalDocuments = {
  terms: LegalDocument
  privacy: LegalDocument
}

export type LegalKind = keyof LegalDocuments
