import React, {useState, useRef, useEffect} from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
import moment from 'moment'
import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'
import classNames from 'classnames'

import { asText } from '../components/ShippingTimeRange'
import { useIntersection } from '../hooks/useIntersection'

require('gasparesganga-jquery-loading-overlay')

import 'swiper/css';
import 'swiper/css/navigation'

import './list.scss'

/**
 * Turn off automatic browser handle of scroll
 *
 * The browser by default saves the last position of the scroll so that when
 * the user returns to the screen (e.g. when going to a restaurant and back again)
 * returns to the last section of the list that has been viewing.
 * But in our case we don't load the whole list, but we load it as the user scrolls
 * therefore when returning to the screen the last position of the scroll in a previous navigation
 * may be beyond the amount of elements we are displaying.

 * By disabling the default scroll handling we make the browser not want to position the user in a place
 * that no longer exists because the list has been restarted.
 */
window.history.scrollRestoration = 'manual'

const FulfillmentBadge = ({ range, isPreOrder }) => {

  return (
    <span className={ classNames('restaurant-item__time-range', 'rendered-badge', { 'pre-order': isPreOrder }) }>
      {!isPreOrder ? (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 11.38">
          <path d="M14.52 4.41c-.29 0-.58.05-.85.12l-.72-2.23s-.02-.06-.04-.09v-.03l.8-.14v.47c0 .1.08.17.17.17h1c.1 0 .17-.08.17-.17V1.22c0-.2-.09-.39-.24-.53a.684.684 0 00-.55-.16l-2.49.43a.71.71 0 00-.49.37c-.07.15-.09.32-.05.48H6.52l-.29-.74h.86c.33 0 .59-.24.59-.54s-.27-.54-.59-.54h-2.1c-.33 0-.59.24-.59.54 0 .23.15.42.37.5l.56 1.44-.79 1.63a3.5 3.5 0 00-.97-.14c-1.98 0-3.58 1.66-3.58 3.7s1.61 3.7 3.58 3.7c1.66 0 3.07-1.19 3.47-2.79l.04.13c.07.25.27.44.52.49.25.05.5-.05.66-.25l3.8-5 .37 1.16c-.85.64-1.4 1.64-1.4 2.78 0 1.92 1.56 3.48 3.48 3.48s3.48-1.56 3.48-3.48-1.56-3.48-3.48-3.48zm2.17 3.48c0 1.19-.97 2.17-2.17 2.17s-2.17-.97-2.17-2.17.97-2.17 2.17-2.17 2.17.97 2.17 2.17zM5.83 7.67c0 1.28-1 2.32-2.24 2.32S1.35 8.95 1.35 7.67s1-2.32 2.24-2.32 2.24 1.04 2.24 2.32zM6.08 5c-.1-.1-.2-.19-.31-.27l.15-.31.16.58zm4.87-1.8L8.04 7.03 6.98 3.2h3.96z" />
        </svg>
      ) : (
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="44"
          height="44"
          viewBox="0 0 24 24"
          strokeWidth="2"
          stroke="#2c3e50"
          fill="none"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path stroke="none" d="M0 0h24v24H0z" fill="none" />
          <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
          <path d="M12 7v5l3 3" />
        </svg>
      )}
      {asText(range, false, true)}
    </span>
  )
}

function addFulfillmentBadge(el) {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery && !data.collection) {
      return
    }

    const isPreOrder = JSON.parse(el.dataset.preorder)

    const ranges = []
    if (data.delivery && data.delivery.range) {
      ranges.push(data.delivery.range)
    }
    if (data.collection && data.collection.range) {
      ranges.push(data.collection.range)
    }

    ranges.sort((a, b) => moment(a[0]).isSame(b[0]) ? 0 : (moment(a[0]).isBefore(b[0]) ? -1 : 1))

    render(<FulfillmentBadge range={ ranges[0] } isPreOrder={ isPreOrder } />, el)
  })
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  addFulfillmentBadge(el)
})

const Paginator = ({ page, pages }) => {
  const [currentPage, setCurrentPage] = useState(page)
  const [totalPages] = useState(pages)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    // scroll to top when shops list screen is rendered
    window.scrollTo(0, 0)
  }, [])

  const ref = useRef()

  const inViewport = useIntersection(ref, '10px')

  const loadMore = () => {
    if (!loading) {
      const newPage = currentPage < totalPages ? currentPage + 1 : currentPage

      if (newPage > currentPage) {
        setLoading(true)

        const shopsEl = $("#shops-list")

        $.ajax({
          url : window.location.pathname + window.location.search,
          data: {
            page: newPage,
          },
          type: 'GET',
          cache: false,
          success: function(data) {
            shopsEl.append($.parseHTML(data.rendered_list))
            renderFulfillmentBadgeAfterAjax()
            setTimeout(() => {
              setCurrentPage(newPage)
              setLoading(false)
            }, 100)
          }
        })
      }
    }
  }

  if (inViewport && !loading) {
    loadMore();
  }

  return (
    <div ref={ref} className="shops-list-paginator">
      {loading && <span><i className="fa fa-spinner fa-spin"></i></span>}
    </div>
  )
}

const paginator = document.getElementById('shops-list-paginator')

if (paginator) {
  render(
    <Paginator
     page={Number(paginator.dataset.page)}
     pages={Number(paginator.dataset.pages)} />,
    paginator
  )
}

new Swiper('.swiper', {
  modules: [ Navigation ],
  slidesPerView: 'auto',
  spaceBetween: 2,
  slidesPerGroup: 1,
  navigation: {
    nextEl: '.swiper-nav-next',
    prevEl: '.swiper-nav-prev',
  },
  lazyLoading: true,
  breakpoints: {
    480: {
      slidesPerView: 1.25,
      spaceBetween: 2,
      slidesPerGroup: 1,
    },
    768: {
      slidesPerView: 2.1,
      spaceBetween: 2,
      slidesPerGroup: 2,
    },
    992: {
      slidesPerView: 2.75,
      spaceBetween: 2,
      slidesPerGroup: 2,
    },
    1200: {
      slidesPerView: 3.3,
      spaceBetween: 2.5,
      slidesPerGroup: 3,
    },
  },
  observer: true, // to be initialized properly inside a hidden container
  observeParents: true
})

function resetPaginator(data) {
  if (paginator) {
    unmountComponentAtNode(paginator)
    render(
      <Paginator
       page={Number(data.page)}
       pages={Number(data.pages)} />,
      paginator
    )
  }
}

function renderFulfillmentBadgeAfterAjax() {
  document.querySelectorAll('[data-fulfillment]').forEach(el => {
    // render fulfillment badge only to new elements
    if (el.firstChild.classList && el.firstChild.classList.contains('rendered-badge')) {
      return;
    }
    addFulfillmentBadge(el)
  })
}

function submitFilter(e) {
  $('.shops-content').LoadingOverlay('show', {
    image: false,
  })

  const shopsEl = $("#shops-list")

  $.ajax({
    url : $(e.target).closest('form').attr('path'),
    data: $(e.target).closest('form').serialize(),
    type: $(e.target).closest('form').attr('method'),
    cache: false,
    success: function(data) {
      resetPaginator(data)

      shopsEl.empty().append($.parseHTML(data.rendered_list)) // show results

      renderFulfillmentBadgeAfterAjax()

      // applyClickListenerForRestaurantItem()

      // update URL with applied filters
      const searchParams = new URLSearchParams($(e.target).closest('form').serialize())
      const path = `${$(e.target).closest('form').attr('path')}?${searchParams.toString()}`
      window.history.pushState({path}, '', path)

      $('.shops-content').LoadingOverlay('hide', {
        image: false,
      })
    }
  })
}

$('.shops-side-bar-filters input[type=radio]').on('click', function (e) {
  submitFilter(e)
});

$('.shops-side-bar-filters input[type=checkbox]').on('click', function (e) {
  submitFilter(e)
});

/**
 * When the user clicks on a restaurant and
 * there is no address scroll into the search bar, and ask for an address.
 */
// eslint-disable-next-line no-unused-vars
function applyClickListenerForRestaurantItem() {
  document.querySelectorAll('[data-restaurant-path]').forEach(el => {
    el.addEventListener("click", function (e) {

      const searchParams = new URLSearchParams(window.location.search);

      // check if there is an address query param
      if (!searchParams.has('address')) {
        // if there is not an address do not navigate to restaurant page
        e.preventDefault()

        // scroll into the search bar and ask for an address
        document.querySelectorAll('[data-search="address"]').forEach((container) => {
          const el = container.querySelector('[data-element]')
          if (el) {
            el.scrollIntoView({behavior: "smooth"})
            const inputEl = el.querySelector('input[type="search"]')
            if (inputEl) {
              inputEl.focus();
            }
          }
        })
      }

    })
  })
}

// applyClickListenerForRestaurantItem()
