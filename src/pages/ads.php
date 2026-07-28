<?php
/*
 * ads.txt, generated from configuration. Serves /ads.txt.
 *
 * WHY THIS IS GENERATED RATHER THAN A STATIC FILE
 *   The repository previously shipped a static ads.txt containing a placeholder
 *   publisher id. A wrong id is worse than no file at all: AdSense reports it as
 *   an invalid ads.txt, which counts against the account. Reading the id from
 *   $adsenseClient means the file is either correct or absent, and it starts
 *   working the moment the real id is provisioned.
 */

global $adsenseClient;

$client = trim((string) ($adsenseClient ?? ''));

header('Content-Type: text/plain; charset=utf-8');

// AdSense publisher ids look like "ca-pub-1234567890123456"; ads.txt wants the
// "pub-..." form without the "ca-" prefix.
if (preg_match('/^ca-pub-(\d{10,20})$/', $client, $m)) {
  echo "google.com, pub-{$m[1]}, DIRECT, f08c47fec0942fa0\n";
} else {
  echo "# No AdSense publisher id configured yet.\n";
  echo "# Set \$adsenseClient in config.local.php (or the ADSENSE_CLIENT secret,\n";
  echo "# then run the \"Provision server config\" workflow) and this file fills in.\n";
}
