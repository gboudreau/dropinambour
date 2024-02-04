<?php

use PommePause\Dropinambour\Config;

function string_contains(?string $haystack, string|array $needle) : bool {
    if (is_array($needle)) {
        foreach ($needle as $n) {
            if (string_contains($haystack, $n)) {
                return TRUE;
            }
        }
        return FALSE;
    }
    return stripos($haystack, $needle) !== FALSE;
}

function string_begins_with(?string $haystack, string|array $needle) : bool {
    if (is_array($needle)) {
        foreach ($needle as $n) {
            if (string_begins_with($haystack, $n)) {
                return TRUE;
            }
        }
        return FALSE;
    }
    return stripos($haystack, $needle) === 0;
}

function array_contains(array $haystack, mixed $needle) : bool {
    if (empty($haystack)) {
        return FALSE;
    }
    return array_search($needle, $haystack) !== FALSE;
}

function array_clone(array $array) : array {
    $new_array = [];
    foreach ($array as $k => $v) {
        if (is_object($v)) {
            $new_array[$k] = clone $v;
        } elseif (is_array($v)) {
            $new_array[$k] = array_clone($v);
        } else {
            $new_array[$k] = $v;
        }
    }
    return $new_array;
}

function to_array($array_or_element) : array {
    if (is_array($array_or_element)) {
        return $array_or_element;
    }
    return array($array_or_element);
}

function to_object($el) : object {
    if (is_object($el)) {
        return $el;
    }
    return (object) $el;
}

function first($array) : mixed {
    if (!is_array($array) || count($array) == 0) {
        return FALSE;
    }
    return array_shift($array);
}

function last($array) : mixed {
    if (!is_array($array)) {
        return FALSE;
    }
    return end($array);
}

function options_contains($haystack, $needle) : bool {
    return ( ($needle & $haystack) === $needle );
}

function he($text) : string {
    $text = str_replace('&nbsp;', 0x0a00 . 0x0a00 . 0x0a00, $text);
    $text = htmlentities($text, ENT_COMPAT|ENT_QUOTES, 'UTF-8');
    $text = str_replace(0x0a00 . 0x0a00 . 0x0a00, '&nbsp;', $text);
    return $text;
}

function phe($text) : void {
    echo he($text);
}

function echo_if(bool $condition, string $text_if_true, string $text_if_false = '') : void {
    if ($condition) {
        echo $text_if_true;
    } else {
        echo $text_if_false;
    }
}

function oddOrEven() : string {
    global $odd_even;
    if (empty($odd_even)) {
        $odd_even = 1;
    }
    $result = ($odd_even % 2 == 0 ? 'even' : 'odd');
    $odd_even++;
    return $result;
}

function is_https() : bool {
    if (isset($_SERVER['HTTP_CF_VISITOR'])) {
        // We're behind the CloudFlare proxy; HTTP_CF_VISITOR will tell us if we're serving content using HTTPS or not
        return string_contains($_SERVER['HTTP_CF_VISITOR'], 'https');
    }
    if (@$_SERVER['HTTPS'] == 'on' || @$_SERVER['HTTPS'] == 1 || @$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !isset($_SERVER['HTTP_HOST'])) {
        return TRUE;
    }
    return FALSE;
}

function minutes_to_human(int $minutes) : string {
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $minutes -= $hours * 60;
        if ($minutes == 0) {
            return sprintf("%dh", $hours);
        }
        return sprintf("%dh %dm", $hours, $minutes);
    }
    return sprintf("%dm", $minutes);
}

/**
 * Extract values from an array of objects.
 *
 * @param array  $array        Array of objects.
 * @param string $props        Properties to extract from the objects, using dot-notation. Examples: 'id', 'sub.id', 'ride.other_user.name'
 * @param bool   $keep_indices Keep array indices?
 *
 * @return array
 */
function getPropValuesFromArray(array $array, string $props, bool $keep_indices = FALSE) : array {
    $values = [];
    foreach ($array as $idx => $value) {
        $found = TRUE;
        foreach (explode('.', $props) as $prop) {
            if (isset($value->{$prop})) {
                $value = $value->{$prop};
            } else {
                $found = FALSE;
                break;
            }
        }
        if ($found) {
            if ($keep_indices) {
                $values[$idx] = $value;
            } else {
                $values[] = $value;
            }
        }
    }
    return $values;
}

/**
 * Loop on $array, and then on element.$prop, and if $value is found, return element.$return_prop (or element, if $return_prop === '.').
 * If $value is not found, return $default_return_value.
 *
 * @param array  $array
 * @param string $prop
 * @param        $value
 * @param string $return_prop
 * @param        $default_return_value
 *
 * @return mixed
 */
function findPropValueInArray(array $array, string $prop, $value, string $return_prop, $default_return_value) : mixed {
    foreach ($array as $element) {
        $found = is_array($element->{$prop}) && array_contains($element->{$prop}, $value);
        $found |= !is_array($element->{$prop}) && $element->{$prop} == $value;
        if ($found) {
            if ($return_prop === '.') {
                return $element;
            }
            return $element->{$return_prop};
        }
    }
    return $default_return_value;
}

function get_http_accepts() : array {
    $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
    return explode(',', $accept);
}

function sendPOST(string $url, $data, array $headers = array(), ?string $content_type = NULL, string $method = 'POST', int $timeout = 30, bool $retry_on_429 = TRUE) {
    $ch = curl_init();

    if (!empty($content_type)) {
        $headers[] = "Content-type: $content_type";
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    if ($method != 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    } else {
        curl_setopt($ch, CURLOPT_POST, TRUE);
    }
    if (string_contains($content_type, '/json')) {
        if ($data !== NULL) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif (!empty($data)) {
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $proxy = Config::get('CURL_PROXY');
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    $result = curl_exec($ch);

    if (curl_errno($ch) != 0) {
        throw new \Exception(curl_error($ch), curl_errno($ch));
    }

    $info = curl_getinfo($ch);

    curl_close($ch);

    if ($info['http_code'] == 429 && $retry_on_429) {
        // Too Many Requests; sleep 1s and retry
        sleep(1);
        return sendPOST($url, $data, $headers, $content_type, $method, $timeout, FALSE);
    }

    if ($info['http_code'] >= 400) {
        $response = @json_decode($result);
        if ($response && !empty($response->error->localized_message)) {
            $error_code = $response->error->code ?? $info['http_code'];
            throw new \Exception($response->error->localized_message, $error_code);
        }
        throw new \Exception('Non-200 HTTP status (' . $info['http_code'] . '). Response: ' . $result, $info['http_code']);
    }

    return $result;
}

function sendGET(string $url, array $headers = array(), bool $follow_redirects = TRUE, int $timeout = 30, bool $retry_on_429 = TRUE, bool $use_flaresolverr = FALSE) {
    $ch = curl_init();

    $headers[] = 'User-agent: PHP/dropinambour';

    if ($use_flaresolverr && Config::get('FLARESOLVERR_URL')) {
        $cookies = [];
        foreach ($headers as $header) {
            if (string_begins_with($header, 'Cookie: ')) {
                foreach (explode(';', substr($header, 8)) as $cookie_txt) {
                    [$name, $value] = explode('=', trim($cookie_txt));
                    $cookies[] = ['name' => $name, 'value' => $value];
                }
            }
        }
        $data = [
            'cmd'        => 'request.get',
            'url'        => $url,
            'maxTimeout' => $timeout*1000,
            'cookies'    => $cookies,
        ];
        $response = sendPOST(Config::get('FLARESOLVERR_URL'), $data, $headers, 'application/json');
        $result = @json_decode($response);

        if ($result->solution->status >= 400 || empty($result)) {
            throw new \Exception('Non-200 HTTP status (' . ($result->solution->status ?? 0) . '). Response: ' . $response, ($result->solution->status ?? 0));
        }

        if (!empty($result->solution->response)) {
            $result->solution->response = preg_replace('@.*pre-wrap;.>(.*)</pre.*@', '\1', $result->solution->response);
            return $result->solution->response;
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($follow_redirects) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $proxy = Config::get('CURL_PROXY');
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    $result = curl_exec($ch);

    if (curl_errno($ch) != 0) {
        throw new \Exception(curl_error($ch), curl_errno($ch));
    }

    $info = curl_getinfo($ch);

    curl_close($ch);

    if ($info['http_code'] == 429 && $retry_on_429) {
        // Too Many Requests; sleep 1s and retry
        sleep(1);
        return sendGET($url, $headers, $follow_redirects, $timeout, FALSE);
    }

    if ($info['http_code'] == 403 && !$use_flaresolverr && Config::get('FLARESOLVERR_URL')) {
        return sendGET($url, $headers, $follow_redirects, $timeout, use_flaresolverr: TRUE);
    }

    if ($info['http_code'] >= 400) {
        $response = @json_decode($result);
        if ($response && !empty($response->error)) {
            throw new \Exception($response->error, $info['http_code']);
        }
        throw new \Exception('Non-200 HTTP status (' . $info['http_code'] . '). Response: ' . $result, $info['http_code']);
    }

    return $result;
}

function lang_from_code(?string $iso_639_1_code) : string {
    return [
        'ab' => 'Abkhazian',
        'aa' => 'Afar',
        'af' => 'Afrikaans',
        'ak' => 'Akan',
        'sq' => 'Albanian',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'an' => 'Aragonese',
        'hy' => 'Armenian',
        'as' => 'Assamese',
        'av' => 'Avaric',
        'ae' => 'Avestan',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'bm' => 'Bambara',
        'ba' => 'Bashkir',
        'eu' => 'Basque',
        'be' => 'Belarusian',
        'bn' => 'Bengali',
        'bh' => 'Bihari languages',
        'bi' => 'Bislama',
        'bs' => 'Bosnian',
        'br' => 'Breton',
        'bg' => 'Bulgarian',
        'my' => 'Burmese',
        'ca' => 'Catalan, Valencian',
        'km' => 'Central Khmer',
        'ch' => 'Chamorro',
        'ce' => 'Chechen',
        'ny' => 'Chichewa, Chewa, Nyanja',
        'zh' => 'Chinese',
        'cu' => 'Church Slavonic, Old Bulgarian, Old Church Slavonic',
        'cv' => 'Chuvash',
        'kw' => 'Cornish',
        'co' => 'Corsican',
        'cr' => 'Cree',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'dv' => 'Divehi, Dhivehi, Maldivian',
        'nl' => 'Dutch, Flemish',
        'dz' => 'Dzongkha',
        'en' => 'English',
        'eo' => 'Esperanto',
        'et' => 'Estonian',
        'ee' => 'Ewe',
        'fo' => 'Faroese',
        'fj' => 'Fijian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'ff' => 'Fulah',
        'gd' => 'Gaelic, Scottish Gaelic',
        'gl' => 'Galician',
        'lg' => 'Ganda',
        'ka' => 'Georgian',
        'de' => 'German',
        'ki' => 'Gikuyu, Kikuyu',
        'el' => 'Greek (Modern)',
        'kl' => 'Greenlandic, Kalaallisut',
        'gn' => 'Guarani',
        'gu' => 'Gujarati',
        'ht' => 'Haitian, Haitian Creole',
        'ha' => 'Hausa',
        'he' => 'Hebrew',
        'hz' => 'Herero',
        'hi' => 'Hindi',
        'ho' => 'Hiri Motu',
        'hu' => 'Hungarian',
        'is' => 'Icelandic',
        'io' => 'Ido',
        'ig' => 'Igbo',
        'id' => 'Indonesian',
        'ia' => 'Interlingua (International Auxiliary Language Association)',
        'ie' => 'Interlingue',
        'iu' => 'Inuktitut',
        'ik' => 'Inupiaq',
        'ga' => 'Irish',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'jv' => 'Javanese',
        'kn' => 'Kannada',
        'kr' => 'Kanuri',
        'ks' => 'Kashmiri',
        'kk' => 'Kazakh',
        'rw' => 'Kinyarwanda',
        'kv' => 'Komi',
        'kg' => 'Kongo',
        'ko' => 'Korean',
        'kj' => 'Kwanyama, Kuanyama',
        'ku' => 'Kurdish',
        'ky' => 'Kyrgyz',
        'lo' => 'Lao',
        'la' => 'Latin',
        'lv' => 'Latvian',
        'lb' => 'Letzeburgesch, Luxembourgish',
        'li' => 'Limburgish, Limburgan, Limburger',
        'ln' => 'Lingala',
        'lt' => 'Lithuanian',
        'lu' => 'Luba-Katanga',
        'mk' => 'Macedonian',
        'mg' => 'Malagasy',
        'ms' => 'Malay',
        'ml' => 'Malayalam',
        'mt' => 'Maltese',
        'gv' => 'Manx',
        'mi' => 'Maori',
        'mr' => 'Marathi',
        'mh' => 'Marshallese',
        'ro' => 'Moldovan, Moldavian, Romanian',
        'mn' => 'Mongolian',
        'na' => 'Nauru',
        'nv' => 'Navajo, Navaho',
        'nd' => 'Northern Ndebele',
        'ng' => 'Ndonga',
        'ne' => 'Nepali',
        'se' => 'Northern Sami',
        'no' => 'Norwegian',
        'nb' => 'Norwegian Bokmål',
        'nn' => 'Norwegian Nynorsk',
        'ii' => 'Nuosu, Sichuan Yi',
        'oc' => 'Occitan (post 1500)',
        'oj' => 'Ojibwa',
        'or' => 'Oriya',
        'om' => 'Oromo',
        'os' => 'Ossetian, Ossetic',
        'pi' => 'Pali',
        'pa' => 'Panjabi, Punjabi',
        'ps' => 'Pashto, Pushto',
        'fa' => 'Persian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'qu' => 'Quechua',
        'rm' => 'Romansh',
        'rn' => 'Rundi',
        'ru' => 'Russian',
        'sm' => 'Samoan',
        'sg' => 'Sango',
        'sa' => 'Sanskrit',
        'sc' => 'Sardinian',
        'sr' => 'Serbian',
        'sn' => 'Shona',
        'sd' => 'Sindhi',
        'si' => 'Sinhala, Sinhalese',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'so' => 'Somali',
        'st' => 'Sotho, Southern',
        'nr' => 'South Ndebele',
        'es' => 'Spanish, Castilian',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'ss' => 'Swati',
        'sv' => 'Swedish',
        'tl' => 'Tagalog',
        'ty' => 'Tahitian',
        'tg' => 'Tajik',
        'ta' => 'Tamil',
        'tt' => 'Tatar',
        'te' => 'Telugu',
        'th' => 'Thai',
        'bo' => 'Tibetan',
        'ti' => 'Tigrinya',
        'to' => 'Tonga (Tonga Islands)',
        'ts' => 'Tsonga',
        'tn' => 'Tswana',
        'tr' => 'Turkish',
        'tk' => 'Turkmen',
        'tw' => 'Twi',
        'ug' => 'Uighur, Uyghur',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        've' => 'Venda',
        'vi' => 'Vietnamese',
        'vo' => 'Volap_k',
        'wa' => 'Walloon',
        'cy' => 'Welsh',
        'fy' => 'Western Frisian',
        'wo' => 'Wolof',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'yo' => 'Yoruba',
        'za' => 'Zhuang, Chuang',
        'zu' => 'Zulu'
    ][$iso_639_1_code] ?? $iso_639_1_code;
}

function get_from_cache(string $cache_key, callable $calculate_fn) : mixed {
    $key = "globalcache-$cache_key";
    if (empty($GLOBALS[$key])) {
        $GLOBALS[$key] = $calculate_fn();
    }
    return $GLOBALS[$key];
}
