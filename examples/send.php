<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Anypost\Anypost;
use Anypost\Exceptions\AnypostException;

// Reads ANYPOST_API_KEY from the environment. Pass the key explicitly with
// new Anypost('ap_...') if you prefer.
$client = new Anypost();

try {
    $email = $client->email->send([
        'from' => 'Acme <you@yourdomain.com>',
        'to' => ['someone@example.com'],
        'subject' => 'Hello from Anypost',
        'html' => '<p>It worked.</p>',
    ]);

    echo "Queued {$email->id}\n";
} catch (AnypostException $e) {
    fwrite(STDERR, "Send failed: {$e->getErrorType()} — {$e->getMessage()}\n");
    exit(1);
}
