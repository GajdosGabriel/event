import { Component, Input, OnChanges, OnInit, SimpleChanges, signal, inject } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';

@Component({
  selector: 'app-location-map',
  standalone: true,
  templateUrl: './location-map.component.html',
  styleUrl: './location-map.component.css'
})
export class LocationMapComponent implements OnInit, OnChanges {
  @Input() latitude: string | number | null = null;
  @Input() longitude: string | number | null = null;

  protected readonly hasValidCoordinates = signal(false);
  protected readonly mapUrl = signal<SafeResourceUrl | null>(null);

  private readonly sanitizer = inject(DomSanitizer);

  ngOnInit(): void {
    this.updateMapState();
  }

  ngOnChanges(_: SimpleChanges): void {
    this.updateMapState();
  }

  private parseCoordinate(value: string | number | null): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }

    const num = typeof value === 'number' ? value : Number(String(value).trim());
    return Number.isFinite(num) ? num : null;
  }

  protected openInNewTab(): void {
    const lat = Number(String(this.latitude).trim());
    const lng = Number(String(this.longitude).trim());
    window.open(`https://maps.google.com/maps?q=${lat},${lng}&z=15`, '_blank', 'noopener,noreferrer');
  }

  private getEmbedUrl(): string {
    const lat = Number(String(this.latitude).trim());
    const lng = Number(String(this.longitude).trim());

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return '';
    }

    return `https://maps.google.com/maps?q=${lat},${lng}&z=15&output=embed`;
  }

  private updateMapState(): void {
    const lat = this.parseCoordinate(this.latitude);
    const lng = this.parseCoordinate(this.longitude);
    const isValid = lat !== null && lng !== null;
    this.hasValidCoordinates.set(isValid);

    if (!isValid) {
      this.mapUrl.set(null);
      return;
    }

    const url = this.getEmbedUrl();
    this.mapUrl.set(this.sanitizer.bypassSecurityTrustResourceUrl(url));
  }
}
