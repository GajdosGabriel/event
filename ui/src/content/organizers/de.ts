import type { OrganizersPageContent } from './types'

const de: OrganizersPageContent = {
  metaTitle: 'Für Veranstalter — Veranstaltung kostenlos veröffentlichen',
  metaDescription:
    'Veröffentlichen Sie Ihre Veranstaltung kostenlos aus einem Plakat, nehmen Sie Anmeldungen an, verkaufen Sie Tickets, prüfen Sie sie am Eingang per QR-Code und zeigen Sie Ihre Veranstaltungen auf Ihrer Website.',
  hero: {
    eyebrow: 'Für Veranstalter',
    title: 'Ihre Veranstaltungen für alle,',
    titleAccent: ' unkompliziert und kostenlos',
    lead: 'Veröffentlichen Sie eine Veranstaltung in einer Minute aus einem Plakat, sammeln Sie Anmeldungen, verkaufen Sie Tickets und scannen Sie sie am Eingang mit dem Handy. Alles an einem Ort.',
    primary: 'Plakat hochladen',
    secondary: 'Konto erstellen',
  },
  promises: ['Kostenlose Veröffentlichung', 'Ticketeinnahmen gehen auf Ihr Konto', 'Keine Installation, ein Handy genügt'],
  featuresTitle: 'Was Sie damit erledigen',
  featuresLead: 'Von der Einladung bis zur Kontrolle am Eingang. Nutzen Sie nur, was Sie gerade brauchen.',
  features: [
    { icon: 'upload', title: 'Veranstaltung aus dem Plakat', text: 'Laden Sie ein Plakat hoch oder fügen Sie den Einladungstext ein. Name, Termin, Ort und Beschreibung füllen wir aus, Sie prüfen sie nur.' },
    { icon: 'ticket', title: 'Anmeldungen und Tickets', text: 'Kostenlose Reservierungen und bezahlte Tickets mit Kapazität und mehreren Ticketarten. Käufer erhalten Zahlungsanweisungen auf Ihr Konto.' },
    { icon: 'checkin', title: 'Kontrolle per QR-Code', text: 'Tickets scannen Sie mit der Handykamera. Das funktioniert auch ohne Empfang; die Scans werden gesendet, sobald die Verbindung zurück ist.' },
    { icon: 'users', title: 'Teilnehmerliste', text: 'Wer sich angemeldet, wer bezahlt hat, wer gekommen ist. Laden Sie die Liste herunter und schreiben Sie allen Angemeldeten mit einem Klick.' },
    { icon: 'clipboard', title: 'Fragebogen zur Anmeldung', text: 'Fragen Sie nach Verpflegung, Unterkunft oder T-Shirt-Größe. Die Antworten sehen Sie zusammengefasst an einem Ort.' },
    { icon: 'question', title: 'Fragen aus dem Publikum', text: 'Die Menschen im Saal schicken Fragen vom Handy, Sie geben sie frei und zeigen sie auf der Leinwand.' },
    { icon: 'calendar', title: 'Erinnerungen und Wiederholungstermine', text: 'Wir erinnern die Teilnehmer an den Termin und informieren sie bei Änderungen. Wiederkehrende Veranstaltungen legen Sie als Terminserie an.' },
    { icon: 'externalLink', title: 'Veranstaltungen auf Ihrer Website', text: 'Binden Sie die Liste Ihrer Veranstaltungen auf Ihrer eigenen Seite ein. Sie aktualisiert sich selbst.' },
    { icon: 'settings', title: 'Team und Statistiken', text: 'Laden Sie Kollegen als Redakteure oder Einlasspersonal ein. Sehen Sie, wie viele Menschen Ihre Veranstaltungen angesehen und wie viele sich angemeldet haben.' },
  ],
  startTitle: 'So fangen Sie an',
  steps: [
    { title: 'Plakat hochladen', text: 'Ohne Registrierung sehen Sie, was wir aus dem Plakat gelesen haben. Das Konto erstellen Sie erst beim Speichern.', cta: 'Plakat hochladen', action: 'poster' },
    { title: 'Konto erstellen', text: 'Konto und Veranstalterprofil sind kostenlos. Veranstaltungen fügen Sie dann in der Übersicht hinzu und verwalten sie dort.', cta: 'Konto erstellen', action: 'register' },
    { title: 'Ihr Profil finden', text: 'Viele Veranstaltungen sind schon im Portal, übernommen aus öffentlichen Quellen. Finden Sie Ihre Veranstaltung und übernehmen Sie das Veranstalterprofil.', cta: 'Ihre Veranstaltung suchen', action: 'events' },
  ],
  claimNote:
    'Ein Profil übernehmen Sie über die Schaltfläche „Verwalten Sie diesen Kanal?“ auf der Veranstalterseite. Wir prüfen das über die Kontakt-E-Mail des Profils oder bearbeiten die Anfrage manuell.',
  faqTitle: 'Häufige Fragen',
  faq: [
    { q: 'Was kostet das?', a: 'Die Veröffentlichung von Veranstaltungen, das Veranstalterprofil, Anmeldungen und die Ticketkontrolle sind kostenlos.' },
    { q: 'Wie bekomme ich das Geld für die Tickets?', a: 'Käufer zahlen per Überweisung direkt auf das Bankkonto Ihrer Organisation. Sie markieren die Zahlung dann in der Liste und das Ticket wird freigegeben.' },
    { q: 'Meine Veranstaltung ist schon im Portal. Wer hat sie eingetragen?', a: 'Wir übernehmen Veranstaltungen aus öffentlichen Quellen, damit man sie an einem Ort findet. Wenn Sie das Profil übernehmen, können Sie die Angaben korrigieren, ergänzen und Anmeldungen sowie Fragen direkt erhalten.' },
    { q: 'Brauche ich für die Ticketkontrolle ein spezielles Gerät?', a: 'Nein. Ein Handy mit Kamera und Browser genügt. Für den Einlass können Sie auch Helfer einladen, die nur die Ticketkontrolle sehen.' },
    { q: 'Kann ich die Veranstaltungen auf meiner Website zeigen?', a: 'Ja. In den Profileinstellungen finden Sie einen Code für Ihre Seite, und die Liste der Veranstaltungen erscheint dort automatisch.' },
  ],
  cta: {
    title: 'Bereit für Ihre nächste Veranstaltung?',
    lead: 'Ihre erste Veranstaltung ist in wenigen Minuten online.',
    primary: 'Plakat hochladen',
    secondary: 'Konto erstellen',
  },
}

export default de
