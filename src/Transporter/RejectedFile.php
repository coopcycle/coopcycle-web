<?php

declare(strict_types=1);

namespace AppBundle\Transporter;

/**
 * A file an import couldn't process, as it was received.
 */
final class RejectedFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $reason,
        public readonly string $content,
    ) {
    }

    /**
     * What the importers are told on flush(): reasons, keyed like the files.
     *
     * @param array<array-key,RejectedFile> $files
     * @return array<array-key,string>
     */
    public static function reasons(array $files): array
    {
        return array_map(fn(RejectedFile $file): string => $file->reason, $files);
    }
}
