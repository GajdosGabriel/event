// Právne formy a slová, podľa ktorých text v poli „Meno“ vyzerá skôr ako
// názov organizácie než človeka. Je to len upozornenie — registrácia sa
// nezastaví, človek môže zostať pri osobe.
const LEGAL_FORM = /(^|[\s,])(o\.\s?z\.|s\.\s?r\.\s?o\.|a\.\s?s\.|n\.\s?o\.|z\.\s?s\.|z\.\s?ú\.|spol\.|n\.\s?f\.|o\.\s?p\.\s?s\.|e\.\s?v\.|gmbh|ltd\.?|inc\.?)(?=$|[\s,.])/iu

// Celé slová (koniec slova je povinný, aby „Klubová“ nebola „klub“).
const WORDS = /(^|[^\p{L}])(spolok|spolek|združenie|sdružení|nadácia|nadace|farnosť|farnost|obec|mesto|město|klub|zbor|sbor|centrum|spoločnosť|společnost|škola|cirkev|církev|komunita|festival|divadlo|kino|múzeum|muzeum|galéria|galerie|knižnica|knihovna|organizácia|organizace|verein|stiftung|gemeinde|association|foundation|parish|church|club)(?![\p{L}])/iu

// Kmene, ktoré v osobnom mene nebývajú ani na začiatku slova.
const STEMS = /(^|[^\p{L}])(občiansk|občansk|diecéz|ekumen)/iu

export function looksLikeOrganization(name: string): boolean {
  const value = name.trim()
  if (value === '') return false
  return LEGAL_FORM.test(value) || WORDS.test(value) || STEMS.test(value)
}
