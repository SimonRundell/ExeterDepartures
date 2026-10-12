import axios from 'axios'

/**
 * Base URL of the PHP API. In development this comes from `devApiBase` in
 * .config.json (injected at dev-server start by vite.config.js), since Vite
 * and the PHP API run on different ports. In production the API sits beside
 * the built app, so a relative path is used.
 * @type {string}
 */
const API_BASE = import.meta.env.VITE_API_BASE || './api'

/**
 * True when the page was opened with ?mock=1, which asks the API for fixture data.
 * @type {boolean}
 */
const MOCK = new URLSearchParams(window.location.search).has('mock')

/**
 * Shared Axios instance for all API calls. A trailing slash keeps relative paths tidy.
 * @type {import('axios').AxiosInstance}
 */
export const http = axios.create({ baseURL: API_BASE.replace(/\/?$/, '/'), timeout: 15000 })

/**
 * Fetch the non-secret configuration (destinations, windows, ui settings).
 * @returns {Promise<{origin: {crs: string, name: string}, destinations: Array<{crs: string, name: string}>, windows: object, ui: object}>}
 */
export async function fetchConfig() {
  const { data } = await http.get('destinations.php')
  return data
}

/**
 * Fetch normalised departures for one destination.
 * @param {string} crs - Destination CRS code, for example "PLY".
 * @returns {Promise<{destination: object, generatedAt: string, fromCache: boolean, stale: boolean, services: object[], messages: string[]}>}
 */
export async function fetchDepartures(crs) {
  const { data } = await http.get('departures.php', { params: { dest: crs, ...(MOCK ? { mock: 1 } : {}) } })
  return data
}
