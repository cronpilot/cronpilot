<?php

namespace App\Helpers;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Exception;

class Connection
{
    private string $ssh_private_key;
    private string $passphrase;
    private string $hostname;
    private string $username;

    public function __construct(string $ssh_private_key, string $passphrase, string $hostname, string $username)
    {
        $this->ssh_private_key = $ssh_private_key;
        $this->hostname = $hostname;
        $this->passphrase = $passphrase;
        $this->username = $username;
    }

    /**
     * @throws Exception
     */
    public function connectToServer(): bool
    {
        try {
            $key = PublicKeyLoader::load($this->ssh_private_key, $this->passphrase);
            $ssh = new SSH2($this->hostname);

            if (!$ssh->login($this->username, $key)) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            throw new Exception('Connection Failed: ' . $e->getMessage());
        }

    }
}
