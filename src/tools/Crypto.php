<?php

namespace moe\tools;

use moe\Instance;
use moe\Prefab;

/**
 * @source http://www.warpconduit.net/2013/04/14/highly-secure-data-encryption-decryption-made-easy-with-php-mcrypt-rijndael-256-and-cbc/
 */
final class Crypto extends Prefab
{
    private $key;

    const
        E_Key='No secret key defined!',
        E_Invalid='Secret key must be 64-char';

    // Encrypt Function
    public function encrypt($string)
    {
        $string = serialize($string);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_DEV_URANDOM);
        $key = pack('H*', $this->key);
        $mac = hash_hmac('sha256', $string, substr(bin2hex($key), -32));
        $passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $string.$mac, MCRYPT_MODE_CBC, $iv);
        return base64_encode($passcrypt).'|'.base64_encode($iv);
    }

    // Decrypt Function
    public function decrypt($string){
        $string = explode('|', $string.'|');
        $decoded = base64_decode($string[0]);
        $iv = base64_decode($string[1]);
        if(strlen($iv)!==mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC))
            return false;
        $key = pack('H*', $this->key);
        $decrypted = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_CBC, $iv));
        $mac = substr($decrypted, -64);
        $decrypted = substr($decrypted, 0, -64);
        $calcmac = hash_hmac('sha256', $decrypted, substr(bin2hex($key), -32));
        if($calcmac!==$mac)
            return false;
        return unserialize($decrypted);
    }

    public function compare($string, $encrypted)
    {
        return $string === $this->decrypt($encrypted);
    }

    public function __construct()
    {
        $this->key = Instance::get('SECRETKEY');
        if (!$this->key)
            throw new Exception(self::E_Key);
        if (strlen($this->key)!=64)
            throw new Exception(self::E_Invalid);
    }
}
