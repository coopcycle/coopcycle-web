import {
  Chart,
  ArcElement,
  BarElement,
  BarController,
  BubbleController,
  DoughnutController,
  PieController,
  PointElement,
  CategoryScale,
  LinearScale,
  LogarithmicScale,
  Tooltip,
  Legend,
} from 'chart.js'

Chart.register(
  ArcElement,
  BarElement,
  BarController,
  BubbleController,
  DoughnutController,
  PieController,
  PointElement,
  CategoryScale,
  LinearScale,
  LogarithmicScale,
  Tooltip,
  Legend
)

// Bootstrap 3 contextual colours matching the panel styles in getSegmentMeta()
const SEGMENT_COLORS = {
  champions:            '#5cb85c',
  loyal_customers:      '#337ab7',
  potential_loyalists:  '#5bc0de',
  recent_customers:     '#5bc0de',
  promising:            '#5bc0de',
  at_risk:              '#f0ad4e',
  cant_lose_them:       '#d9534f',
  hibernating:          '#999',
  lost:                 '#d9534f',
}

const SCORE_COLORS = ['#d9534f', '#f0ad4e', '#5bc0de', '#5cb85c']

function formatCurrency(cents) {
  return (cents / 100).toFixed(2)
}

function renderPie(chartData, segmentMeta) {
  const canvas = document.getElementById('rfm-chart-pie')
  if (!canvas) return

  const keys    = Object.keys(segmentMeta)
  const counts  = keys.map(k => chartData.segment_counts[k] || 0)
  const colors  = keys.map(k => SEGMENT_COLORS[k] || '#ccc')
  const labels  = keys.map(k => segmentMeta[k].label || k)

  new Chart(canvas, {
    type: 'pie',
    data: {
      labels,
      datasets: [{ data: counts, backgroundColor: colors, borderWidth: 1 }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'right' },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed / counts.reduce((a, b) => a + b, 0) * 100)}%)`,
          },
        },
      },
    },
  })
}

function renderAvgSpend(chartData, segmentMeta) {
  const canvas = document.getElementById('rfm-chart-avg-spend')
  if (!canvas) return

  const entries = Object.entries(chartData.segment_avg_monetary)
    .sort((a, b) => b[1] - a[1])

  const labels  = entries.map(([k]) => segmentMeta[k]?.label || k)
  const values  = entries.map(([, v]) => formatCurrency(v))
  const colors  = entries.map(([k]) => SEGMENT_COLORS[k] || '#ccc')

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderWidth: 1,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          ticks: { callback: v => `${v}` },
        },
      },
    },
  })
}

function renderBounds(chartData) {
  const bounds = chartData.quartile_bounds
  if (!bounds) return

  const configs = [
    {
      id:    'rfm-chart-bounds-r',
      dim:   'r',
      label: 'days ago',
      fmt:   v => `${v} days`,
    },
    {
      id:    'rfm-chart-bounds-f',
      dim:   'f',
      label: 'orders',
      fmt:   v => `${v} orders`,
    },
    {
      id:    'rfm-chart-bounds-m',
      dim:   'm',
      label: '',
      fmt:   v => formatCurrency(v),
    },
  ]

  for (const { id, dim, fmt } of configs) {
    const canvas = document.getElementById(id)
    if (!canvas || !bounds[dim]) continue

    const scores = [1, 2, 3, 4]
    const data   = scores.map(s => bounds[dim][s] ? [Math.max(1, bounds[dim][s][0]), bounds[dim][s][1]] : null)

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: scores.map(s => `Score ${s}`),
        datasets: [{
          data,
          backgroundColor: SCORE_COLORS,
          borderWidth: 1,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => {
                const [min, max] = ctx.raw
                return ` ${fmt(min)} – ${fmt(max)}`
              },
            },
          },
        },
        scales: {
          x: { type: 'logarithmic', ticks: { callback: v => fmt(v) } },
        },
      },
    })
  }
}

function renderBubble(chartData) {
  const canvas = document.getElementById('rfm-chart-bubble')
  if (!canvas || !chartData.bubble_cells?.length) return

  const maxCount = Math.max(...chartData.bubble_cells.map(c => c.count))

  const datasets = chartData.bubble_cells.map(cell => ({
    label: cell.segment,
    data: [{
      x: cell.r,
      y: cell.f,
      r: Math.max(6, Math.sqrt(cell.count / maxCount) * 40),
    }],
    backgroundColor: (SEGMENT_COLORS[cell.segment] || '#ccc') + 'cc',
    borderColor:     SEGMENT_COLORS[cell.segment] || '#ccc',
    borderWidth: 1,
  }))

  new Chart(canvas, {
    type: 'bubble',
    data: { datasets },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => {
              const cell = chartData.bubble_cells.find(
                c => c.r === ctx.raw.x && c.f === ctx.raw.y
              )
              return cell ? ` ${cell.segment}: ${cell.count} customers` : ''
            },
          },
        },
      },
      scales: {
        x: {
          min: 0.5,
          max: 4.5,
          ticks: { stepSize: 1, callback: v => Number.isInteger(v) ? `R${v}` : '' },
          title: { display: true, text: 'Recency score' },
        },
        y: {
          min: 0.5,
          max: 4.5,
          ticks: { stepSize: 1, callback: v => Number.isInteger(v) ? `F${v}` : '' },
          title: { display: true, text: 'Frequency score' },
        },
      },
    },
  })
}

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('rfm-charts')
  if (!container) return

  const chartData   = JSON.parse(container.dataset.chartData)
  const segmentMeta = JSON.parse(container.dataset.segmentMeta)

  renderPie(chartData, segmentMeta)
  renderAvgSpend(chartData, segmentMeta)
  renderBounds(chartData)
  renderBubble(chartData)
})
