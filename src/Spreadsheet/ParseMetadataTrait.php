<?php

namespace AppBundle\Spreadsheet;

use AppBundle\Entity\Task;

trait ParseMetadataTrait
{
    private function parseAndApplyMetadata(Task $task, $metadataRecord)
    {
        array_map(function ($metaString) use ($task) {
            [$metaKey, $metaValue] = explode("=", $metaString);
            $task->setMetadata($metaKey, $metaValue);
        }, explode(" ", $metadataRecord));
    }
}
