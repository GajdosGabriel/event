import { describe, expect, it } from 'vitest'
import { formatBoardToken, normalizeBoardToken } from './boardToken'

describe('boardToken', () => {
  it('rozdelí token na dve päťice', () => {
    expect(formatBoardToken('A7K2M9QXBF')).toBe('A7K2M-9QXBF')
  })

  it('zobrazený tvar sa dá naformátovať znovu bez zmeny', () => {
    expect(formatBoardToken('A7K2M-9QXBF')).toBe('A7K2M-9QXBF')
  })

  it('nechá neúplný token tak, ako prišiel', () => {
    expect(formatBoardToken('A7K2M')).toBe('A7K2M')
  })

  it('z prepísaného kódu spraví tvar do adresy', () => {
    expect(normalizeBoardToken(' a7k2m-9qx bf ')).toBe('A7K2M9QXBF')
  })
})
