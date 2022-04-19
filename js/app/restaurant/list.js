import React, {useState, useRef, useEffect} from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import Swiper, { Navigation } from 'swiper'
import { times } from 'lodash'

import { asText } from '../components/ShippingTimeRange'
import { useIntersection } from '../hooks/useIntersection'

import 'swiper/css';
import 'swiper/css/navigation'

import './list.scss'

// turn off automatic browser handle of scroll
window.history.scrollRestoration = 'manual'

// save last scroll position right before the page is exited
// if user navigates back to this screen, when all previous pages are loaded we scroll to this position
window.addEventListener("beforeunload", () => {
  localStorage.setItem("shops-lastScrollPos", window.document.documentElement.scrollTop);
});

const FulfillmentBadge = ({ range }) => {

  return (
    <span className="restaurant-item__time-range">
      <i className="fa fa-clock-o mr-2"></i>
      <span>{ asText(range, false, true) }</span>
    </span>
  )
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery && !data.collection) {
      return
    }

    const ranges = []
    if (data.delivery && data.delivery.range) {
      ranges.push(data.delivery.range)
    }
    if (data.collection && data.collection.range) {
      ranges.push(data.collection.range)
    }

    ranges.sort((a, b) => moment(a[0]).isSame(b[0]) ? 0 : (moment(a[0]).isBefore(b[0]) ? -1 : 1))

    render(<FulfillmentBadge range={ ranges[0] } />, el)
  })
})

const Paginator = ({ page, pages }) => {
  const [currentPage, setCurrentPage] = useState(page)
  const [totalPages] = useState(pages)
  const [loading, setLoading] = useState(false)
  const [loadingPrevious, setLoadingPrevious] = useState(false)

  useEffect(() => {
    // scroll to top when shops list screen is rendered
    window.scrollTo(0, 0)
  }, [])

  const ref = useRef()

  const shopsEl = $("#shops-list")

  const inViewport = useIntersection(ref, '10px')

  const loadPage = (page, onSuccess) => {
    const searchParams = new URLSearchParams(window.location.search)
      searchParams.set("page", page);
      $.ajax({
        url : window.location.pathname + '?' + searchParams.toString(),
        type: 'GET',
        cache: false,
        success: function(data) {
          onSuccess(data)
        }
      })
  }

  const loadMore = () => {
    if (!loading) {
      const newPage = currentPage < totalPages ? currentPage + 1 : currentPage

      if (newPage > currentPage) {
        setLoading(true)
        loadPage(newPage, (data) => {
          shopsEl.append($.parseHTML(data.rendered_list))
            setTimeout(() => {
              setCurrentPage(newPage)
              setLoading(false)
            }, 100)
        })
      }
    }
  }

  useEffect(() => {
    if (page > 1) {
      // previously user has scrolled to a page > 1 in shops page
      $("#shops-list").hide()
      setLoadingPrevious(true)
      setLoading(true)

      // we need to fetch all previous pages until last 'page' seen
      times(page - 1, (num) => {
        // num is an index, from 0 to the previous page number to last page seen
        const newPage = page - (num + 1) // we want to load from the begining 1,2,etc until last page seen
        loadPage(newPage, (data) => {
          shopsEl.prepend($.parseHTML(data.rendered_list))
            if ((num + 1) === (page - 1)) {
              // we have loaded all previous pages
              $("#shops-list").show()
              // without this timeout the rendering of Paginator behaves weird
              setTimeout(() => {
                setCurrentPage(page)
                setLoadingPrevious(false)
                setLoading(false)
                // auto scroll to last scroll position
                window.scrollTo({ top: localStorage.getItem("shops-lastScrollPos"), behavior: 'smooth' })
              }, 100)
            }
        })
      })
    }
  }, [])

  if (inViewport && !loadingPrevious) {
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
  slidesPerView: 1.25,
  spaceBetween: 2,
  slidesPerGroup: 1,
  navigation: {
    nextEl: '.swiper-nav-next',
    prevEl: '.swiper-nav-prev',
  },
  lazyLoading: true,
  breakpoints: {
    480: {
      slidesPerView: 2.25,
      spaceBetween: 2,
      slidesPerGroup: 1,
    },
    768: {
      slidesPerView: 3.25,
      spaceBetween: 2,
      slidesPerGroup: 2,
    },
    992: {
      slidesPerView: 4.25,
      spaceBetween: 3,
      slidesPerGroup: 2,
    },
    1200: {
      slidesPerView: 5.25,
      spaceBetween: 4,
      slidesPerGroup: 3,
    },
  },
  observer: true, // to be initialized properly inside a hidden container
  observeParents: true,
  on: {
    afterInit: function() {
      // we need to hide the swiper at the begining because until is not initialized the images do not look very well
      document.querySelectorAll('.homepage-restaurants').forEach(element => element.classList.remove('hidden'))
    }
  }
})

function disableAndSubmit(e) {
  document.querySelectorAll('.shops-side-bar-filters input[type="radio"]:not(:checked)').forEach((radio) => radio.disabled=true)
  document.querySelectorAll('.shops-side-bar-filters input[type="checkbox"]:not(:checked)').forEach((check) => check.disabled=true)
  $(e.target).closest('form').submit()
}

$('.shops-side-bar-filters input[type=radio]').on('click', function (e) {
  disableAndSubmit(e)
});

$('.shops-side-bar-filters input[type=checkbox]').on('click', function (e) {
  disableAndSubmit(e)
});
