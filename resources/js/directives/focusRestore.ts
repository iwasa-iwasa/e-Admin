import type { Directive } from 'vue'
import { useFocusMemory } from '@/composables/useFocusMemory'

const { attachListener, restoreFocus } = useFocusMemory()

export const vFocusRestore: Directive = {
  mounted() {
    attachListener()
  },
  updated(el, binding) {
    if (binding.oldValue === true && binding.value === false) {
      restoreFocus()
    }
  }
}
