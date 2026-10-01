import { describe, it, expect } from 'vitest'
import { uploadRejection, MAX_UPLOAD_BYTES } from './uploadFileTypes'

function file(name: string, type: string, size: number): File {
  const f = new File(['x'], name, { type })
  Object.defineProperty(f, 'size', { value: size })
  return f
}

describe('uploadRejection', () => {
  it('11 MB súbor je príliš veľký', () => {
    expect(uploadRejection(file('a.jpg', 'image/jpeg', 11 * 1024 * 1024))).toBe('tooLarge')
  })
  it('.exe je nepovolený typ', () => {
    expect(uploadRejection(file('a.exe', 'application/x-msdownload', 1000))).toBe('type')
  })
  it('povolený súbor do limitu prejde', () => {
    expect(uploadRejection(file('a.png', 'image/png', MAX_UPLOAD_BYTES))).toBeNull()
    expect(uploadRejection(file('a.pdf', 'application/pdf', 1000))).toBeNull()
  })
})
