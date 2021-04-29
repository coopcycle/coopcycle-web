export const updateQueryString = ({ getState }) => {

  return next => action => {

    const result = next(action)
    const state = getState()

    window.history.replaceState(
      {},
      document.title,
      window.Routing.generate('admin_metrics', {
        view: state.view,
        range: state.dateRange
      })
    )

    return result
  }
}
