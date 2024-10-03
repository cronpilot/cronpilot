<?php

namespace App\Helpers;

use App\Models\ServerCredential;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Exception;

class Connection
{
    private string $hostname;
    private int $credentialId;

    public function __construct(
        string $hostname,
        int    $credentialId
    )
    {
        $this->hostname = $hostname;
        $this->credentialId = $credentialId;
    }

    /**
     * @throws Exception
     */
    public function connectToServer(): array
    {
        $credentials = ServerCredential::query()->find($this->credentialId);
        try {

            if (!$credentials) {
                return [
                    'connected' => false,
                    'credentials' => null
                ];
            }
            $key = PublicKeyLoader::load($credentials->ssh_private_key, $credentials->passphrase);
            $ssh = new SSH2($this->hostname);
           $isConnected = $ssh->login($credentials->username, $key);
            return [
                'connected' => $isConnected,
                'credentials' => $credentials->title,
            ];

        } catch (\Exception $e) {
            return [
                'connected' => false,
                'credentials' => $credentials->title
            ];
        }

    }
}
