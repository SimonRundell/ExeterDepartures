import { useEffect, useState } from 'react'

/**
 * Current time, updated every second.
 * @returns {Date} The current date and time.
 */
export function useClock() {
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 1000)
    return () => clearInterval(id)
  }, [])
  return now
}
