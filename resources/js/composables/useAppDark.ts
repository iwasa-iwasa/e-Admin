import { useDark, useToggle } from '@vueuse/core'

const _isDark = useDark({
  selector: 'html',
  attribute: 'class',
  valueDark: 'dark',
  valueLight: '',
})

const _toggleDark = useToggle(_isDark)

export const isDark = _isDark

export const toggleDark = () => {
  if (!document.startViewTransition) {
    _toggleDark()
    return
  }
  
  document.startViewTransition(() => {
    _toggleDark()
  })
}
