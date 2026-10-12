<?php
/**
 * Darwin OpenLDBWS client. Builds the SOAP envelope by hand and sends it with
 * cURL, so it works whether or not the PHP soap extension is enabled.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Strip XML namespaces so the response can be walked with plain element names.
 *
 * @param string $xml Raw SOAP response.
 * @return string Namespace-free XML.
 */
function stripNamespaces(string $xml): string
{
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);
    $xml = preg_replace('/(<\/?)[\w.-]+:/', '$1', $xml);
    return preg_replace('/(\s)[\w.-]+:([\w.-]+=)/', '$1$2', $xml);
}

/**
 * Build the GetDepBoardWithDetails SOAP envelope.
 *
 * @param array  $config Validated config.
 * @param string $from   Origin CRS.
 * @param string $to     Filter CRS.
 * @param int    $rows   Maximum rows.
 * @return string SOAP XML.
 */
function buildEnvelope(array $config, string $from, string $to, int $rows): string
{
    $ns = $config['darwin']['namespace'] ?? 'http://thalesgroup.com/RTTI/2021-11-01/ldb/';
    $typesNs = $config['darwin']['typesNamespace'] ?? 'http://thalesgroup.com/RTTI/2013-11-28/Token/types';
    $token = htmlspecialchars($config['darwin']['token'], ENT_XML1);
    $crs = htmlspecialchars($from, ENT_XML1);
    $filter = htmlspecialchars($to, ENT_XML1);
    $rows = (int) $rows;

    return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:typ="{$typesNs}" xmlns:ldb="{$ns}">
  <soap:Header>
    <typ:AccessToken><typ:TokenValue>{$token}</typ:TokenValue></typ:AccessToken>
  </soap:Header>
  <soap:Body>
    <ldb:GetDepBoardWithDetailsRequest>
      <ldb:numRows>{$rows}</ldb:numRows>
      <ldb:crs>{$crs}</ldb:crs>
      <ldb:filterCrs>{$filter}</ldb:filterCrs>
      <ldb:filterType>to</ldb:filterType>
      <ldb:timeOffset>0</ldb:timeOffset>
      <ldb:timeWindow>120</ldb:timeWindow>
    </ldb:GetDepBoardWithDetailsRequest>
  </soap:Body>
</soap:Envelope>
XML;
}

/**
 * Fetch a departure board with calling points.
 *
 * @param array  $config Validated config.
 * @param string $from   Origin CRS.
 * @param string $to     Filter CRS (services calling at this station).
 * @param int    $rows   Maximum rows.
 * @return SimpleXMLElement The GetStationBoardResult element.
 * @throws RuntimeException On transport, HTTP or SOAP fault errors.
 */
function fetchDepartureBoard(array $config, string $from, string $to, int $rows = 15): SimpleXMLElement
{
    $ns = $config['darwin']['namespace'] ?? 'http://thalesgroup.com/RTTI/2021-11-01/ldb/';
    $ch = curl_init($config['darwin']['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => buildEnvelope($config, $from, $to, $rows),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ' . $ns . 'GetDepBoardWithDetails',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Darwin unreachable: ' . $err);
    }
    $xml = @simplexml_load_string(stripNamespaces($body));
    if ($xml === false) {
        throw new RuntimeException('Darwin returned unreadable data (HTTP ' . $status . ')');
    }
    return extractBoard($xml, $status);
}

/**
 * Pull the board element out of a parsed, namespace-free response.
 *
 * @param SimpleXMLElement $xml    Parsed response.
 * @param int              $status HTTP status, for error messages.
 * @return SimpleXMLElement The GetStationBoardResult element.
 * @throws RuntimeException When the response is a fault or has no board.
 */
function extractBoard(SimpleXMLElement $xml, int $status = 200): SimpleXMLElement
{
    $result = $xml->xpath('//GetStationBoardResult');
    if (!$result) {
        $fault = $xml->xpath('//faultstring');
        throw new RuntimeException('Darwin error (HTTP ' . $status . ')' . ($fault ? ': ' . (string) $fault[0] : ''));
    }
    return $result[0];
}
