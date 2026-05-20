<?php
/**
 * esewa_proxy.php
 *
 * Why this exists:
 *   The eSewa RC sandbox server sometimes returns a 502 Bad Gateway.
 *   When the browser POSTs directly to eSewa and gets a 502, the user
 *   is stranded on a blank nginx error page with no way back.
 *
 *   This proxy POSTs to eSewa server-side using cURL, inspects the
 *   HTTP status code before the user ever leaves our site, and:
 *     - On success (2xx/3xx): streams the eSewa payment page HTML back
 *       to the browser (the user sees the eSewa UI as normal).
 *     - On 5xx / network error: redirects back to payment.php with a
 *       clear error message — the user never sees a blank 502 page.
 *
 * Allowed fields (whitelisted to prevent abuse):
 *   amount, tax_amount, total_amount, transaction_uuid, product_code,
 *   product_service_charge, product_delivery_charge,
 *   success_url, failure_url, signed_field_names, signature
 */

require_once '../includes/auth.php';
start_session();
session_security_check();

// Must be a logged-in customer
if (empty($_SESSION['user_id'])) {
    header('Location: portal-login.php');
    exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'customer') {
    header('Location: portal-login.php');
    exit;
}

// Must be a POST request with the expected fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['transaction_uuid'])) {
    header('Location: payment.php');
    exit;
}

// ── Whitelist and collect the fields eSewa expects ────────────────────────────
$allowed_fields = [
    'amount', 'tax_amount', 'total_amount', 'transaction_uuid',
    'product_code', 'product_service_charge', 'product_delivery_charge',
    'success_url', 'failure_url', 'signed_field_names', 'signature',
];

$post_data = [];
foreach ($allowed_fields as $field) {
    if (isset($_POST[$field])) {
        $post_data[$field] = $_POST[$field];
    }
}

$esewa_url = 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';

// ── cURL POST to eSewa ────────────────────────────────────────────────────────
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $esewa_url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post_data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,   // We handle redirects manually
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HEADER         => true,    // Include response headers in output
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'HeraldCanteen/1.0',
]);

$response   = curl_exec($ch);
$http_code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$curl_error  = curl_error($ch);
$location    = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

// ── Handle cURL-level failure (network unreachable, timeout, TLS error) ───────
if ($response === false || $curl_error !== '') {
    error_log('eSewa proxy cURL error: ' . $curl_error);
    $_SESSION['_esewa_error'] = 'Could not connect to eSewa. Please check your internet connection and try again.';
    header('Location: payment.php?esewa_err=connect');
    exit;
}

//Handle HTTP 5xx / 4xx from eSewa
if ($http_code >= 500) {
    error_log('eSewa proxy received HTTP ' . $http_code . ' from eSewa server.');
    $_SESSION['_esewa_error'] = 'eSewa\'s payment server is temporarily unavailable (Error ' . $http_code . '). Please try again in a few minutes, or choose Cash on Delivery.';
    header('Location: payment.php?esewa_err=' . $http_code);
    exit;
}

// Handle 3xx redirect 
if ($http_code >= 300 && $http_code < 400) {
    // Extract Location header and redirect the browser there
    $resp_headers = substr($response, 0, $header_size);
    if (preg_match('/^Location:\s*(.+)$/im', $resp_headers, $m)) {
        $redirect_to = trim($m[1]);
        header('Location: ' . $redirect_to);
        exit;
    }
    // Fallback
    header('Location: payment.php?esewa_err=redirect');
    exit;
}

$body = substr($response, $header_size);
$resp_headers = substr($response, 0, $header_size);
$content_type = 'text/html; charset=UTF-8';
if (preg_match('/^Content-Type:\s*(.+)$/im', $resp_headers, $m)) {
    $content_type = trim($m[1]);
}

header('Content-Type: ' . $content_type);
header('X-Frame-Options: SAMEORIGIN');
echo $body;
exit;
