import { useCallback, useEffect, useState } from 'react'
import { fetchDepartures } from '../api/client.js'

/**
 * @typedef {object} DestinationResult
 * @property {object|null} data - Last good API response, kept when later requests fail.
 * @property {number|null} fetchedAt - Epoch ms of the last good response.
 * @property {boolean} error - True when the most recent request failed.
 */

/**
 * Polls departures for the visible tab and prefetches the next one so that
 * rotation is instant. Last good data is kept when a request fails, and the
 * next poll recovers without a reload.
 * @param {string|undefined} activeCrs - CRS code of the visible tab.
 * @param {string|undefined} prefetchCrs - CRS code to warm, or undefined.
 * @param {number} pollSeconds - Seconds between polls of the visible tab.
 * @returns {Object<string, DestinationResult>} Results keyed by CRS code.
 */
export function useDepartures(activeCrs, prefetchCrs, pollSeconds) {
  const [results, setResults] = useState({})

  /**
   * Fetch one destination and merge the outcome into state once it settles.
   * @param {string} crs - CRS code to load.
   * @returns {void}
   */
  const load = useCallback((crs) => {
    fetchDepartures(crs).then(
      (data) => setResults((prev) => ({ ...prev, [crs]: { data, fetchedAt: Date.now(), error: false } })),
      () =>
        setResults((prev) => ({
          ...prev,
          [crs]: { data: prev[crs]?.data ?? null, fetchedAt: prev[crs]?.fetchedAt ?? null, error: true },
        })),
    )
  }, [])

  useEffect(() => {
    if (!activeCrs) return undefined
    load(activeCrs)
    const id = setInterval(() => {
      if (!document.hidden) load(activeCrs)
    }, pollSeconds * 1000)
    const onVisible = () => {
      if (!document.hidden) load(activeCrs)
    }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      clearInterval(id)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [activeCrs, pollSeconds, load])

  useEffect(() => {
    if (prefetchCrs) load(prefetchCrs)
  }, [prefetchCrs, load])

  return results
}
