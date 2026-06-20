import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'timeUntil', standalone: true, pure: false })
export class TimeUntilPipe implements PipeTransform {
  private readonly rtf = new Intl.RelativeTimeFormat('sk', { numeric: 'always' });

  transform(value: string | null | undefined): string | null {
    if (!value) return null;

    const diffMs = new Date(value).getTime() - Date.now();
    if (isNaN(diffMs)) return null;

    const diffMin = Math.round(diffMs / 60000);
    const diffHrs = Math.round(diffMs / 3600000);
    const diffDays = Math.round(diffMs / 86400000);

    if (Math.abs(diffMin) < 60) return this.rtf.format(diffMin, 'minute');
    if (Math.abs(diffHrs) < 24) return this.rtf.format(diffHrs, 'hour');
    return this.rtf.format(diffDays, 'day');
  }
}
