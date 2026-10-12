/**
 * Footer with the National Rail credit, licence line and last update time.
 * @param {object} props - Component props.
 * @param {string|null} props.lastUpdated - Formatted last updated time, or null.
 * @returns {JSX.Element} The footer.
 */
export default function Footer({ lastUpdated }) {
  return (
    <footer className="footer">
      <p>
        Train information from{' '}
        <a href="https://www.nationalrail.co.uk" target="_blank" rel="noreferrer">
          National Rail
        </a>
        . Not affiliated with or endorsed by National Rail.
      </p>
      <p>
        Licensed{' '}
        <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/" target="_blank" rel="noreferrer">
          CC BY-NC-SA 4.0
        </a>
        {lastUpdated && <> · Updated {lastUpdated}</>}
      </p>
    </footer>
  )
}
