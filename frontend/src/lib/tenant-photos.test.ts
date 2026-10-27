import { describe, expect, it } from 'vitest'
import { photoSelectionError } from './tenant-photos'
const photo = { name: 'su-co.jpg', type: 'image/jpeg', size: 1024 }
describe('tenant photo selection limits', () => {
  it('accepts supported photos and the exact size limit', () => {
    expect(photoSelectionError([photo])).toBe('')
    expect(photoSelectionError([{ ...photo, size: 5 * 1024 * 1024 }])).toBe('')
  })
  it('enforces capacity including existing photos', () => {
    expect(photoSelectionError([photo, photo], 4)).toContain('tối đa 1 ảnh')
    expect(photoSelectionError(Array(6).fill(photo))).toContain('tối đa 5 ảnh')
  })
  it('rejects SVG, misleading extensions, empty or oversized files', () => {
    expect(photoSelectionError([{ ...photo, name: 'su-co.svg', type: 'image/svg+xml' }])).not.toBe('')
    expect(photoSelectionError([{ ...photo, name: 'su-co.exe' }])).not.toBe('')
    expect(photoSelectionError([{ ...photo, size: 0 }])).not.toBe('')
    expect(photoSelectionError([{ ...photo, size: 5 * 1024 * 1024 + 1 }])).not.toBe('')
  })
})
