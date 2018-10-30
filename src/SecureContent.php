<?php

namespace Zenapply\HRIS\Paylocity;

use phpseclib\Crypt\RSA;
use phpseclib\Crypt\AES;
use phpseclib\Crypt\Random;

class SecureContent
{
    protected $data;
    protected $secret_key;
    protected $public_key;
    protected $iv;
    protected $rsa;
    protected $aes;

    public function __construct($data, $public_key_path)
    {
        $this->data = $data;
        $this->secret_key = Random::string(32);
        $this->public_key = file_get_contents($public_key_path);
        
        // AES
        $this->aes = new AES(AES::MODE_CBC);
        $this->aes->setKey($this->secret_key);
        $this->aes->setKeyLength(256);
        $this->aes->setBlockLength(128);
        $this->iv = Random::string($this->aes->getBlockLength() >> 3);
        $this->aes->setIV($this->iv);

        // RSA
        $this->rsa = new RSA();
        $this->rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $this->rsa->loadKey($this->public_key);
        $this->rsa->setPublicKey($this->public_key);
    }

    protected function aes_encrypt($data)
    {
        return $this->aes->encrypt($data);
    }

    protected function rsa_encrypt($data)
    {
        return $this->rsa->encrypt($data);
    }

    public function get()
    {
        $key = base64_encode($this->rsa_encrypt($this->secret_key));
        $content = base64_encode($this->aes_encrypt(json_encode($this->data)));
        $iv = base64_encode($this->iv);

        return json_encode(["secureContent" => ["key"=>$key, "iv"=>$iv, "content"=>$content]]);
    }

    public function toJson()
    {
        return $this->__toString();
    }

    public function __toString()
    {
        $string = $this->get();
        return $string;
    }
}