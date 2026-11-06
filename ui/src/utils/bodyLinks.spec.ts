import { describe, expect, it } from 'vitest'
import { enhanceBodyLinks, linkKind } from './bodyLinks'

const label = (kind: string) => `[${kind}]`

describe('linkKind', () => {
  it.each([
    ['https://docs.google.com/forms/d/e/1FAIpQ/viewform', 'form'],
    ['https://forms.gle/abc', 'form'],
    ['https://docs.google.com/document/d/1/edit', 'document'],
    ['https://docs.google.com/spreadsheets/d/1/edit', 'spreadsheet'],
    ['https://example.sk/files/program.PDF', 'pdf'],
    ['https://drive.google.com/file/d/1/view', 'file'],
    ['https://youtu.be/xyz', 'video'],
    ['https://maps.app.goo.gl/xyz', 'map'],
    ['https://us02web.zoom.us/j/123', 'meeting'],
    ['https://www.farnost.sk/oznamy', null],
  ])('%s → %s', (href, kind) => {
    expect(linkKind(href)).toBe(kind)
  })
})

describe('enhanceBodyLinks', () => {
  it('holú URL v texte zmení na krátky odkaz a odreže bodku za vetou', () => {
    const html = enhanceBodyLinks('<p>Registrácia: https://docs.google.com/forms/d/e/1FAIpQ/viewform.</p>', label)
    expect(html).toBe(
      '<p>Registrácia: <a href="https://docs.google.com/forms/d/e/1FAIpQ/viewform" target="_blank" rel="noopener noreferrer" title="https://docs.google.com/forms/d/e/1FAIpQ/viewform">[form]</a>.</p>',
    )
  })

  it('neznámy typ zobrazí doménu, www. doplní protokol', () => {
    const html = enhanceBodyLinks('<p>Viac na www.farnost.sk/oznamy</p>', label)
    expect(html).toContain('href="https://www.farnost.sk/oznamy"')
    expect(html).toContain('>farnost.sk</a>')
  })

  it('odkaz s vlastným textom ponechá', () => {
    const html = enhanceBodyLinks('<p><a href="https://forms.gle/abc">prihláste sa tu</a></p>', label)
    expect(html).toContain('>prihláste sa tu</a>')
    expect(html).toContain('target="_blank"')
  })

  it('odkaz, ktorého text je URL, skráti', () => {
    const html = enhanceBodyLinks('<p><a href="https://youtu.be/xyz">https://youtu.be/xyz</a></p>', label)
    expect(html).toContain('>[video]</a>')
  })

  it('mailto a URL v kóde nechá tak', () => {
    const html = enhanceBodyLinks('<p><a href="mailto:a@b.sk">a@b.sk</a> <code>https://x.sk</code></p>', label)
    expect(html).toBe('<p><a href="mailto:a@b.sk">a@b.sk</a> <code>https://x.sk</code></p>')
  })
})
