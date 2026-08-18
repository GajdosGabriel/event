/**
 * Token nástenky otázok sa premieta na plátno a ľudia si ho prepisujú rukou.
 * Preto sa zobrazuje po piatich znakoch (`A7K2M-9QXBF`) a do adresy sa posiela
 * tak, ako ho človek napísal — pomlčky, medzery ani veľkosť písmen backend
 * nezaujímajú (App\Support\BoardToken::normalize).
 */

const TOKEN_LENGTH = 10

/** `A7K2M9QXBF` → `A7K2M-9QXBF`. Kratší reťazec vráti bez zmeny. */
export function formatBoardToken(token: string): string {
  const clean = normalizeBoardToken(token)

  return clean.length === TOKEN_LENGTH ? `${clean.slice(0, 5)}-${clean.slice(5)}` : token
}

/** Čokoľvek, čo človek naťukal, na tvar do adresy. */
export function normalizeBoardToken(raw: string): string {
  return (raw ?? '').replace(/[^A-Za-z0-9]/g, '').toUpperCase()
}
