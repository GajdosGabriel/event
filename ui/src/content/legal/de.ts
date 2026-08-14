import type { LegalDocuments } from './types'

/** Übersetzung der slowakischen Fassung (sk.ts). Maßgeblich ist der slowakische Wortlaut. */
const de: LegalDocuments = {
  terms: {
    title: 'Allgemeine Geschäftsbedingungen',
    perex:
      'Diese Geschäftsbedingungen regeln die Rechte und Pflichten bei der Nutzung des Portals {site}. ' +
      'Diese Fassung gilt ab {effectiveFrom} (Version {version}). Maßgeblich ist der slowakische Wortlaut.',
    sections: [
      {
        heading: 'Art. 1 — Betreiber',
        paragraphs: [
          '1.1 Betreiber des Portals {site} (nachfolgend „Portal“) ist {name}, Sitz {address}, Ident.-Nr. (IČO): {ico}, {dic}, {registration} (nachfolgend „Betreiber“).',
          '1.2 Kontakt: E-Mail {email}, Telefon {phone}.',
          '1.3 Aufsichtsbehörde ist die Slowakische Handelsinspektion (Slovenská obchodná inšpekcia) — {soi}.',
        ],
      },
      {
        heading: 'Art. 2 — Begriffsbestimmungen',
        paragraphs: [
          '2.1 Portal — die unter {site} erreichbare Webanwendung, die Informationen über kulturelle, gesellschaftliche, sportliche und sonstige Veranstaltungen sowie damit verbundene Dienste veröffentlicht.',
          '2.2 Nutzer — eine natürliche oder juristische Person, die das Portal nutzt, auch ohne Registrierung.',
          '2.3 Registrierter Nutzer — ein Nutzer mit einem Konto auf dem Portal.',
          '2.4 Veranstalter — ein registrierter Nutzer, der auf dem Portal Veranstaltungen veröffentlicht oder darüber Tickets anbietet.',
          '2.5 Besucher — ein Nutzer, der über das Portal ein Ticket reserviert oder kauft bzw. sich für eine Veranstaltung anmeldet.',
          '2.6 Inhalt — Texte, Fotos, Plakate, Links und weitere Daten, die ein Nutzer in das Portal einstellt.',
          '2.7 Vertrag — der Vertrag über die Erbringung der Portalleistungen zwischen dem Betreiber und einem registrierten Nutzer, dessen Inhalt diese Bedingungen bilden.',
        ],
      },
      {
        heading: 'Art. 3 — Registrierung und Nutzerkonto',
        paragraphs: [
          '3.1 Die Registrierung ist kostenlos und freiwillig. Veröffentlichte Veranstaltungen können auch ohne sie angesehen werden.',
          '3.2 Ein Konto kann eine Person ab 16 Jahren anlegen. Für jüngere Personen darf nur deren gesetzlicher Vertreter handeln.',
          '3.3 Der Nutzer schließt die Registrierung ab, indem er das Formular ausfüllt und sein Einverständnis mit diesen Bedingungen ankreuzt. Der Vertrag kommt mit der Bestätigung der E-Mail-Adresse bzw. mit der ersten Anmeldung über ein Google- oder Facebook-Konto zustande.',
          '3.4 Der Nutzer macht wahrheitsgemäße und aktuelle Angaben und ist für die Geheimhaltung seiner Zugangsdaten verantwortlich. Den Verdacht eines Missbrauchs meldet er unverzüglich an {email}.',
          '3.5 Das Konto ist nicht übertragbar. Der Nutzer haftet für Handlungen, die über sein Konto erfolgen.',
          '3.6 Registriert sich ein Nutzer als Veranstalter im Namen einer juristischen Person, erklärt er, zu deren Vertretung berechtigt zu sein.',
        ],
      },
      {
        heading: 'Art. 4 — Vom Nutzer veröffentlichte Inhalte',
        paragraphs: [
          '4.1 Für Richtigkeit, Rechtmäßigkeit und Aktualität des Inhalts haftet der Nutzer, der ihn eingestellt hat. Der Betreiber erstellt die Inhalte nicht und übernimmt keine Gewähr für deren Vollständigkeit oder Wahrheitsgehalt.',
          '4.2 Mit dem Einstellen von Inhalten räumt der Nutzer dem Betreiber ein unentgeltliches, nicht ausschließliches und räumlich unbeschränktes Recht ein, diese im für Betrieb und Bewerbung des Portals erforderlichen Umfang anzuzeigen, zu vervielfältigen, in der Größe anzupassen und zu verbreiten, einschließlich Vorschauen in Suchmaschinen und sozialen Netzwerken, und zwar für die Dauer der Veröffentlichung auf dem Portal.',
          '4.3 Der Nutzer erklärt, zur Einräumung dieses Rechts befugt zu sein und dass der Inhalt keine Rechte Dritter verletzt, insbesondere Urheber-, Persönlichkeits- und Markenrechte.',
          '4.4 Untersagt sind Inhalte, die gegen die Rechtsordnung der Slowakischen Republik oder die guten Sitten verstoßen, insbesondere hetzerische, ehrverletzende, irreführende, grundsätzlich veranstaltungsfremde, gewaltverherrlichende oder für Minderjährige ungeeignete Inhalte.',
          '4.5 Der Betreiber ist berechtigt, Inhalte, die gegen diese Bedingungen verstoßen, nicht zu veröffentlichen, ihre Kategorisierung zu ändern oder sie zu entfernen. Über eine Entfernung informiert er den Nutzer; dieser kann unter {email} Einspruch erheben.',
          '4.6 Der Betreiber ist nicht verpflichtet, Inhalte vorab zu prüfen. Auf Meldungen rechtswidriger Inhalte an {email} reagiert er unverzüglich.',
        ],
      },
      {
        heading: 'Art. 5 — Tickets und Zahlungen',
        paragraphs: [
          '5.1 Ermöglicht das Portal die Reservierung oder den Verkauf von Tickets, handelt der Betreiber als Vermittler im Namen und für Rechnung des Veranstalters. Der Vertrag über die Teilnahme an der Veranstaltung kommt zwischen Besucher und Veranstalter zustande, nicht mit dem Betreiber.',
          '5.2 Der Ticketpreis wird einschließlich Mehrwertsteuer angegeben. Etwaige Service- oder Transaktionsgebühren werden vor der verbindlichen Bestellung ausgewiesen; der Gesamtbetrag wird dem Besucher vor der Bestätigung der Bestellung angezeigt.',
          '5.3 Das Ticket wird elektronisch an die in der Bestellung angegebene E-Mail-Adresse zugestellt und gilt zusammen mit einem eindeutigen Code, der am Einlass geprüft wird. Für den Schutz des Tickets vor Kopieren haftet der Besucher; bei mehrfacher Verwendung desselben Codes gilt der erste Einlass.',
          '5.4 Für das Zustandekommen der Veranstaltung, ihren Inhalt, Ablauf, Termin- und Ortsänderungen sowie die Absage haftet ausschließlich der Veranstalter. Die Erstattung des Eintrittsgelds bei abgesagten oder verschobenen Veranstaltungen wickelt der Veranstalter ab.',
          '5.5 Eine kostenlose Reservierung oder Anmeldung begründet keinen Einlassanspruch über die vom Veranstalter angekündigte Kapazität hinaus.',
        ],
      },
      {
        heading: 'Art. 6 — Widerruf und Reklamationen',
        paragraphs: [
          '6.1 Ein registrierter Nutzer, der Verbraucher ist, kann vom Vertrag über die Nutzung des Portals jederzeit und ohne Angabe von Gründen durch Löschen seines Kontos zurücktreten. Die Leistung ist unentgeltlich, sodass hierfür keine Kosten anfallen.',
          '6.2 Bei Veranstaltungstickets besteht kein 14-tägiges Widerrufsrecht. Es handelt sich um eine Dienstleistung im Zusammenhang mit Freizeitbetätigungen, bei der sich der Anbieter zur Leistung zu einem bestimmten Zeitpunkt verpflichtet — Ausnahme nach § 19 des Gesetzes Nr. 108/2024 Slg. über den Verbraucherschutz.',
          '6.3 Reklamationen zu den Portalleistungen können per E-Mail an {email} geltend gemacht werden. Der Betreiber bestätigt den Eingang, erledigt die Reklamation spätestens innerhalb von 30 Tagen und stellt darüber einen schriftlichen Nachweis aus.',
          '6.4 Reklamationen zur Veranstaltung selbst (Ablauf, Qualität, Absage) sind beim Veranstalter als Vertragspartner geltend zu machen.',
        ],
      },
      {
        heading: 'Art. 7 — Betrieb des Portals und Haftung',
        paragraphs: [
          '7.1 Der Betreiber bemüht sich in angemessenem Umfang um eine durchgehende Verfügbarkeit des Portals, garantiert sie jedoch nicht. Das Portal kann für die erforderliche Zeit wegen Wartung, Aktualisierung oder Ausfällen Dritter nicht erreichbar sein.',
          '7.2 Der Betreiber darf Umfang und Gestalt der Portalfunktionen ändern. Beabsichtigt er, den Betrieb einzustellen, teilt er dies registrierten Nutzern mindestens 30 Tage im Voraus mit.',
          '7.3 Der Betreiber haftet nicht für Schäden durch Nichtverfügbarkeit des Portals, Verlust eingestellter Inhalte oder Handlungen Dritter, sofern er sie nicht vorsätzlich oder grob fahrlässig verursacht hat. Die Haftung für Gesundheitsschäden und sonstige nicht ausschließbare Haftung bleibt unberührt.',
          '7.4 Der Nutzer darf das Portal nicht über das übliche Maß hinaus durch automatisierten Datenabruf belasten, Sicherheitsvorkehrungen umgehen oder in seine technische Umsetzung eingreifen.',
        ],
      },
      {
        heading: 'Art. 8 — Laufzeit und Beendigung',
        paragraphs: [
          '8.1 Der Vertrag wird auf unbestimmte Zeit geschlossen.',
          '8.2 Der Nutzer kann sein Konto jederzeit in den Kontoeinstellungen oder per Anfrage an {email} löschen.',
          '8.3 Der Betreiber kann ein Konto einschränken oder löschen, wenn der Nutzer diese Bedingungen schwerwiegend oder wiederholt verletzt oder das Konto offensichtlich missbraucht wird. Er informiert den Nutzer per E-Mail unter Angabe des Grundes.',
          '8.4 Mit der Löschung des Kontos endet der Vertrag. Informationen über bereits stattgefundene Veranstaltungen können im Archiv des Portals verbleiben; personenbezogene Daten werden nur noch im Umfang der Datenschutzhinweise verarbeitet.',
        ],
      },
      {
        heading: 'Art. 9 — Schutz personenbezogener Daten',
        paragraphs: [
          '9.1 Der Betreiber verarbeitet personenbezogene Daten in dem Umfang und auf die Weise, wie im Dokument Datenschutz beschrieben, das informativer Bestandteil dieser Bedingungen ist.',
          '9.2 Die Verarbeitung der für Kontoführung und Leistungserbringung erforderlichen Daten beruht auf der Vertragserfüllung, nicht auf einer Einwilligung — sie kann daher nicht gesondert widerrufen werden, solange das Konto besteht.',
        ],
      },
      {
        heading: 'Art. 10 — Alternative Streitbeilegung',
        paragraphs: [
          '10.1 Ein Verbraucher, der mit der Erledigung seiner Reklamation nicht zufrieden ist oder der Ansicht ist, der Betreiber habe seine Rechte verletzt, kann unter {email} Abhilfe verlangen.',
          '10.2 Antwortet der Betreiber ablehnend oder innerhalb von 30 Tagen gar nicht, hat der Verbraucher das Recht, einen Antrag auf alternative Streitbeilegung nach dem Gesetz Nr. 391/2015 Slg. über die alternative Beilegung von Verbraucherstreitigkeiten zu stellen.',
          '10.3 Zuständige Stelle ist die Slowakische Handelsinspektion (Ústredný inšpektorát SOI, Bajkalská 21/A, 827 99 Bratislava, www.soi.sk) oder eine andere befugte juristische Person, die in der vom Wirtschaftsministerium der Slowakischen Republik geführten Liste der Stellen zur alternativen Streitbeilegung eingetragen ist. Der Verbraucher kann wählen, an wen er sich wendet.',
          '10.4 Die alternative Streitbeilegung ist für den Verbraucher unentgeltlich oder mit höchstens 5 Euro bepreist. Das Recht, ein Gericht anzurufen, bleibt unberührt.',
        ],
      },
      {
        heading: 'Art. 11 — Schlussbestimmungen',
        paragraphs: [
          '11.1 Nicht geregelte Rechtsverhältnisse richten sich nach dem Recht der Slowakischen Republik, insbesondere dem Bürgerlichen Gesetzbuch, dem Gesetz Nr. 108/2024 Slg. über den Verbraucherschutz und dem Gesetz Nr. 22/2004 Slg. über den elektronischen Geschäftsverkehr.',
          '11.2 Der Betreiber darf diese Bedingungen ändern, insbesondere bei Änderungen der Rechtsvorschriften oder des Leistungsumfangs. Über eine Änderung informiert er registrierte Nutzer mindestens 15 Tage vor Wirksamwerden der neuen Fassung per E-Mail oder Hinweis auf dem Portal.',
          '11.3 Ist der Nutzer mit der Änderung nicht einverstanden, kann er sein Konto bis zum Tag ihres Wirksamwerdens löschen. Mit der weiteren Nutzung nach diesem Tag nimmt er die Änderung an.',
          '11.4 Sollte eine Bestimmung dieser Bedingungen unwirksam werden, bleiben die übrigen Bestimmungen wirksam.',
          '11.5 Diese Bedingungen sind in slowakischer Sprache abgefasst; Fassungen in anderen Sprachen sind eine informative Übersetzung, im Widerspruchsfall ist der slowakische Wortlaut maßgeblich.',
          '11.6 Diese Fassung tritt am {effectiveFrom} in Kraft und ersetzt alle bisherigen Fassungen (Version {version}).',
        ],
      },
    ],
  },

  privacy: {
    title: 'Datenschutz',
    perex:
      'Dieses Dokument erklärt, welche personenbezogenen Daten das Portal {site} über Sie verarbeitet, zu welchem Zweck, wie lange und welche Rechte Sie haben. ' +
      'Wir handeln nach der Verordnung (EU) 2016/679 (DSGVO) und dem Gesetz Nr. 18/2018 Slg. über den Schutz personenbezogener Daten. ' +
      'Diese Fassung gilt ab {effectiveFrom} (Version {version}).',
    sections: [
      {
        heading: '1. Wer die Daten verarbeitet',
        paragraphs: [
          'Verantwortlicher im Sinne der DSGVO ist {name}, Sitz {address}, Ident.-Nr. (IČO): {ico}, {registration}.',
          'In Datenschutzangelegenheiten erreichen Sie uns unter {email} oder schriftlich an der Anschrift des Sitzes.',
        ],
      },
      {
        heading: '2. Welche Daten wir verarbeiten',
        paragraphs: [
          'Registrierungsdaten — E-Mail-Adresse, Name oder Firma, Passwort (ausschließlich als unlesbarer Hash gespeichert) und Registrierungsart (E-Mail, Google, Facebook).',
          'Einwilligungsnachweis — Datum und Version der Geschäftsbedingungen, denen Sie bei der Registrierung zugestimmt haben.',
          'Profil- und Rechnungsdaten des Veranstalters — Name, Kontakt- und Rechnungsdaten, sofern Sie diese angeben.',
          'Von Ihnen veröffentlichte Inhalte — Veranstaltungen, Orte, Fotos, Plakate und Texte einschließlich darin genannter Kontaktdaten.',
          'Kommunikation — Inhalt der über das Portal gesendeten Nachrichten und der E-Mail-Korrespondenz mit uns.',
          'Ticketdaten — bestellte Tickets, deren Status und der Einlassnachweis.',
          'Technische Daten — IP-Adresse, Browsertyp und -sprache, Datum und Uhrzeit des Zugriffs, Anmeldeprotokolle und Fehlerprotokolle der Anwendung.',
        ],
      },
      {
        heading: '3. Zwecke und Rechtsgrundlagen',
        paragraphs: [
          'Führung des Nutzerkontos und Bereitstellung der Portalfunktionen — Vertragserfüllung nach Art. 6 Abs. 1 lit. b DSGVO. Die Angabe dieser Daten ist erforderlich; ohne sie entsteht kein Konto.',
          'Veröffentlichung der von Ihnen eingestellten Inhalte einschließlich ihrer Anzeige in Suchmaschinen — Vertragserfüllung nach Art. 6 Abs. 1 lit. b DSGVO.',
          'Vermittlung von Tickets, deren Zustellung und Prüfung am Einlass — Vertragserfüllung nach Art. 6 Abs. 1 lit. b DSGVO; die für den Einlass nötigen Daten werden an den Veranstalter übermittelt.',
          'Bestätigung der E-Mail-Adresse, Absicherung der Konten und Schutz vor Missbrauch und Spam — berechtigtes Interesse nach Art. 6 Abs. 1 lit. f DSGVO.',
          'Nachweis der erteilten Zustimmung zu den Geschäftsbedingungen — rechtliche Verpflichtung nach Art. 6 Abs. 1 lit. c in Verbindung mit Art. 7 Abs. 1 DSGVO.',
          'Buchführung und Erfüllung steuerlicher Pflichten bei kostenpflichtigen Leistungen — rechtliche Verpflichtung nach Art. 6 Abs. 1 lit. c DSGVO.',
          'Versand von Neuigkeiten und Veranstaltungshinweisen, sofern Sie diese anfordern — Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO, jederzeit widerrufbar ohne Auswirkung auf die Rechtmäßigkeit der bisherigen Verarbeitung.',
          'Geltendmachung oder Abwehr von Rechtsansprüchen — berechtigtes Interesse nach Art. 6 Abs. 1 lit. f DSGVO.',
        ],
      },
      {
        heading: '4. Wie lange wir die Daten speichern',
        paragraphs: [
          'Kontodaten — für die Dauer des Kontos und danach höchstens 1 Jahr ab Löschung, wegen möglicher Ansprüche aus der Nutzung des Portals.',
          'Nicht bestätigte Registrierung — längstens bis zum Ablauf des Bestätigungslinks (48 Stunden), danach automatische Löschung.',
          'Einwilligungsnachweis — für die Dauer des Kontos und 1 Jahr nach dessen Löschung, gemeinsam mit den Kontodaten.',
          'Veröffentlichte Veranstaltungsinhalte — können auch nach Löschung des Kontos im Archiv des Portals verbleiben, in der Regel ohne Kontaktdaten natürlicher Personen.',
          'Ticketdaten und Buchhaltungsbelege — 10 Jahre, soweit steuer- und buchhaltungsrechtlich erforderlich.',
          'Technische Protokolle — in der Regel 12 Monate.',
        ],
      },
      {
        heading: '5. An wen wir Daten weitergeben',
        paragraphs: [
          'An technische Dienstleister — Hosting und Serverinfrastruktur, E-Mail-Versand, gegebenenfalls Zahlungsdienstleister und IT-Support. Diese Empfänger handeln als Auftragsverarbeiter auf Grundlage eines Vertrags nach Art. 28 DSGVO.',
          'An den Veranstalter — im für Reservierung, Ticket und Einlass erforderlichen Umfang.',
          'An Steuerberater und Berater — im für die Erfüllung gesetzlicher Pflichten erforderlichen Umfang.',
          'An Behörden — nur soweit gesetzlich vorgeschrieben.',
          'Wir verkaufen keine personenbezogenen Daten und geben sie nicht für eigene Marketingzwecke Dritter weiter.',
        ],
      },
      {
        heading: '6. Übermittlung in Drittländer',
        paragraphs: [
          'Wir verarbeiten die Daten innerhalb der Europäischen Union und des Europäischen Wirtschaftsraums.',
          'Käme es bei einem Dienst (etwa bei der Anmeldung über ein Google- oder Facebook-Konto) zu einer Übermittlung außerhalb des EWR, erfolgt sie auf Grundlage eines Angemessenheitsbeschlusses der Kommission oder der von der Europäischen Kommission genehmigten Standardvertragsklauseln.',
        ],
      },
      {
        heading: '7. Ihre Rechte',
        paragraphs: [
          'Sie haben das Recht auf Auskunft über Ihre Daten und auf eine Kopie der verarbeiteten Daten.',
          'Sie haben das Recht auf Berichtigung unrichtiger und Vervollständigung unvollständiger Daten.',
          'Sie haben das Recht auf Löschung, sofern die Daten nicht mehr benötigt werden und keine gesetzliche Aufbewahrungspflicht entgegensteht.',
          'Sie haben das Recht auf Einschränkung der Verarbeitung und auf Übertragbarkeit der von Ihnen bereitgestellten Daten.',
          'Sie haben das Recht, der auf berechtigtem Interesse beruhenden Verarbeitung zu widersprechen, und das Recht, eine Einwilligung jederzeit zu widerrufen, wo sie Rechtsgrundlage ist.',
          'Anfragen senden Sie an {email}. Wir antworten spätestens innerhalb eines Monats nach Eingang.',
          'Sie haben das Recht auf Beschwerde bei der Aufsichtsbehörde: Úrad na ochranu osobných údajov Slovenskej republiky, Hraničná 12, 820 07 Bratislava 27, Slowakei, www.dataprotection.gov.sk.',
        ],
      },
      {
        heading: '8. Automatisierte Entscheidungsfindung',
        paragraphs: [
          'Wir führen keine automatisierte Entscheidungsfindung und kein Profiling durch, die Ihnen gegenüber rechtliche Wirkung entfalten oder Sie in ähnlicher Weise erheblich beeinträchtigen würden.',
        ],
      },
      {
        heading: '9. Cookies und lokaler Speicher',
        paragraphs: [
          'Das Portal verwendet notwendige Cookies und den lokalen Speicher des Browsers für Anmeldung, Sicherheit und das Merken der gewählten Sprache. Ohne sie würde das Portal nicht funktionieren, daher ist hierfür keine Einwilligung erforderlich.',
          'Sollten wir Analyse- oder Marketing-Cookies einsetzen, holen wir vorab Ihre Einwilligung ein; diese ist jederzeit widerrufbar.',
          'Gespeicherte Cookies können Sie jederzeit in den Einstellungen Ihres Browsers löschen; einzelne Funktionen des Portals funktionieren dann möglicherweise nicht mehr.',
        ],
      },
      {
        heading: '10. Änderungen dieses Dokuments',
        paragraphs: [
          'Die aktuelle Fassung ist stets auf dieser Seite verfügbar. Über wesentliche Änderungen informieren wir registrierte Nutzer per E-Mail oder durch einen Hinweis auf dem Portal.',
          'Diese Fassung gilt ab {effectiveFrom} (Version {version}). Maßgeblich ist der slowakische Wortlaut; andere Sprachfassungen sind eine informative Übersetzung.',
        ],
      },
    ],
  },
}

export default de
