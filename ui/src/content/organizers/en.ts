import type { OrganizersPageContent } from './types'

const en: OrganizersPageContent = {
  metaTitle: 'For organisers — publish your event for free',
  metaDescription:
    'Publish your event for free from a poster, take sign-ups and sell tickets, check them at the door with a QR code and show your events on your own website.',
  hero: {
    eyebrow: 'For organisers',
    title: 'Your events in front of people,',
    titleAccent: ' hassle-free and free of charge',
    lead: 'Publish an event from a poster in a minute, collect sign-ups, sell tickets and scan them at the door with your phone. All in one place.',
    primary: 'Upload a poster',
    secondary: 'Create an account',
  },
  promises: ['Free publishing', 'Ticket money goes to your account', 'Nothing to install, a phone is enough'],
  featuresTitle: 'What you can do',
  featuresLead: 'From the invitation to the check at the door. Use only what you need right now.',
  features: [
    { icon: 'upload', title: 'Event from a poster', text: 'Upload a poster or paste the invitation text. We fill in the name, date, place and description; you just check them.' },
    { icon: 'ticket', title: 'Sign-ups and tickets', text: 'Free reservations and paid tickets with capacity and several ticket types. Buyers get instructions to pay to your account.' },
    { icon: 'checkin', title: 'QR code check-in', text: 'Scan tickets with your phone camera. It works offline too; scans are sent once the connection is back.' },
    { icon: 'users', title: 'Attendee list', text: 'Who signed up, who paid, who came. Download the list and email everyone who signed up in one click.' },
    { icon: 'clipboard', title: 'Sign-up questionnaire', text: 'Ask about meals, accommodation or T-shirt size. See the answers summarised in one place.' },
    { icon: 'question', title: 'Audience questions', text: 'People in the room send questions from their phones; you approve them and show them on the screen.' },
    { icon: 'calendar', title: 'Reminders and recurring dates', text: 'We remind attendees of the date and notify them of changes. Add a recurring event as a series of dates.' },
    { icon: 'externalLink', title: 'Events on your website', text: 'Embed the list of your events on your own site. It updates itself.' },
    { icon: 'settings', title: 'Team and statistics', text: 'Invite colleagues as editors or door staff. See how many people viewed your events and how many signed up.' },
  ],
  startTitle: 'How to start',
  steps: [
    { title: 'Upload a poster', text: 'See what we read from your poster without signing up. You create an account only when saving.', cta: 'Upload a poster', action: 'poster' },
    { title: 'Create an account', text: 'The account and organiser profile are free. Then add and manage events in your dashboard.', cta: 'Create an account', action: 'register' },
    { title: 'Find your profile', text: 'Many events are already on the portal, taken from public sources. Find your event and take over the organiser profile.', cta: 'Find your event', action: 'events' },
  ],
  claimNote:
    'Take over a profile with the “Do you manage this channel?” button on the organiser page. We verify it through the profile’s contact email or review the request manually.',
  faqTitle: 'Frequently asked questions',
  faq: [
    { q: 'How much does it cost?', a: 'Publishing events, the organiser profile, sign-ups and ticket checks are free.' },
    { q: 'How do I get paid for tickets?', a: 'Buyers pay by bank transfer directly to your organisation’s account. You then mark the payment in the list and the ticket is released to them.' },
    { q: 'My event is already on the portal. Who added it?', a: 'We collect events from public sources so people can find them in one place. If you take over the profile, you can correct and complete the details and receive sign-ups and questions yourself.' },
    { q: 'Do I need special equipment to check tickets?', a: 'No. A phone with a camera and a browser is enough. You can also invite helpers for the door who see only the ticket check.' },
    { q: 'Can I show events on my own website?', a: 'Yes. In your profile settings you will find code to paste into your site, and the list of events will display there automatically.' },
  ],
  cta: {
    title: 'Ready to publish your next event?',
    lead: 'Your first event is live in a few minutes.',
    primary: 'Upload a poster',
    secondary: 'Create an account',
  },
}

export default en
