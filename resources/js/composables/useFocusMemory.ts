let lastFocusedElement: HTMLElement | null = null
let listenerAttached = false

const rememberFocus = (event: FocusEvent) => {
  const target = event.target as HTMLElement
  if (
    target instanceof HTMLInputElement ||
    target instanceof HTMLTextAreaElement ||
    target instanceof HTMLSelectElement
  ) {
    lastFocusedElement = target
  }
}

const restoreFocus = () => {
  if (lastFocusedElement && document.contains(lastFocusedElement)) {
    requestAnimationFrame(() => {
      lastFocusedElement?.focus()
    })
  }
}

const attachListener = () => {
  if (!listenerAttached) {
    document.addEventListener('focusin', rememberFocus, true)
    listenerAttached = true
  }
}

export function useFocusMemory() {
  return {
    attachListener,
    restoreFocus
  }
}
