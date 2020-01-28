<?php

namespace AppBundle\Composer;

use Composer\Script\Event;
use Composer\Package\BasePackage;
use Composer\Util\Filesystem;

// @see https://github.com/0xch/composer-vendor-cleanup/blob/master/src/CleanupScript.php
// @see https://github.com/barryvdh/composer-cleanup-plugin/blob/master/src/CleanupPlugin.php
class Cleaner
{
    protected static $filesystem;

    public static function clean(Event $event)
    {
        self::$filesystem = new Filesystem();

        $repository = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        $files = [];
        foreach($repository->getPackages() as $package){
            if ($package instanceof BasePackage) {
                $files = array_merge($files, self::cleanPackage($event, $package));
            }
        }

        $event->getIO()->write(sprintf('Removing %d files', count($files)));

        foreach ($files as $file) {
            self::$filesystem->remove($file);
        }
    }

    public static function loadComposerJson($dir)
    {
        $file = $dir . '/composer.json';
        if (!is_file($file)) {
            // $this->io->writeError("Composer cleaner: File $file not found.", true, IOInterface::VERBOSE);
            return;
        }
        $data = json_decode(file_get_contents($file), true);
        // var_dump($data);
        // if (!$data instanceof stdClass) {
        //     // $this->io->writeError("Composer cleaner: Invalid $file.");
        //     return;
        // }
        return $data;
    }

    protected static function cleanPackage(Event $event, BasePackage $package)
    {
        $docs = 'README* CHANGELOG* FAQ* CONTRIBUTING* HISTORY* UPGRADING* UPGRADE* package* demo example examples doc docs readme* changelog*';
        $tests = '.travis.yml .scrutinizer.yml phpcs.xml* phpcs.php phpunit.xml* phpunit.php test tests Tests travis patchwork.json';

        // // Only clean 'dist' packages
        // if ($package->getInstallationSource() !== 'dist') {
        //     return false;
        // }

        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $targetDir = $package->getTargetDir();
        $packageName = $package->getPrettyName();
        $packageDir = $targetDir ? $packageName . '/' . $targetDir : $packageName;

        // print_r($package); exit;



        // var_dump($packageName);

        $rules = [
            $docs, $tests
        ];

        $dir = self::$filesystem->normalizePath(realpath($vendorDir . '/' . $packageDir));
        if (!is_dir($dir)) {
            return [];
        }

        // $composer = self::loadComposerJson($dir);

        // $blacklist = [];

        // // Fix "Could not scan for classes inside "vendor/oneup/uploader-bundle/Tests/App/AppKernel.php""
        // if (isset($composer['autoload'], $composer['autoload']['classmap'])) {
        //     foreach ($composer['autoload']['classmap'] as $path) {
        //         if (is_file($dir.'/'.$path)) {
        //             $blacklist[] = $dir.'/'.$path;
        //         }
        //     }
        // }

        // print_r($blacklist);

        if ($packageName === 'oneup/uploader-bundle') {
            return [];
        }

        $files = [];
        foreach((array) $rules as $part) {
            // Split patterns for single globs (should be max 260 chars)
            $patterns = explode(' ', trim($part));

            foreach ($patterns as $pattern) {
                try {
                    foreach (glob($dir.'/'.$pattern) as $file) {

                        // if (is_dir($file)) {
                        //     foreach ($blacklist as $key => $value) {
                        //         # code...
                        //     }
                        //     // if (strpos($, needle))
                        // }

                        // // strpos(haystack, glob_recursive)

                        // // var_dump($file);
                        // if (in_array($file, $blacklist)) {
                        //     var_dump('Skipped ' .$file);
                        //     continue;
                        // }
                        $files[] = $file;
                    }
                } catch (\Exception $e) {
                    // $this->io->write("Could not parse $packageDir ($pattern): ".$e->getMessage());
                }
            }
        }

        return $files;
    }
}
