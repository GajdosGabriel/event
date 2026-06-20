import { Component, input } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { TruncatePipe } from '../../pipes/truncate.pipe';
import { TimeUntilPipe } from '../../pipes/time-until.pipe';
import { StripHtmlPipe } from '../../pipes/strip-html.pipe';

@Component({
  selector: 'app-event-card',
  imports: [RouterLink, DatePipe, TruncatePipe, TimeUntilPipe, StripHtmlPipe],
  templateUrl: './event-card.component.html',
  styleUrl: './event-card.component.css'
})
export class EventCardComponent {
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly category = input.required<string>();
  readonly canalName = input.required<string>();
  readonly imageUrl = input.required<string>();
  readonly link = input.required<string>();
  readonly startAt = input<string | null>();
  readonly registrationDeadlineAt = input<string | null>();
}
