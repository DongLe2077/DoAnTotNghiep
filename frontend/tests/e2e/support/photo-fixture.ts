import { deflateSync } from 'node:zlib'
// A synthetic faucet illustration; never reads real user photos or external assets.
export function photoFixture(width = 240, height = 160) {
  function chunk(type: string, data: Buffer) {
    const payload = Buffer.concat([Buffer.from(type), data]), length = Buffer.alloc(4), crc = Buffer.alloc(4)
    length.writeUInt32BE(data.length)
    let value = 0xffffffff
    for (const byte of payload) { value ^= byte; for (let bit = 0; bit < 8; bit++) value = value & 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1 }
    crc.writeUInt32BE((value ^ 0xffffffff) >>> 0)
    return Buffer.concat([length, payload, crc])
  }
  const header = Buffer.alloc(13); header.writeUInt32BE(width); header.writeUInt32BE(height, 4); header[8] = 8; header[9] = 6
  const pixels = Buffer.alloc(height * (width * 4 + 1))
  for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
    const pipe = x > 65 && x < 85 && y > 35 && y < 105 || x > 65 && x < 168 && y > 45 && y < 65 || x > 150 && x < 168 && y > 45 && y < 85
    const water = (x - 159) ** 2 + (y - 109) ** 2 < 100 || x > 156 && x < 163 && y > 95 && y < 109
    const color = pipe ? [35, 92, 75] : water ? [70, 136, 142] : [228, 240, 234]
    const offset = y * (width * 4 + 1) + 1 + x * 4
    pixels[offset] = color[0]!; pixels[offset + 1] = color[1]!; pixels[offset + 2] = color[2]!; pixels[offset + 3] = 255
  }
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', header), chunk('IDAT', deflateSync(pixels)), chunk('IEND', Buffer.alloc(0))])
}
