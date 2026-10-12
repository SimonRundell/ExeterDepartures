import { useCallback, useEffect, useRef, useState } from 'react'

/** Seconds before a rotation at which the next tab is prefetched. */
const PREFETCH_LEAD_SECONDS = 5

/**
 * Auto-rotates through tabs. Interaction pauses rotation for a while, and
 * rotation holds still while the page is hidden.
 * @param {object} options - Hook options.
 * @param {number} options.count - Number of tabs.
 * @param {number} options.rotateSeconds - Seconds each tab stays visible.
 * @param {number} options.pauseSeconds - Seconds rotation pauses after interaction.
 * @returns {{index: number, progress: number, paused: boolean, prefetchNext: boolean, select: function(number): void, pause: function(): void}}
 *   Active index, progress 0 to 1 towards the next rotation, pause flag, prefetch hint and controls.
 */
export function useRotation({ count, rotateSeconds, pauseSeconds }) {
  const [index, setIndex] = useState(0)
  const [progress, setProgress] = useState(0)
  const [paused, setPaused] = useState(false)
  const [prefetchNext, setPrefetchNext] = useState(false)
  const timing = useRef({ start: 0, pausedUntil: 0 })

  /** Pause rotation for the configured period and restart the countdown after it. */
  const pause = useCallback(() => {
    const until = Date.now() + pauseSeconds * 1000
    timing.current.pausedUntil = until
    timing.current.start = until
  }, [pauseSeconds])

  /**
   * Show a specific tab and pause rotation.
   * @param {number} i - Tab index.
   */
  const select = useCallback(
    (i) => {
      setIndex(i)
      pause()
    },
    [pause],
  )

  useEffect(() => {
    if (count < 1) return undefined
    const rotateMs = rotateSeconds * 1000
    timing.current.start = Date.now()
    const id = setInterval(() => {
      const t = timing.current
      const now = Date.now()
      if (document.hidden) {
        t.start = now
        return
      }
      const isPaused = now < t.pausedUntil
      setPaused(isPaused)
      if (isPaused) {
        setProgress(0)
        setPrefetchNext(false)
        return
      }
      const elapsed = now - t.start
      if (elapsed >= rotateMs) {
        t.start = now
        setIndex((i) => (i + 1) % count)
        setProgress(0)
        setPrefetchNext(false)
      } else {
        setProgress(elapsed / rotateMs)
        setPrefetchNext(rotateMs - elapsed <= PREFETCH_LEAD_SECONDS * 1000)
      }
    }, 250)
    return () => clearInterval(id)
  }, [count, rotateSeconds])

  return { index: count ? index % count : 0, progress, paused, prefetchNext, select, pause }
}
