const user = JSON.parse(document.head.querySelector('meta[name="application-auth-user"]').content)
const jwt = document.head.querySelector('meta[name="application-auth-jwt"]').content
window['_auth'] = {
  isAuth: user !== null,
  jwt: jwt !== '' ? jwt : null,
  user
}
