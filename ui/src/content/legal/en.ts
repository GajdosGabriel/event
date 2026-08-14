import type { LegalDocuments } from './types'

/** Translation of the Slovak original (sk.ts). The Slovak version prevails. */
const en: LegalDocuments = {
  terms: {
    title: 'Terms and Conditions',
    perex:
      'These terms and conditions govern the rights and obligations arising from the use of the {site} portal. ' +
      'This version is effective from {effectiveFrom} (version {version}). The Slovak wording prevails.',
    sections: [
      {
        heading: 'Art. 1 — Operator',
        paragraphs: [
          '1.1 The {site} portal (the “Portal”) is operated by {name}, registered office {address}, Company ID: {ico}, {dic}, {registration} (the “Operator”).',
          '1.2 Contact details: e-mail {email}, phone {phone}.',
          '1.3 The supervisory authority is the Slovak Trade Inspection (Slovenská obchodná inšpekcia) — {soi}.',
        ],
      },
      {
        heading: 'Art. 2 — Definitions',
        paragraphs: [
          '2.1 Portal — the web application available at {site} publishing information about cultural, social, sporting and other events, and related services.',
          '2.2 User — a natural or legal person using the Portal, including without registration.',
          '2.3 Registered User — a User with an account on the Portal.',
          '2.4 Organiser — a Registered User who publishes events on the Portal or offers tickets through it.',
          '2.5 Visitor — a User who reserves or buys a ticket through the Portal, or signs up for an event.',
          '2.6 Content — texts, photographs, posters, links and other data uploaded to the Portal by a User.',
          '2.7 Agreement — the contract for the provision of the Portal’s services between the Operator and a Registered User, the content of which is set out in these terms.',
        ],
      },
      {
        heading: 'Art. 3 — Registration and user account',
        paragraphs: [
          '3.1 Registration is free of charge and voluntary. Published events can be browsed without it.',
          '3.2 An account may be created by a person over 16 years of age. For a younger person, only their legal representative may act.',
          '3.3 The User completes registration by filling in the form and ticking their agreement with these terms. The Agreement is concluded upon verification of the e-mail address, or upon the first sign-in via a Google or Facebook account.',
          '3.4 The User provides truthful and current data and is responsible for keeping their account credentials confidential. Any suspected misuse of the account must be reported without delay to {email}.',
          '3.5 The account is non-transferable. The User is responsible for actions carried out through their account.',
          '3.6 A User registering as an Organiser on behalf of a legal entity declares that they are authorised to act for it.',
        ],
      },
      {
        heading: 'Art. 4 — Content published by Users',
        paragraphs: [
          '4.1 The User who uploaded the Content is responsible for its accuracy, lawfulness and timeliness. The Operator does not create the Content and does not warrant its completeness or truthfulness.',
          '4.2 By uploading Content, the User grants the Operator a free, non-exclusive and territorially unlimited licence to display, reproduce, resize and distribute it to the extent needed to operate and promote the Portal, including previews in search engines and on social networks, for as long as the Content is published on the Portal.',
          '4.3 The User declares that they are entitled to grant such a licence and that the Content does not infringe the rights of third parties, in particular copyright, personality rights and trade mark rights.',
          '4.4 Content contrary to the laws of the Slovak Republic or to good morals is prohibited, in particular content that is hateful, defamatory, misleading, fundamentally unrelated to events, promoting violence, or unsuitable for minors.',
          '4.5 The Operator may refuse to publish Content that breaches these terms, change its categorisation, or remove it. The Operator informs the User of any removal; the User may object at {email}.',
          '4.6 The Operator is not obliged to review Content in advance. It responds without undue delay to notices of unlawful Content sent to {email}.',
        ],
      },
      {
        heading: 'Art. 5 — Tickets and payments',
        paragraphs: [
          '5.1 Where the Portal allows tickets to be reserved or sold, the Operator acts as an intermediary in the name and on behalf of the Organiser. The contract for attending the event is concluded between the Visitor and the Organiser, not with the Operator.',
          '5.2 Ticket prices are stated including value added tax. Any service or transaction fees are shown before the order is bindingly submitted; the total amount payable is displayed to the Visitor before the order is confirmed.',
          '5.3 The ticket is delivered electronically to the e-mail address given in the order and is valid together with a unique code checked at the entrance. The Visitor is responsible for protecting the ticket against copying; if the same code is used repeatedly, the first admission prevails.',
          '5.4 The Organiser alone is responsible for holding the event, its content, course, change of date or venue, and cancellation. Refunds for cancelled or rescheduled events are handled by the Organiser.',
          '5.5 A free reservation or sign-up does not create a right of entry beyond the capacity announced by the Organiser.',
        ],
      },
      {
        heading: 'Art. 6 — Withdrawal and complaints',
        paragraphs: [
          '6.1 A Registered User who is a consumer may withdraw from the Agreement on the use of the Portal at any time by deleting their account, without giving a reason. The service is free of charge, so withdrawal involves no costs.',
          '6.2 The consumer’s 14-day right of withdrawal does not apply to event tickets. This is the provision of a service related to leisure activities where the provider undertakes to perform at an agreed time — the exception under Section 19 of Act No. 108/2024 Coll. on consumer protection.',
          '6.3 Complaints about the Portal’s services may be submitted by e-mail to {email}. The Operator confirms receipt, settles the complaint no later than 30 days from its submission and issues a written record of the outcome.',
          '6.4 Complaints about the event itself (its course, quality or cancellation) are to be raised with the Organiser, who is the contracting party.',
        ],
      },
      {
        heading: 'Art. 7 — Operation of the Portal and liability',
        paragraphs: [
          '7.1 The Operator makes reasonable efforts to keep the Portal continuously available but does not guarantee it. The Portal may be unavailable for the time necessary due to maintenance, updates or third-party outages.',
          '7.2 The Operator may change the scope and form of the Portal’s features. Should it decide to discontinue operation, it will notify Registered Users at least 30 days in advance.',
          '7.3 The Operator is not liable for damage caused by unavailability of the Portal, loss of uploaded Content or acts of third parties, unless caused intentionally or by gross negligence. This does not affect liability for damage to health or other liability that cannot be excluded.',
          '7.4 Users must not burden the Portal with automated data retrieval beyond ordinary use, circumvent security features, or interfere with its technical implementation.',
        ],
      },
      {
        heading: 'Art. 8 — Duration and termination',
        paragraphs: [
          '8.1 The Agreement is concluded for an indefinite period.',
          '8.2 The User may delete the account at any time in the account settings or by request to {email}.',
          '8.3 The Operator may restrict or delete an account if the User seriously or repeatedly breaches these terms, or if the account is manifestly being misused. The Operator informs the User by e-mail, stating the reason.',
          '8.4 Deleting the account terminates the Agreement. Information about events that have already taken place may remain in the Portal’s archive; personal data is further processed only as described in the privacy notice.',
        ],
      },
      {
        heading: 'Art. 9 — Personal data',
        paragraphs: [
          '9.1 The Operator processes personal data to the extent and in the manner described in the Privacy Notice, which forms an informational part of these terms.',
          '9.2 Processing of data necessary to maintain the account and provide the Portal’s services is based on performance of this Agreement, not on consent — it therefore cannot be withdrawn separately while the account exists.',
        ],
      },
      {
        heading: 'Art. 10 — Alternative dispute resolution',
        paragraphs: [
          '10.1 A consumer dissatisfied with the way a complaint was handled, or who believes the Operator has infringed their rights, may request redress at {email}.',
          '10.2 If the Operator rejects the request or fails to reply within 30 days, the consumer has the right to file a proposal to initiate alternative dispute resolution under Act No. 391/2015 Coll. on alternative resolution of consumer disputes.',
          '10.3 The competent body is the Slovak Trade Inspection (Ústredný inšpektorát SOI, Bajkalská 21/A, 827 99 Bratislava, www.soi.sk) or another authorised entity listed in the register of alternative dispute resolution entities maintained by the Ministry of Economy of the Slovak Republic. The consumer may choose which one to approach.',
          '10.4 Alternative dispute resolution is free of charge for the consumer or subject to a fee of no more than EUR 5. The right to bring the matter before a court is not affected.',
        ],
      },
      {
        heading: 'Art. 11 — Final provisions',
        paragraphs: [
          '11.1 Legal relationships not governed by these terms are subject to the law of the Slovak Republic, in particular the Civil Code, Act No. 108/2024 Coll. on consumer protection and Act No. 22/2004 Coll. on electronic commerce.',
          '11.2 The Operator may amend these terms, in particular following changes in legislation or in the scope of services. Registered Users are notified of any amendment by e-mail or by a notice on the Portal at least 15 days before the new version takes effect.',
          '11.3 A User who does not agree with an amendment may delete their account before it takes effect. Continuing to use the Portal after that date constitutes acceptance.',
          '11.4 Should any provision of these terms become invalid or ineffective, the remaining provisions remain in force.',
          '11.5 These terms are drawn up in the Slovak language; versions in other languages are an informative translation and, in the event of a discrepancy, the Slovak wording prevails.',
          '11.6 This version takes effect on {effectiveFrom} and supersedes all previous versions (version {version}).',
        ],
      },
    ],
  },

  privacy: {
    title: 'Privacy Notice',
    perex:
      'This notice explains what personal data the {site} portal processes about you, for what purpose, for how long, and what rights you have. ' +
      'We act in accordance with Regulation (EU) 2016/679 (GDPR) and Act No. 18/2018 Coll. on personal data protection. ' +
      'This version is effective from {effectiveFrom} (version {version}).',
    sections: [
      {
        heading: '1. Who processes the data',
        paragraphs: [
          'The controller within the meaning of the GDPR is {name}, registered office {address}, Company ID: {ico}, {registration}.',
          'For data protection matters, contact us at {email} or in writing at the registered office address.',
        ],
      },
      {
        heading: '2. What data we process',
        paragraphs: [
          'Registration data — e-mail address, name or company name, password (stored solely as an unreadable hash) and the registration method (e-mail, Google, Facebook).',
          'Consent record — the date and version of the terms and conditions you agreed to during registration.',
          'Organiser profile and billing data — name, contact and billing details, if you provide them.',
          'Content you publish — events, venues, photographs, posters and texts, including any contact details you state in them.',
          'Communication — the content of messages sent through the Portal and of e-mail correspondence with us.',
          'Ticket data — tickets ordered, their status and the record of admission to the event.',
          'Technical data — IP address, browser type and language, date and time of access, sign-in records and application error logs.',
        ],
      },
      {
        heading: '3. Purposes and legal bases',
        paragraphs: [
          'Maintaining the user account and providing the Portal’s features — performance of a contract under Art. 6(1)(b) GDPR. Providing this data is necessary; without it no account can be created.',
          'Publishing the content you upload, including its display in search engines — performance of a contract under Art. 6(1)(b) GDPR.',
          'Intermediating tickets, delivering them and checking them at the entrance — performance of a contract under Art. 6(1)(b) GDPR; data needed for admission is passed to the event Organiser.',
          'Verifying the e-mail address, securing accounts and protecting against misuse and spam — legitimate interest under Art. 6(1)(f) GDPR.',
          'Demonstrating the consent given to the terms and conditions — legal obligation under Art. 6(1)(c) in conjunction with Art. 7(1) GDPR.',
          'Bookkeeping and tax compliance for paid services — legal obligation under Art. 6(1)(c) GDPR.',
          'Sending newsletters and event alerts where you request them — consent under Art. 6(1)(a) GDPR, which you may withdraw at any time without affecting the lawfulness of processing before withdrawal.',
          'Establishing or defending legal claims — legitimate interest under Art. 6(1)(f) GDPR.',
        ],
      },
      {
        heading: '4. How long we keep the data',
        paragraphs: [
          'Account data — for as long as the account exists and then for up to 1 year after deletion, for any claims arising from use of the Portal.',
          'Unverified registration — at most until the verification link expires (48 hours), after which it is deleted automatically.',
          'Consent record — for the lifetime of the account and 1 year after its deletion, together with the account data.',
          'Published event content — may remain in the Portal’s archive even after the account is deleted, as a rule without contact details of natural persons.',
          'Ticket data and accounting records — 10 years, where required by tax and accounting legislation.',
          'Technical logs — as a rule 12 months.',
        ],
      },
      {
        heading: '5. Who we share the data with',
        paragraphs: [
          'Technical service providers — hosting and server infrastructure, e-mail delivery, and where applicable a payment gateway and IT support. These recipients act as processors under a contract pursuant to Art. 28 GDPR.',
          'The event Organiser — to the extent needed for the reservation, the ticket and admission to the event.',
          'Accountants and advisers — to the extent needed to meet statutory obligations.',
          'Public authorities — only where required by law.',
          'We do not sell personal data and do not provide it to third parties for their own marketing purposes.',
        ],
      },
      {
        heading: '6. Transfers to third countries',
        paragraphs: [
          'We process data within the European Union and the European Economic Area.',
          'Should a transfer outside the EEA occur with any service (for example when signing in with a Google or Facebook account), it takes place on the basis of a Commission adequacy decision or standard contractual clauses approved by the European Commission.',
        ],
      },
      {
        heading: '7. Your rights',
        paragraphs: [
          'You have the right of access to your data and to a copy of the data processed.',
          'You have the right to have inaccurate data corrected and incomplete data completed.',
          'You have the right to erasure where the data is no longer needed and no statutory retention obligation applies.',
          'You have the right to restriction of processing and the right to portability of the data you provided to us.',
          'You have the right to object to processing based on legitimate interest and to withdraw consent at any time where consent is the legal basis.',
          'Send requests to {email}. We will respond within one month of receiving the request at the latest.',
          'You have the right to lodge a complaint with the supervisory authority: Úrad na ochranu osobných údajov Slovenskej republiky, Hraničná 12, 820 07 Bratislava 27, Slovakia, www.dataprotection.gov.sk.',
        ],
      },
      {
        heading: '8. Automated decision-making',
        paragraphs: [
          'We do not carry out automated decision-making or profiling that would produce legal effects concerning you or similarly significantly affect you.',
        ],
      },
      {
        heading: '9. Cookies and local storage',
        paragraphs: [
          'The Portal uses strictly necessary cookies and browser local storage for sign-in, security and remembering the chosen language. The Portal would not work without them, so no consent is required.',
          'Should we deploy analytics or marketing cookies, we will ask for your consent in advance and it will be withdrawable at any time.',
          'You can delete stored cookies at any time in your browser settings; some features of the Portal may then stop working.',
        ],
      },
      {
        heading: '10. Changes to this notice',
        paragraphs: [
          'The current version is always available on this page. We inform registered users of material changes by e-mail or by a notice on the Portal.',
          'This version is effective from {effectiveFrom} (version {version}). The Slovak wording prevails; other language versions are an informative translation.',
        ],
      },
    ],
  },
}

export default en
