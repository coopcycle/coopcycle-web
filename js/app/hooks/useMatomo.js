export const useMatomo = () => {
  return {
    trackEvent: (category, action, name) => {
      if (!window._paq) {
        return
      }

      window._paq.push(['trackEvent', category, action, name])
    },
  }
}
