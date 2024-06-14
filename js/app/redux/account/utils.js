import { initialState } from './slice'

export function getAccountInitialState() {
  const hasAccount = window._auth && window._auth.isAuth
  const accessToken = hasAccount ? window._auth.jwt : null

  return ({
    ...initialState,
    accessToken: accessToken,
  })
}
