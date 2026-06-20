import { DOCUMENT } from '@angular/common';
import { Injectable, inject } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';

interface PageMetaPayload {
  title: string;
  description?: string | null;
  imageUrl?: string | null;
  type?: 'article' | 'website';
  url?: string | null;
  robots?: string | null;
}

@Injectable({ providedIn: 'root' })
export class PageMetaService {
  private readonly document = inject(DOCUMENT);
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly appName = 'EventUi';
  private readonly defaultDescription = 'Prehlad podujati, miest a kanalov.';
  private readonly defaultLocale = 'sk_SK';
  private readonly defaultLang = 'sk';
  private readonly twitterSiteHandle = '';

  setPageMeta(payload: PageMetaPayload): void {
    const title = this.normalizeText(payload.title) || this.appName;
    const description =
      this.normalizeDescription(payload.description) || this.defaultDescription;
    const fullTitle = title === this.appName ? title : `${title} | ${this.appName}`;
    const type = payload.type ?? 'article';
    const imageUrl = this.normalizeText(payload.imageUrl);
    const canonicalUrl = this.normalizeText(payload.url) || this.resolveCurrentUrl();
    const robots = this.normalizeText(payload.robots) || this.resolveDefaultRobots();

    this.title.setTitle(fullTitle);
    this.updateDocumentLanguage();
    this.updateNameTag('description', description);
    this.updateNameTag('robots', robots);
    this.updateNameTag('twitter:card', imageUrl ? 'summary_large_image' : 'summary');
    this.updateNameTag('twitter:title', title);
    this.updateNameTag('twitter:description', description);
    this.updatePropertyTag('og:locale', this.defaultLocale);
    this.updatePropertyTag('og:title', title);
    this.updatePropertyTag('og:description', description);
    this.updatePropertyTag('og:type', type);
    this.updatePropertyTag('og:site_name', this.appName);

    if (this.twitterSiteHandle) {
      this.updateNameTag('twitter:site', this.twitterSiteHandle);
    } else {
      this.meta.removeTag("name='twitter:site'");
    }

    if (canonicalUrl) {
      this.updatePropertyTag('og:url', canonicalUrl);
      this.updateCanonicalLink(canonicalUrl);
    } else {
      this.meta.removeTag("property='og:url'");
      this.removeCanonicalLink();
    }

    if (imageUrl) {
      this.updateNameTag('twitter:image', imageUrl);
      this.updateNameTag('twitter:image:alt', title);
      this.updatePropertyTag('og:image', imageUrl);
      this.updatePropertyTag('og:image:alt', title);
      return;
    }

    this.meta.removeTag("name='twitter:image'");
    this.meta.removeTag("name='twitter:image:alt'");
    this.meta.removeTag("property='og:image'");
    this.meta.removeTag("property='og:image:alt'");
  }

  private updateNameTag(name: string, content: string): void {
    this.meta.updateTag({ name, content }, `name='${name}'`);
  }

  private updatePropertyTag(property: string, content: string): void {
    this.meta.updateTag({ property, content }, `property='${property}'`);
  }

  private normalizeText(value: string | null | undefined): string {
    return typeof value === 'string' ? value.trim() : '';
  }

  private normalizeDescription(value: string | null | undefined): string {
    const normalized = this.normalizeText(value).replace(/\s+/g, ' ');
    return normalized.slice(0, 160);
  }

  private resolveCurrentUrl(): string {
    const href = this.normalizeText(this.document.location?.href);
    if (href) {
      return href;
    }

    return this.normalizeText(this.document.baseURI);
  }

  private resolveDefaultRobots(): string {
    const pathname = this.normalizeText(this.document.location?.pathname).toLowerCase();

    if (
      pathname.startsWith('/admin') ||
      pathname.startsWith('/dashboard') ||
      pathname.startsWith('/login') ||
      pathname.startsWith('/register') ||
      pathname.startsWith('/verify-email') ||
      pathname.includes('/edit') ||
      pathname.includes('/create')
    ) {
      return 'noindex,nofollow';
    }

    return 'index,follow';
  }

  private updateDocumentLanguage(): void {
    this.document.documentElement?.setAttribute('lang', this.defaultLang);
  }

  private updateCanonicalLink(href: string): void {
    let link = this.document.head.querySelector("link[rel='canonical']") as HTMLLinkElement | null;

    if (!link) {
      link = this.document.createElement('link');
      link.setAttribute('rel', 'canonical');
      this.document.head.appendChild(link);
    }

    link.setAttribute('href', href);
  }

  private removeCanonicalLink(): void {
    const link = this.document.head.querySelector("link[rel='canonical']");
    link?.parentNode?.removeChild(link);
  }
}
