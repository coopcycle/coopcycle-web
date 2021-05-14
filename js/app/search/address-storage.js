import engine  from 'store/src/store-engine'
import session from 'store/storages/sessionStorage'
import cookie  from 'store/storages/cookieStorage'

export default engine.createStore([ session, cookie ])
