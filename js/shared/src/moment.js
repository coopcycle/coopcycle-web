import Moment from 'moment'
import 'moment-timezone'
import { extendMoment } from 'moment-range'

const moment = extendMoment(Moment)

export default moment
