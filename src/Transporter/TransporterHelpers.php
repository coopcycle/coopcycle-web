<?php

namespace AppBundle\Transporter;


use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;


class TransporterHelpers {

    public static function parseSyncOptions(string $uri): Filesystem {


        $auth_details = parse_url($uri);

        //Enjoy the ugly hack :)
        if (!$auth_details && str_starts_with($uri, 'memory://')) {
            $auth_details = ['scheme' => 'memory'];
        }

        switch ($auth_details['scheme']) {
            case 'ftp':
                $adapter = new FtpAdapter(
                    FtpConnectionOptions::fromArray([
                        'host' => $auth_details['host'],
                        'username' => $auth_details['user'],
                        'password' => $auth_details['pass'],
                        'port' => $auth_details['port'] ?? 21,
                        'root' => $auth_details['path'] ?? '',
                        'ssl' => false,
                    ])
                );
                break;
            case 'sftp':
                $adapter = new SftpAdapter(
                    SftpConnectionProvider::fromArray([
                        'host' => $auth_details['host'],
                        'username' => $auth_details['user'],
                        'password' => $auth_details['pass'],
                        'port' => $auth_details['port'] ?? 22,
                    ]),
                    $auth_details['path'] ?? ''
                );
                break;
            case 'file':
                $adapter = new LocalFilesystemAdapter($auth_details['path']);
                break;
            case 'memory':
                $adapter = new InMemoryFilesystemAdapter();
                break;
            default:
                throw new \Exception(sprintf('Unknown scheme %s', $auth_details['scheme']));
        }

        return new Filesystem($adapter);
    }
}
