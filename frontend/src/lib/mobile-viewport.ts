export function initializeMobileViewport() {
  const viewport = window.visualViewport
  function update() {
    const height = viewport?.height ?? window.innerHeight
    document.documentElement.style.setProperty('--app-viewport-height', `${height}px`)
    document.documentElement.style.setProperty('--visual-top', `${viewport?.offsetTop ?? 0}px`)
    const editing = document.activeElement?.matches('input, textarea, select, [contenteditable="true"]')
    const keyboard = !!editing && window.innerHeight - height > 150
    document.body.classList.toggle('keyboard-open', keyboard)
    if (keyboard) requestAnimationFrame(() => document.activeElement?.scrollIntoView({ block: 'nearest' }))
  }
  viewport?.addEventListener('resize', update)
  viewport?.addEventListener('scroll', update)
  window.addEventListener('resize', update)
  document.addEventListener('focusin', update)
  document.addEventListener('focusout', () => requestAnimationFrame(update))
  update()
}
