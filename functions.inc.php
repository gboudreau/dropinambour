<?php

use PommePause\Dropinambour\Config;

function string_contains($haystack, $needle) {
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

function string_begins_with($haystack, $needle) {
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

function array_contains($haystack, $needle) {
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

function to_array($array_or_element) {
    if (is_array($array_or_element)) {
        return $array_or_element;
    }
    return array($array_or_element);
}

function to_object($el) {
    if (is_object($el)) {
        return $el;
    }
    return (object) $el;
}

function first($array) {
    if (!is_array($array) || count($array) == 0) {
        return FALSE;
    }
    return array_shift($array);
}

function last($array) {
    if (!is_array($array)) {
        return FALSE;
    }
    return end($array);
}

function options_contains($haystack, $needle) {
    return ( ($needle & $haystack) === $needle );
}

function he($text) {
    $text = str_replace('&nbsp;', 0x0a00 . 0x0a00 . 0x0a00, $text);
    $text = htmlentities($text, ENT_COMPAT|ENT_QUOTES, 'UTF-8');
    $text = str_replace(0x0a00 . 0x0a00 . 0x0a00, '&nbsp;', $text);
    return $text;
}

function phe($text) {
    echo he($text);
}

function echo_if($condition, $text_if_true, $text_if_false = '') {
    if ($condition) {
        echo $text_if_true;
    } else {
        echo $text_if_false;
    }
}

function oddOrEvent() : string {
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

function minutes_to_human($minutes) {
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
function getPropValuesFromArray($array, $props, $keep_indices = FALSE) {
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

function get_http_accepts() {
    $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
    return explode(',', $accept);
}

function sendPOST($url, $data, $headers = array(), $content_type = NULL, string $method = 'POST', int $timeout = 30, bool $retry_on_429 = TRUE) {
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

function sendGET($url, $headers = array(), bool $follow_redirects = TRUE, int $timeout = 30, bool $retry_on_429 = TRUE) {
    $ch = curl_init();

    $headers[] = 'User-agent: PHP/dropinambour';

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

    if ($info['http_code'] >= 400) {
        $response = @json_decode($result);
        if ($response && !empty($response->error)) {
            throw new \Exception($response->error, $info['http_code']);
        }
        throw new \Exception('Non-200 HTTP status (' . $info['http_code'] . '). Response: ' . $result, $info['http_code']);
    }

    return $result;
}
