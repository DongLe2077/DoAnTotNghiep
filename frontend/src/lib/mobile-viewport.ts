export function initializeMobileViewport() {
  const viewport = window.visualViewport
  let revealFrame = 0
  function update() {
    const height = viewport?.height ?? window.innerHeight
    const top = viewport?.offsetTop ?? 0
    document.documentElement.style.setProperty('--app-viewport-height', `${height}px`)
    document.documentElement.style.setProperty('--visual-top', `${top}px`)
    const field = document.activeElement
    const editing = field instanceof HTMLElement && field.matches('input, textarea, select, [contenteditable="true"]')
    const keyboard = editing && window.innerHeight - height * (viewport?.scale ?? 1) > 150
    document.body.classList.toggle('keyboard-open', keyboard)
    cancelAnimationFrame(revealFrame)
    if (!keyboard) return
    revealFrame = requestAnimationFrame(() => {
      if (document.activeElement !== field) return
      const box = field.getBoundingClientRect()
      const content = field.closest('.ui-dialog .content')?.getBoundingClientRect()
      const header = document.querySelector('.rb7-top, .topbar')?.getBoundingClientRect()
      const visibleTop = Math.max(top, content?.top ?? header?.bottom ?? top) + 8
      const visibleBottom = Math.min(top + height, content?.bottom ?? top + height) - 8
      if (box.top < visibleTop || box.bottom > visibleBottom) field.scrollIntoView({ block: 'nearest', behavior: 'instant' })
    })
  }
  viewport?.addEventListener('resize', update)
  viewport?.addEventListener('scroll', update)
  window.addEventListener('resize', update)
  document.addEventListener('focusin', update)
  document.addEventListener('focusout', () => requestAnimationFrame(update))
  update()
}
