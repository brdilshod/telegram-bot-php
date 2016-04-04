<?php

class TelegramBotAPI {

    /**
     * BOT Token and BOT Name
     *
     * @var
     */
    private $apiKey;

    /**
     * HTTP codes
     *
     * @var array
     */
    public static $codes = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // RFC2518
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status', // RFC4918
        208 => 'Already Reported', // RFC5842
        226 => 'IM Used', // RFC3229
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect', // RFC7238
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity', // RFC4918
        423 => 'Locked', // RFC4918
        424 => 'Failed Dependency', // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal', // RFC2817
        426 => 'Upgrade Required', // RFC2817
        428 => 'Precondition Required', // RFC6585
        429 => 'Too Many Requests', // RFC6585
        431 => 'Request Header Fields Too Large', // RFC6585
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)', // RFC2295
        507 => 'Insufficient Storage', // RFC4918
        508 => 'Loop Detected', // RFC5842
        510 => 'Not Extended', // RFC2774
        511 => 'Network Authentication Required', // RFC6585
    ];
	
	public $fileFields = array('photo', 'document', 'audio', 'video', 'voice');

    /**
     * Default http status code
     */
    const DEFAULT_STATUS_CODE = 200;

    /**
     * Not Modified http status code
     */
    const NOT_MODIFIED_STATUS_CODE = 304;

    /**
     * Limits for tracked ids
     */
    const MAX_TRACKED_EVENTS = 200;

    /**
     * Url prefixes
     */
    const URL_PREFIX = 'https://api.telegram.org/bot';
    const FILE_URL_PREFIX = 'https://api.telegram.org/file/bot';

    /**
     * CURL object
     *
     * @var
     */
    protected $curl;

    /**
     * PHP input
     *
     * @var
     */
    protected $input;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;        
        $this->curl = curl_init();
        $this->input = file_get_contents('php://input');
    }

    /**
     * Call method
     *
     * @param string $method
     * @param array|null $data
     *
     * @return mixed
     */
    public function call($method, array $data = null, $file = null) {
        $options = [
            CURLOPT_URL => $this->getUrl() . '/' . $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => null,
            CURLOPT_POSTFIELDS => null
        ];
		foreach($this->fileFields as $field) {
			if (isset($data[$field])) {
				$data[$field] = new CURLFile(realpath($data['photo']));			
				$options[CURLOPT_HTTPHEADER] = array(
					"Content-Type:multipart/form-data"
				);            
			}
		}        
		if ($data) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        $response = self::jsonValidate($this->executeCurl($options), $this->returnArray);
        if ($this->returnArray) {
            if (!isset($response['ok'])) {
                throw new Exception($response['description'], $response['error_code']);
            }
            return $response['result'];
        }
        if (!$response->ok) {
            throw new Exception($response->description, $response->error_code);
        }
		
        return $response->result;
    }

    /**
     * curl_exec wrapper for response validation
     *
     * @param array $options
     *
     * @return string
     *
     */
    protected function executeCurl(array $options) {
        curl_setopt_array($this->curl, $options);
        $result = curl_exec($this->curl);
        self::curlValidate($this->curl);
        return $result;
    }

    /**
     * Response validation
     *
     * @param resource $curl
     */
    public static function curlValidate($curl) {
        if (($httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE)) && !in_array($httpCode, [self::DEFAULT_STATUS_CODE, self::NOT_MODIFIED_STATUS_CODE])) {
            throw new Exception(self::$codes[$httpCode], $httpCode);
        }
    }

    /**
     * JSON validation
     *
     * @param string $jsonString
     * @param boolean $asArray
     *
     * @return object|array
     */
    public static function jsonValidate($jsonString, $asArray) {
        $json = json_decode($jsonString, $asArray);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new Exception(json_last_error_msg(), json_last_error());
        }
        return $json;
    }

    /**
     * JSON parse
     *
     * @param string $input
     *
     * @return object|array
     */
    private function jsonParse($input, $returnArray = false) {
        return json_decode($input, $returnArray);
    }

    private function send($func, $args) {
        $data = $this->getData($func, $args);
        return $this->call($func, $data);
    }

    public function getMe() {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendMessage($chat_id, $text, $parse_mode = null, $disable_web_page_preview = false, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function forwardMessage($chat_id, $from_chat_id, $message_id) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendPhoto($chat_id, $photo, $caption = null, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendAudio($chat_id, $audio, $duration = null, $performer = null, $title = null, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendDocument($chat_id, $document, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendSticker($chat_id, $sticker, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendVideo($chat_id, $video, $duration = null, $caption = null, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendVoice($chat_id, $voice, $duration = null, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function sendLocation($chat_id, $latitude, $longitude, $reply_to_message_id = null, $reply_markup = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    /**
     * Type of action to broadcast. Choose one, depending on what the user is about to receive:
     * `typing` for text messages, `upload_photo` for photos, `record_video` or `upload_video` for videos,
     * `record_audio` or upload_audio for audio files, `upload_document` for general files,
     * `find_location` for location data.
     *
     * @param int $chat_id
     * @param string $chat_action
     *
     * @return bool 
     */
    public function sendChatAction($chat_id, $action) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function getUserProfilePhotos($user_id, $offset = null, $limit = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function getUpdates($offset = null, $limit = null, $timeout = null) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    public function getFile($file_id) {
        return $this->send(__FUNCTION__, func_get_args());
    }

    /**
     * Use this method to specify a url and receive incoming updates via an outgoing webhook.
     * Whenever there is an update for the bot, we will send an HTTPS POST request to the specified url,
     * containing a JSON-serialized Update.
     * In case of an unsuccessful request, we will give up after a reasonable amount of attempts.
     *
     * @param string $url HTTPS url to send updates to. Use an empty string to remove webhook integration
     * @param \CURLFile|string $certificate Upload your public key certificate
     *                                      so that the root certificate in use can be checked
     *
     * @return string
     */
    public function setWebhook($url = '', $certificate = null) {
        return $this->call('setWebhook', ['url' => $url, 'certificate' => $certificate]);
    }

    /**
     * Close curl
     */
    public function __destruct() {
        $this->curl && curl_close($this->curl);
    }

    /**
     * @return string
     */
    public function getUrl() {
        return self::URL_PREFIX . $this->apiKey;
    }

    /**
     * @return string
     */
    public function getFileUrl() {
        return self::FILE_URL_PREFIX . $this->token;
    }

    /**
     * Getting data
     * 
     * @param string function name
     * @param array arg values
     * 
     * @return array
     */
    protected function getData($funcName, $args) {
        $f = new ReflectionMethod(__CLASS__, $funcName);
        $result = array();
        foreach ($f->getParameters() as $key => $param) {
            if (isset($args[$key]))
                $result[$param->name] = $args[$key];
        }
        return $result;
    }

	/**
     * Getting chat_id    
     * 
     * @return integer
     */
    public function getChatId() {
        $input = $this->jsonParse($this->input);		
        return $input->message->chat->id;;
    }

	/**
     * Getting message    
     * 
     * @return string
     */
    public function getMessage() {
        $input = $this->jsonParse($this->input);
        return $input->message->text;
    }

}
