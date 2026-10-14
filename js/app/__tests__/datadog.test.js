jest.mock('@datadog/browser-logs', () => ({
  datadogLogs: { init: jest.fn(), logger: {} },
}))

jest.mock('@datadog/browser-rum', () => ({
  datadogRum: { init: jest.fn() },
}))

describe('datadog', () => {

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('tags logs & RUM events with the deployed version', () => {
    document.body.innerHTML =
      '<div id="datadog" data-client-token="pubtoken" data-application-id="app-id" data-env="libelubike" data-version="b367781db"></div>'

    let datadogLogs, datadogRum
    jest.isolateModules(() => {
      ;({ datadogLogs } = require('@datadog/browser-logs'))
      ;({ datadogRum } = require('@datadog/browser-rum'))
      require('../datadog')
    })

    // "service", "env" & "version" are Datadog's unified service tagging
    expect(datadogLogs.init).toHaveBeenCalledWith(expect.objectContaining({
      service: 'frontend',
      env: 'libelubike',
      version: 'b367781db',
    }))

    expect(datadogRum.init).toHaveBeenCalledWith(expect.objectContaining({
      service: 'frontend',
      env: 'libelubike',
      version: 'b367781db',
    }))
  })

  it('does nothing without the #datadog element', () => {
    let datadogLogs, datadogRum
    jest.isolateModules(() => {
      ;({ datadogLogs } = require('@datadog/browser-logs'))
      ;({ datadogRum } = require('@datadog/browser-rum'))
      require('../datadog')
    })

    expect(datadogLogs.init).not.toHaveBeenCalled()
    expect(datadogRum.init).not.toHaveBeenCalled()
  })
})
