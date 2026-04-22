import { ref } from 'vue'

// Module-level so state is shared across layout + page components
export const sidebarSections = ref([])
export const sidebarActive   = ref('')

export function useSidebar() {
  function setSections(sections) {
    sidebarSections.value = sections
    if (sections.length && !sections.find(s => s.id === sidebarActive.value)) {
      sidebarActive.value = sections[0]?.id ?? ''
    }
  }
  function setActive(id) {
    sidebarActive.value = id
  }
  function clearSections() {
    sidebarSections.value = []
    sidebarActive.value   = ''
  }
  return { sidebarSections, sidebarActive, setSections, setActive, clearSections }
}
