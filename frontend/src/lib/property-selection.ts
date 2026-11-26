// Per-tab selection; memory fallback also supports storage-restricted browsers.
let fallback: string | null = null
export function selectedProperty(): string | null {
  try { return typeof sessionStorage === 'undefined' ? fallback : sessionStorage.getItem('rentbase.property') }
  catch { return fallback }
}
export function selectProperty(id: number | null): void {
  fallback = id !== null && Number.isSafeInteger(id) && id > 0 ? String(id) : null
  try {
    if (typeof sessionStorage === 'undefined') return
    if (fallback) sessionStorage.setItem('rentbase.property', fallback)
    else sessionStorage.removeItem('rentbase.property')
  } catch { /* Keep selection in this tab's memory if browser storage is unavailable. */ }
}
