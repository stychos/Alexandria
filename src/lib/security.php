<?php /** @noinspection PhpMethodMayBeStaticInspection */

namespace alexandria\lib;

class security
{
    protected $crypt_key;

    public function __construct(array $args = null)
    {
        $this->crypt_key = $args['crypt_key'] ?? 'mzEMRrR/QqUwAy7sBc9YFRpI8mQ+l8Gc4SfaDMSoO3fxpa8hqQkkyiTj7zKLAKct7X6RF8MXMO3SkytAPzILLf8VP9vMEkdrZlFdXZSaadIQzbUu1Mp0ovxY/tjYd2Da';
    }

    public function set_key(string $key)
    {
        $this->crypt_key = $key;
    }

    public function string(int $length = 8): string
    {
        $ret = $this->bytes($length);
        $ret = base64_encode($ret);
        $ret = substr($ret, 0, $length);
        return $ret;
    }

    public function bytes(int $length = 8)
    {
        $ret = openssl_random_pseudo_bytes($length);
        return $ret;
    }

    public function encode($data)
    {
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv    = openssl_random_pseudo_bytes($ivlen);
        $raw   = openssl_encrypt($data, $cipher, $this->crypt_key, $options = OPENSSL_RAW_DATA, $iv);
        $hmac  = hash_hmac('sha256', $raw, $this->crypt_key, $as_binary = true);
        $ret   = base64_encode($iv.$hmac.$raw);
        return $ret;
    }

    public function decode($data)
    {
        $c              = base64_decode($data);
        $ivlen          = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv             = substr($c, 0, $ivlen);
        $hmac           = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $original       = openssl_decrypt($ciphertext_raw, $cipher, $this->crypt_key, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac        = hash_hmac('sha256', $ciphertext_raw, $this->crypt_key, $as_binary = true);
        return (hash_equals($hmac, $calcmac)) ? $original : false;
    }
}
