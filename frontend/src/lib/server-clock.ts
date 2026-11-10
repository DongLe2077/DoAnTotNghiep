// Anchor defaults to a successful same-origin API response, so a fast device clock
// cannot turn an event entered as "now" into a future transaction on the server.
let anchor: { epoch: number; monotonic: number } | null = null

export function synchronizeServerClock(header: unknown) {
  if (typeof header !== 'string' || !header.endsWith(' GMT')) return
  const epoch = Date.parse(header)
  if (!Number.isFinite(epoch)) return
  anchor = { epoch, monotonic: performance.now() }
}

export function serverNow(): Date {
  if (!anchor) return new Date()
  return new Date(anchor.epoch + Math.max(0, performance.now() - anchor.monotonic))
}
