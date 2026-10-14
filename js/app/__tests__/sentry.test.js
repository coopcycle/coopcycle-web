jest.mock('@sentry/browser', () => ({
  init: jest.fn(),
  browserTracingIntegration: jest.fn(),
  replayIntegration: jest.fn(),
}))

describe('sentry', () => {

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('tags errors with the app name & the deployed release', () => {
    document.body.innerHTML = '<div id="sentry" data-dsn="https://key@sentry.example/1" data-app-name="libelubike" data-release="b367781db"></div>'

    let Sentry
    jest.isolateModules(() => {
      Sentry = require('@sentry/browser')
      require('../sentry')
    })

    expect(Sentry.init).toHaveBeenCalledWith(expect.objectContaining({
      dsn: 'https://key@sentry.example/1',
      release: 'b367781db',
      initialScope: {
        tags: { coopcycle_app_name: 'libelubike' },
      },
    }))
  })

  it('does nothing without the #sentry element', () => {
    let Sentry
    jest.isolateModules(() => {
      Sentry = require('@sentry/browser')
      require('../sentry')
    })

    expect(Sentry.init).not.toHaveBeenCalled()
  })
})
