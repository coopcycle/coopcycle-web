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

  const load = () => {
    let logs, rum
    jest.isolateModules(() => {
      logs = require('@datadog/browser-logs').datadogLogs
      rum = require('@datadog/browser-rum').datadogRum
      require('../datadog')
    })
    return { logs, rum }
  }

  it('uses the instance name as service and env', () => {
    document.body.innerHTML = `<div id="datadog"
      data-client-token="pub123"
      data-application-id="app123"
      data-service="libelubike"
      data-env="libelubike"></div>`

    const { logs, rum } = load()

    const expected = expect.objectContaining({ service: 'libelubike', env: 'libelubike' })
    expect(logs.init).toHaveBeenCalledWith(expected)
    expect(rum.init).toHaveBeenCalledWith(expected)
  })

  it('does nothing without a client token', () => {
    const { logs, rum } = load()

    expect(logs.init).not.toHaveBeenCalled()
    expect(rum.init).not.toHaveBeenCalled()
  })
})
