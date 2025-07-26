import { datadogLogs, Logger } from '@datadog/browser-logs'
import { datadogRum } from '@datadog/browser-rum'

const el = document.getElementById('datadog')
const clientToken = el?.dataset.clientToken
const datadogEnabled = Boolean(clientToken)

// regex patterns to identify known bot instances:
// see https://docs.datadoghq.com/real_user_monitoring/guide/identify-bots-in-the-ui/#filter-out-bot-sessions-on-intake
const botPattern =
  '(googlebot|bot|Googlebot-Mobile|Googlebot-Image|Google favicon|Mediapartners-Google|bingbot|slurp|java|wget|curl|Commons-HttpClient|Python-urllib|libwww|httpunit|nutch|phpcrawl|msnbot|jyxobot|FAST-WebCrawler|FAST Enterprise Crawler|biglotron|teoma|convera|seekbot|gigablast|exabot|ngbot|ia_archiver|GingerCrawler|webmon |httrack|webcrawler|grub.org|UsineNouvelleCrawler|antibot|netresearchserver|speedy|fluffy|bibnum.bnf|findlink|msrbot|panscient|yacybot|AISearchBot|IOI|ips-agent|tagoobot|MJ12bot|dotbot|woriobot|yanga|buzzbot|mlbot|yandexbot|purebot|Linguee Bot|Voyager|CyberPatrol|voilabot|baiduspider|citeseerxbot|spbot|twengabot|postrank|turnitinbot|scribdbot|page2rss|sitebot|linkdex|Adidxbot|blekkobot|ezooms|dotbot|Mail.RU_Bot|discobot|heritrix|findthatfile|europarchive.org|NerdByNature.Bot|sistrix crawler|ahrefsbot|Aboundex|domaincrawler|wbsearchbot|summify|ccbot|edisterbot|seznambot|ec2linkfinder|gslfbot|aihitbot|intelium_bot|facebookexternalhit|yeti|RetrevoPageAnalyzer|lb-spider|sogou|lssbot|careerbot|wotbox|wocbot|ichiro|DuckDuckBot|lssrocketcrawler|drupact|webcompanycrawler|acoonbot|openindexspider|gnam gnam spider|web-archive-net.com.bot|backlinkcrawler|coccoc|integromedb|content crawler spider|toplistbot|seokicks-robot|it2media-domain-crawler|ip-web-crawler.com|siteexplorer.info|elisabot|proximic|changedetection|blexbot|arabot|WeSEE:Search|niki-bot|CrystalSemanticsBot|rogerbot|360Spider|psbot|InterfaxScanBot|Lipperhey SEO Service|CC Metadata Scaper|g00g1e.net|GrapeshotCrawler|urlappendbot|brainobot|fr-crawler|binlar|SimpleCrawler|Livelapbot|Twitterbot|cXensebot|smtbot|bnf.fr_bot|A6-Indexer|ADmantX|Facebot|Twitterbot|OrangeBot|memorybot|AdvBot|MegaIndex|SemanticScholarBot|ltx71|nerdybot|xovibot|BUbiNG|Qwantify|archive.org_bot|Applebot|TweetmemeBot|crawler4j|findxbot|SemrushBot|yoozBot|lipperhey|y!j-asr|Domain Re-Animator Bot|AddThis)'

const regex = new RegExp(botPattern, 'i')
const isBot = regex.test(navigator.userAgent)

if (clientToken) {
  datadogLogs.init({
    clientToken: clientToken,
    site: 'datadoghq.com',
    service: el.dataset.service,
    forwardErrorsToLogs: true,
    // Only tracked sessions send logs; from 0 to 100
    sessionSampleRate: isBot ? 0 : 100,
    telemetrySampleRate: 0,
  })

  datadogRum.init({
    applicationId: el.dataset.applicationId,
    clientToken: clientToken,
    site: 'datadoghq.com',
    service: el.dataset.service,
    // Specify a version number to identify the deployed version of your application in Datadog
    // version: '1.0.0',
    // 'Browser RUM' session sample rate; from 0 to 100
    sessionSampleRate: isBot ? 0 : 5,
    // 'Browser RUM & Session Replay' sample rate (% from sessions tracked by RUM/sessionSampleRate); from 0 to 100
    sessionReplaySampleRate: isBot ? 0 : 10,
    telemetrySampleRate: 0,
    trackUserInteractions: true,
    trackResources: true,
    trackLongTasks: true,
    defaultPrivacyLevel: 'mask-user-input',
  })
}

const DatadogLogger: Logger = {
  /**
   * Send a log with debug level.
   */
  debug(message: string, context?: object): void {
    if (!datadogEnabled) {
      console.debug('DatadogLogger (dry run): ' + message, context)
      return
    }

    datadogLogs.logger.debug(message, context)
  },

  /**
   * Send a log with info level.
   */
  info(message: string, context?: object): void {
    if (!datadogEnabled) {
      console.info('DatadogLogger (dry run): ' + message, context)
      return
    }

    datadogLogs.logger.info(message, context)
  },

  /**
   * Send a log with warn level.
   */
  warn(message: string, context?: object): void {
    if (!datadogEnabled) {
      console.warn('DatadogLogger (dry run): ' + message, context)
      return
    }

    datadogLogs.logger.warn(message, context)
  },

  /**
   * Send a log with error level.
   */
  error(message: string, context?: object): void {
    if (!datadogEnabled) {
      console.error('DatadogLogger (dry run): ' + message, context)
      return
    }

    datadogLogs.logger.error(message, context)
  },
}

window.DatadogLogger = DatadogLogger
