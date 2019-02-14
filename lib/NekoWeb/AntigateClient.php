<?php
namespace NekoWeb;

use GuzzleHttp\Client;

class AntigateClient
{

    public $apiServer = 'http://antigate.com';
    protected $client;
    protected $apiKey;

    public function __construct($apiServer = 'http://antigate.com', $guzzleOptions = [ ])
    {
        $this->apiServer = $apiServer;
        $this->client = new Client($guzzleOptions);
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $captchaBody
     * @param array $config @see sendCaptcha
     * @return string
     */
    public function recognize($captchaBody, array $config = [ ])
    {
        $captchaId = $this->sendCaptcha($captchaBody, $config);
        do {
            sleep(5);
            $captchaText = $this->checkStatus($captchaId);
        } while ($captchaText === false);
        return $captchaText;
    }

    /**
     * @param string $filename
     * @param array $config @see sendCaptcha
     * @return string
     */
    public function recognizeByFilename($filename, array $config = [ ])
    {
        $captchaId = $this->sendCaptchaByFilename($filename, $config);
        do {
            sleep(5);
            $captchaText = $this->checkStatus($captchaId);
        } while ($captchaText === false);
        return $captchaText;
    }

    /**
     * @param string $url
     * @param array $config @see sendCaptcha
     * @return string
     */
    public function recognizeByUrl($url, array $config = [ ])
    {
        $captchaId = $this->sendCaptchaByUrl($url, $config);
        do {
            sleep(5);
            $captchaText = $this->checkStatus($captchaId);
        } while ($captchaText === false);
        return $captchaText;
    }

    /**
     * @param string $filename
     * @param array $config @see sendCaptcha()
     * @throws AntigateException
     * @return int
     */
    public function sendCaptchaByFilename($filename, array $config = [ ])
    {
        return $this->sendCaptcha(file_get_contents($filename), $config);
    }

    /**
     * @param string $url
     * @param array $config @see sendCaptcha()
     * @throws AntigateException
     * @return int
     */
    public function sendCaptchaByUrl($url, array $config = [ ])
    {
        return $this->sendCaptcha(strval($this->client->get($url)->getBody()), $config);
    }

    /**
     * @param string $captchaBody
     * @param array $config
     *              phrase       0 = одно слово, 1 = капча имеет два слова
     *              regsense     0 = регистр не имеет значения, 1 = регистр имеет значение
     *              numeric      0 = значение по умолчанию, 1 = капча состоит только из цифр, 2 = Капча не имеет цифр
     *              calc         0 = значение по умолчанию, 1 = математеческое действие из цифр на капче
     *              min_len      0 = значение по умолчанию, >0 = минимальная длина текста на капче, которую работник должен ввести
     *              max_len      0 = неограничено, >0 = максимальная длина текста на капче, которую работник должен ввести
     *              is_russian   0 = значение по умолчанию, 1 = показать капчу работнику со знанием русского языка
     *              max_bid      Значение по-умолчанию выставляется на странице ставок. Этот параметр позволяет контролировать максимальную ставку без необходимости ее правки на странице ставок.
     * @throws AntigateException
     * @return int
     */
    public function sendCaptcha($captchaBody, array $config = [ ])
    {
        $response = $this->client->post("{$this->apiServer}/in.php", [
            'form_params' => array_merge($config, [
                'method' => 'base64',
                'key'    => $this->apiKey,
                'body'   => base64_encode($captchaBody),
            ])
        ]);
        $responseData = explode('|', strval($response->getBody()), 2);
        if ($responseData[0] != 'OK') {
            throw new AntigateException($responseData[0]);
        }
        if (!isset($responseData[1]) || !is_numeric($responseData[1])) {
            throw new AntigateException("Invalid response format: {$responseData[0]}|{$responseData[1]}");
        }
        return (int) $responseData[1];
    }

    /**
     * @param int $id
     * @return string|boolean
     * @throws AntigateException
     */
    public function checkStatus($id)
    {
        $response = $this->client->get("{$this->apiServer}/res.php?" . http_build_query([
                'action' => 'get',
                'key'    => $this->apiKey,
                'id'     => $id
            ]));

        $responseData = explode('|', $response->getBody(), 2);

        if ($responseData[0] == 'CAPCHA_NOT_READY') {
            return false;
        }
        if ($responseData[0] != 'OK') {
            throw new AntigateException($responseData[0]);
        }
        if (!isset($responseData[1]) || empty($responseData[1])) {
            throw new AntigateException("Invalid response format: {$responseData[0]}|{$responseData[1]}");
        }
        return $responseData[1];
    }

    /**
     * @param int[] $ids
     * @return string|boolean
     * @throws AntigateException
     */
    public function checkStatusBatch($ids)
    {
//		http://antigate.com/res.php?key=ваш_ключ_здесь_32_байта_длиной&action=get&ids=ID_1,ID_2,...,ID_N
        throw new AntigateException("Not implemented yet");
    }

    /**
     * @return float
     */
    public function getBalance()
    {
        $response = $this->client->get("{$this->apiServer}/res.php?" . http_build_query([
                'action' => 'getbalance',
                'key'    => $this->apiKey
            ]));

        return (float) $response->getBody();
    }

    /**
     * @param string $date In format YYYY-MM-DD
     * @throws AntigateException
     * @return array
     */
    public function getStatistic($date)
    {
        $response = $this->client->get("{$this->apiServer}/res.php?" . http_build_query([
                'action' => 'getstats',
                'key'    => $this->apiKey,
                'date'   => $date,
            ]));

        /** @var $xml \SimpleXMLElement */
        if ($xml = simplexml_load_string($response->getBody())) {
            $statistic = [ ];
            foreach ($xml->stats as $hourXml) {
                $hour             = (int) $hourXml->attributes()->hour;
                $statistic[$hour] = [
                    'volume' => (int) $hourXml->volume,
                    'money'  => (float) $hourXml->money,
                ];
            }
            return $statistic;
        }
        throw new AntigateException('Invalid API response');
    }

    public function getRealtimeStatistic()
    {
        $response = $this->client->get("{$this->apiServer}/load.php?" . http_build_query([
                'key' => $this->apiKey,
            ]));

        /** @var $xml \SimpleXMLElement */
        if ($xml = simplexml_load_string($response->getBody())) {
            return [
                'waiting'                => (int) $xml->waiting,
                'load'                   => (float) $xml->load,
                'minbid'                 => (float) $xml->minbid,
                'averageRecognitionTime' => (float) $xml->averageRecognitionTime,
            ];
        }
        throw new AntigateException('Invalid API response');
    }
}

class AntigateException extends \Exception
{ }
