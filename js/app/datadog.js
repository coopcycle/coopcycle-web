import { datadogLogs } from '@datadog/browser-logs'
import { datadogRum } from '@datadog/browser-rum'

const el = document.getElementById('datadog');
const datadogEnabled = Boolean(el)

if (datadogEnabled) {
  datadogLogs.init({
    clientToken: el.dataset.clientToken,
    site: 'datadoghq.com',
    service: el.dataset.service,
    forwardErrorsToLogs: true,
    // Only tracked sessions send logs.
    sessionSampleRate: 100,
    telemetrySampleRate: 0,
  })

  datadogRum.init({
    applicationId: el.dataset.applicationId,
    clientToken: el.dataset.clientToken,
    site: 'datadoghq.com',
    service: el.dataset.service,
    // Specify a version number to identify the deployed version of your application in Datadog
    // version: '1.0.0',
    // 'Browser RUM' session sample rate
    sessionSampleRate: 1,
    // 'Browser RUM & Session Replay' sample rate (% from sessions tracked by RUM/sessionSampleRate)
    sessionReplaySampleRate: 10,
    telemetrySampleRate: 0,
    trackUserInteractions: true,
    trackResources: true,
    trackLongTasks: true,
    defaultPrivacyLevel: 'mask-user-input',
  });

}

const DatadogLogger = {
  /**
   * Send a log with debug level.
   * @param message: The message to send.
   * @param context: The additional context to send.
   */
  debug(message, context) {
    if (!datadogEnabled) {
      console.debug(message, context)
      return
    }

    datadogLogs.logger.debug(message, context)
  },

  /**
   * Send a log with info level.
   * @param message: The message to send.
   * @param context: The additional context to send.
   */
  info(message, context) {
    if (!datadogEnabled) {
      console.info(message, context)
      return
    }

    datadogLogs.logger.info(message, context)
  },

  /**
   * Send a log with warn level.
   * @param message: The message to send.
   * @param context: The additional context to send.
   */
  warn(message, context) {
    if (!datadogEnabled) {
      console.warn(message, context)
      return
    }

    datadogLogs.logger.warn(message, context)
  },

  /**
   * Send a log with error level.
   * @param message: The message to send.
   * @param context: The additional context to send.
   */
  error(message, context) {
    if (!datadogEnabled) {
      console.error(message, context)
      return
    }

    datadogLogs.logger.error(message, context)
  },
}

window.DatadogLogger = DatadogLogger
