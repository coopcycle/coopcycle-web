import moment from 'moment'
import { ConfigProvider, theme } from 'antd'
import { antdLocale } from '../i18n'
import React, { useState, useEffect } from 'react'

moment.locale($('html').attr('lang'))

const longDateFormat = moment.localeData().longDateFormat('LT')

export const timePickerProps = {
  format: longDateFormat,
  // This works automatically based on "format" in ant.design 4.x,
  // but in 3.x we have to explicitly pass "use12Hours" as boolean
  // https://github.com/ant-design/ant-design/blob/9ecb12db768cd6782e82a4cf8a52958dcd164c9c/components/date-picker/generatePicker/index.tsx#L50-L52
  use12Hours: longDateFormat.includes('a') || longDateFormat.includes('A'),
}

export const AntdConfigProvider = ({ children }) => {
  const [isDark, setIsDark] = useState(
    () => window.matchMedia('(prefers-color-scheme: dark)').matches
  )

  useEffect(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    const handler = (e) => setIsDark(e.matches)
    mq.addEventListener('change', handler)
    return () => mq.removeEventListener('change', handler)
  }, [])

  return (
    <ConfigProvider
      theme={{
        algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
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
