/// <reference lib="webworker" />
import { clientsClaim, setCacheNameDetails } from 'workbox-core'
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching'
import { NavigationRoute, registerRoute } from 'workbox-routing'

declare const self: ServiceWorkerGlobalScope & { __WB_MANIFEST: Array<{ url: string; revision: string | null }> }
setCacheNameDetails({ prefix: 'nhatro-public', suffix: 'v1' })
// Only build-time public files. There is deliberately no runtime cache or write queue.
precacheAndRoute(self.__WB_MANIFEST.filter(entry => /^(?:\/?index\.html|\/?favicon\.svg|\/?assets\/[^?]+\.(?:js|css)|\/?fonts\/[^?]+\.woff2|\/?icons\/[^?]+\.(?:png|svg))$/.test(typeof entry === 'string' ? entry : entry.url)))
cleanupOutdatedCaches()
registerRoute(new NavigationRoute(createHandlerBoundToURL('/index.html'), {
  allowlist: [/^\/(?:$|login(?:\?|$)|account(?:\?|$)|notifications(?:\?|$)|forbidden(?:\?|$)|connection-error(?:\?|$)|owner(?:\/|\?|$)|tenant(?:\/|\?|$))/],
}))
clientsClaim()

let applying = false
function prepare(client: Client, token: string): Promise<boolean> {
  return new Promise(resolve => {
    const channel = new MessageChannel()
    const finish = (ready: boolean) => { clearTimeout(timer); channel.port1.close(); resolve(ready) }
    const timer = setTimeout(() => finish(false), 4000)
    channel.port1.onmessage = event => finish(event.data?.ready === true)
    client.postMessage({ type: 'PWA_PREPARE_UPDATE', token }, [channel.port2])
  })
}
self.addEventListener('message', event => {
  if (event.data?.type !== 'PWA_APPLY_UPDATE' || !event.ports[0]) return
  const reply = event.ports[0]
  event.waitUntil((async () => {
    if (applying) { reply.postMessage({ ready: false }); return }
    applying = true
    const token = crypto.randomUUID()
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    try {
      const sender = event.source && 'id' in event.source ? event.source.id : null
      if (!clients.length || !clients.some(client => client.id === sender) || !(await Promise.all(clients.map(client => prepare(client, token)))).every(Boolean)) {
        clients.forEach(client => client.postMessage({ type: 'PWA_RELEASE_UPDATE', token }))
        reply.postMessage({ ready: false }); return
      }
      reply.postMessage({ ready: true })
      await self.skipWaiting()
    } catch {
      clients.forEach(client => client.postMessage({ type: 'PWA_RELEASE_UPDATE', token }))
      reply.postMessage({ ready: false })
    } finally { applying = false; reply.close() }
  })())
})
