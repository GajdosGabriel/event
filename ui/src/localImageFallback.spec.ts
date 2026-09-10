import { describe, expect, it } from 'vitest'
import { installLocalImageFallback } from './localImageFallback'

installLocalImageFallback()

describe('local image fallback', () => {
  it('retries a failed production image in dev, clearing srcset, only once', () => {
    const image = document.createElement('img')
    const dev = 'https://images.example.test/dev/event/thumb.jpg'
    image.src = `https://images.example.test/prod/event/thumb.jpg#local-image-fallback=${encodeURIComponent(dev)}`
    image.srcset = `${image.src} 320w`
    document.body.append(image)
    image.dispatchEvent(new Event('error'))
    expect(image.src).toBe(dev)
    expect(image.hasAttribute('srcset')).toBe(false)
    image.dispatchEvent(new Event('error'))
    expect(image.src).toBe(dev)
    image.remove()
  })

  it('leaves normal images and successful production images unchanged', () => {
    const image = document.createElement('img')
    image.src = 'https://images.example.test/prod/image.jpg'
    document.body.append(image)
    image.dispatchEvent(new Event('error'))
    expect(image.src).toBe('https://images.example.test/prod/image.jpg')
    image.src += '#local-image-fallback=https%3A%2F%2Fimages.example.test%2Fdev%2Fimage.jpg'
    const original = image.src
    image.dispatchEvent(new Event('load'))
    expect(image.src).toBe(original)
    image.remove()
  })
})
