<?php

namespace BlueFission\BlueCore;

class Security {
    private static $_appKey;

    public static function init() {
        self::$_appKey = getenv('APP_KEY');
        if (!self::$_appKey) {
            throw new Exception('Application key not set.');
        }
    }

    public static function generateKey() {
        return base64_encode(random_bytes(32));
    }

    public static function encrypt($data) {
        $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($data, 'AES-256-CBC', self::$_appKey, 0, $iv));
    }

    public static function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        return openssl_decrypt(substr($data, 16), 'AES-256-CBC', self::$_appKey, 0, $iv);
    }

    public static function createToken($data) {
        return hash_hmac('sha256', $data, self::$_appKey);
    }

    public static function verifyToken($token, $data) {
        return hash_equals($token, static::createToken($data));
    }
}
