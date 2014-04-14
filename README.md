# Antigate client

# Usage examples

```php
<?php
require_once 'vendor/autoload.php';

$client = new NekoWeb\AntigateClient();
$client->setApiKey('--YOUR-KEY--');

// Solve from file
file_put_contents('tmp.jpg', file_get_contents('--CAPTCHA-URL--'));
echo $client->recognizeByFilename('tmp.jpg');

// Solve from URL
echo $client->recognizeByUrl('--CAPTCHA-URL--');

// Return Antigate balance
echo $client->getBalance();

// Return your usage statistics for a date
print_r($client->getStatistic('2014-04-11'));

// Return realtime Antigate statistics
print_r($client->getRealtimeStatistic());
```

