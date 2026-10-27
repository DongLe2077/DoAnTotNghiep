// Keep keyboard navigation inside an open modal, including at the first and last control.
export function keepFocusInDialog(event: KeyboardEvent) {
  if (event.key !== 'Tab') return
  const dialog = event.currentTarget
  if (!(dialog instanceof HTMLDialogElement) || !dialog.open) return
  const controls = [...dialog.querySelectorAll<HTMLElement>(
    'button:not([disabled]), a[href], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )].filter(control => control.getClientRects().length > 0 && !control.matches(':disabled'))
  const first = controls[0]
  const last = controls[controls.length - 1]
  if (!first || !last) { event.preventDefault(); dialog.focus(); return }
  const active = document.activeElement
  if (event.shiftKey && (active === first || !dialog.contains(active))) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && (active === last || !dialog.contains(active))) {
    event.preventDefault()
    first.focus()
  }
}
