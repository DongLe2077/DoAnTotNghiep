import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

describe('server time for event defaults', () => {
  beforeEach(() => { vi.resetModules() })
  afterEach(() => { vi.useRealTimers(); vi.restoreAllMocks() })

  it('uses the device clock until an API time has been received', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-01T11:00:45Z'))
    const { serverNow } = await import('./server-clock')
    expect(serverNow().toISOString()).toBe('2026-11-01T11:00:45.000Z')
  })

  it('keeps defaults within server time when the device is fast or its clock changes', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-01T11:00:45Z'))
    const clock = vi.spyOn(performance, 'now').mockReturnValue(1000)
    const { synchronizeServerClock, serverNow } = await import('./server-clock')
    synchronizeServerClock('Sun, 01 Nov 2026 11:00:00 GMT')
    clock.mockReturnValue(4000)
    vi.setSystemTime(new Date('2026-11-02T11:00:45Z'))
    expect(serverNow().toISOString()).toBe('2026-11-01T11:00:03.000Z')
  })

  it('ignores missing or malformed headers without losing the last known server time', async () => {
    vi.spyOn(performance, 'now').mockReturnValue(1000)
    const { synchronizeServerClock, serverNow } = await import('./server-clock')
    synchronizeServerClock('Sun, 01 Nov 2026 11:00:00 GMT')
    for (const invalid of [undefined, null, {}, 'bad GMT', '2026-11-01']) synchronizeServerClock(invalid)
    expect(serverNow().toISOString()).toBe('2026-11-01T11:00:00.000Z')
  })
})
