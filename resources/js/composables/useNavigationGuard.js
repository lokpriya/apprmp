import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Navigation guard that warns the user before leaving a page with unsaved changes.
 *
 * @param {() => boolean} isDirtyFn  - Returns true when there are unsaved changes.
 * @returns {{ showLeaveModal, confirmLeave, cancelLeave, bypassForSubmit }}
 *
 * Call bypassForSubmit() immediately before the form's own save/submit to let
 * the Inertia navigation through without showing the leave modal.
 */
export function useNavigationGuard(isDirtyFn) {
  const showLeaveModal = ref(false)
  let pendingVisit     = null
  let bypassGuard      = false

  function handleBeforeUnload(e) {
    if (isDirtyFn()) {
      e.preventDefault()
      e.returnValue = ''
    }
  }

  let removeGuard = null

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
    removeGuard = router.on('before', (event) => {
      if (bypassGuard || !isDirtyFn()) return
      pendingVisit         = event.detail.visit
      showLeaveModal.value = true
      return false
    })
  })

  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    removeGuard?.()
  })

  function confirmLeave() {
    showLeaveModal.value = false
    bypassGuard          = true
    if (pendingVisit) {
      router.visit(pendingVisit.url, {
        method:   pendingVisit.method   ?? 'get',
        data:     pendingVisit.data     ?? {},
        onFinish: () => { bypassGuard = false },
      })
      pendingVisit = null
    } else {
      bypassGuard = false
    }
  }

  function cancelLeave() {
    showLeaveModal.value = false
    pendingVisit         = null
  }

  // Call this just before form.put() / form.post() / router.visit() for the
  // form's own submission so the guard does not intercept it.
  function bypassForSubmit() {
    bypassGuard = true
  }

  return { showLeaveModal, confirmLeave, cancelLeave, bypassForSubmit }
}
