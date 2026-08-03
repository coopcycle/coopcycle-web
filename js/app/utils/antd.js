import moment from 'moment'
import dayjs from 'dayjs'
import 'dayjs/locale/ca'
import 'dayjs/locale/da'
import 'dayjs/locale/de'
import 'dayjs/locale/en'
import 'dayjs/locale/es'
import 'dayjs/locale/eu'
import 'dayjs/locale/fr'
import 'dayjs/locale/hu'
import 'dayjs/locale/it'
import 'dayjs/locale/pl'
import 'dayjs/locale/pt'
import 'dayjs/locale/pt-br'
import { ConfigProvider, theme } from 'antd'
import { antdLocale } from '../i18n'
import React from 'react'

const htmlLang = $('html').attr('lang')

moment.locale(htmlLang)

// dayjs locale codes are lowercase and dash-separated (e.g "pt-br"),
// unlike our Symfony locale codes (e.g "pt_BR")
const dayjsLocaleMap = { pt_BR: 'pt-br', pt_PT: 'pt' }
dayjs.locale(dayjsLocaleMap[htmlLang] || (htmlLang || 'en').toLowerCase())

const longDateFormat = moment.localeData().longDateFormat('LT')

export const timePickerProps = {
  format: longDateFormat,
  // This works automatically based on "format" in ant.design 4.x,
  // but in 3.x we have to explicitly pass "use12Hours" as boolean
  // https://github.com/ant-design/ant-design/blob/9ecb12db768cd6782e82a4cf8a52958dcd164c9c/components/date-picker/generatePicker/index.tsx#L50-L52
  use12Hours: longDateFormat.includes('a') || longDateFormat.includes('A'),
}

// rc-picker defaults DatePicker/RangePicker to "YYYY-MM-DD" regardless of
// locale unless a "format" is passed explicitly (its own locale files no
// longer drive the displayed format, only the calendar panel chrome)
export const datePickerProps = {
  format: moment.localeData().longDateFormat('L'),
}

export const AntdConfigProvider = ({ children }) => {
  return (
    <ConfigProvider
      theme={{
        algorithm: theme.defaultAlgorithm,
        token: {
          // Seed Token
          //TODO: switch to CoopCycle Brand Red
          // colorPrimary: '#F05A58',
        },
      }}
      locale={antdLocale}>
      {children}
    </ConfigProvider>
  )
}
