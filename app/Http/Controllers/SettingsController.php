<?php

namespace App\Http\Controllers;

class SettingsController extends Controller
{
    public function sshKey()
    {
        $pubKeyPath = config('backups.ssh_key_path').'.pub';
        $publicKey = file_exists($pubKeyPath) ? trim(file_get_contents($pubKeyPath)) : null;

        return view('settings.ssh-key', ['publicKey' => $publicKey]);
    }
}
