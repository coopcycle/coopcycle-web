const datadogEnabled = Boolean(window.DD_LOGS)

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

    window.DD_LOGS.logger.debug(message, context)
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

    window.DD_LOGS.logger.info(message, context)
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

    window.DD_LOGS.logger.warn(message, context)
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

    window.DD_LOGS.logger.error(message, context)
  },
}

window.DatadogLogger = DatadogLogger
