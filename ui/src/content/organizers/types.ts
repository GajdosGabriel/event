import type { IconName } from '@/components/AppIcon.vue'

/** Cieľ tlačidla na stránke „Pre organizátorov" — rieši ho stránka, nie obsah. */
export type OrganizersAction = 'poster' | 'register' | 'events'

export interface OrganizersFeature {
  icon: IconName
  title: string
  text: string
}

export interface OrganizersStep {
  title: string
  text: string
  cta: string
  action: OrganizersAction
}

export interface OrganizersPageContent {
  metaTitle: string
  metaDescription: string
  hero: {
    eyebrow: string
    title: string
    titleAccent: string
    lead: string
    primary: string
    secondary: string
  }
  promises: string[]
  featuresTitle: string
  featuresLead: string
  features: OrganizersFeature[]
  startTitle: string
  steps: OrganizersStep[]
  claimNote: string
  faqTitle: string
  faq: Array<{ q: string; a: string }>
  cta: {
    title: string
    lead: string
    primary: string
    secondary: string
  }
}
